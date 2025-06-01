<?php
require_once 'config.php';

class Utilisateurs {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Vérifie si le super administrateur est déjà créé
    public function superAdminExiste() {
        $stmt = $this->pdo->query("SELECT est_cree FROM super_admin_creation WHERE id = 1");
        $result = $stmt->fetch();
        return $result && $result['est_cree'] == 1;
    }

    // Marque le super administrateur comme créé
    public function marquerSuperAdminCree() {
        $stmt = $this->pdo->prepare("UPDATE super_admin_creation SET est_cree = 1 WHERE id = 1");
        return $stmt->execute();
    }

    // Crée un utilisateur (super admin ou autre)
    public function creerUtilisateur($nom, $prenom, $email, $motDePasse, $role_id, $est_super_admin = false) {
        $motDePasseHash = password_hash($motDePasse, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, role_id, est_super_admin) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nom, $prenom, $email, $motDePasseHash, $role_id, $est_super_admin ? 1 : 0]);
        if ($est_super_admin) {
            $this->marquerSuperAdminCree();
        }
        return $this->pdo->lastInsertId();
    }

    // Authentifie un utilisateur par email et mot de passe
    public function authentifier($email, $motDePasse) {
        $stmt = $this->pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($motDePasse, $user['mot_de_passe_hash'])) {
            if ($user['statut'] !== 'actif') {
                return null; // utilisateur inactif ou sanctionné
            }
            return $user;
        }
        return null;
    }

    // Récupère un utilisateur par ID
    public function getUtilisateurById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // Supprime un utilisateur par ID
    public function supprimerUtilisateur($id) {
        $stmt = $this->pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Modifier un utilisateur (exemple : nom, prenom, email, role, statut)
    public function modifierUtilisateur($id, $nom, $prenom, $email, $role_id, $statut) {
        $stmt = $this->pdo->prepare("UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, role_id = ?, statut = ? WHERE id = ?");
        return $stmt->execute([$nom, $prenom, $email, $role_id, $statut, $id]);
    }

    // Réprimander un utilisateur (changer statut en sanctionné)
    public function reprimanderUtilisateur($id) {
        $stmt = $this->pdo->prepare("UPDATE utilisateurs SET statut = 'sanctionne' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Gérer les permissions (à implémenter selon la table role_permissions)
    public function getPermissionsByRole($role_id) {
        $stmt = $this->pdo->prepare("SELECT p.nom FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id WHERE rp.role_id = ?");
        $stmt->execute([$role_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Ajouter une permission à un rôle
    public function ajouterPermissionRole($role_id, $permission_id) {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        return $stmt->execute([$role_id, $permission_id]);
    }

    // Supprimer une permission d'un rôle
    public function supprimerPermissionRole($role_id, $permission_id) {
        $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?");
        return $stmt->execute([$role_id, $permission_id]);
    }

    // Récupérer tous les rôles
    public function getRoles() {
        $stmt = $this->pdo->query("SELECT * FROM roles ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    // Récupérer toutes les permissions
    public function getPermissions() {
        $stmt = $this->pdo->query("SELECT * FROM permissions ORDER BY id ASC");
        return $stmt->fetchAll();
    }
}
?>
