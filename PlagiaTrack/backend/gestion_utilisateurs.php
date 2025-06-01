<?php
require_once 'auth.php';
require_once 'utilisateurs.php';

if (!Auth::estConnecte()) {
    header('Location: login.php');
    exit;
}

$user = Auth::getUser();
$utilisateurs = new Utilisateurs($pdo);

// Vérifier que l'utilisateur est administrateur ou super admin
if (!in_array($user['role_id'], [1, 2])) { // 1 = super_admin, 2 = administrateur (à confirmer selon la base)
    echo "Accès refusé.";
    exit;
}

// Traitement des actions : ajouter, supprimer, modifier, réprimander
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'ajouter') {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';
        $role_id = intval($_POST['role_id'] ?? 0);

        if ($nom && $prenom && $email && $motDePasse && $role_id) {
            $utilisateurs->creerUtilisateur($nom, $prenom, $email, $motDePasse, $role_id);
            header('Location: gestion_utilisateurs.php');
            exit;
        } else {
            $error = "Tous les champs sont obligatoires.";
        }
    } elseif ($action === 'modifier') {
        $id = intval($_POST['id'] ?? 0);
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role_id = intval($_POST['role_id'] ?? 0);
        $statut = $_POST['statut'] ?? 'actif';

        if ($id && $nom && $prenom && $email && $role_id && $statut) {
            $utilisateurs->modifierUtilisateur($id, $nom, $prenom, $email, $role_id, $statut);
            header('Location: gestion_utilisateurs.php');
            exit;
        } else {
            $error = "Tous les champs sont obligatoires.";
        }
    }
} elseif ($action === 'supprimer') {
    $id = intval($_GET['id'] ?? 0);
    if ($id) {
        $utilisateurs->supprimerUtilisateur($id);
        header('Location: gestion_utilisateurs.php');
        exit;
    }
} elseif ($action === 'reprimander') {
    $id = intval($_GET['id'] ?? 0);
    if ($id) {
        $utilisateurs->reprimanderUtilisateur($id);
        header('Location: gestion_utilisateurs.php');
        exit;
    }
}

// Récupérer la liste des utilisateurs
$stmt = $pdo->query("SELECT u.*, r.nom AS role_nom FROM utilisateurs u JOIN roles r ON u.role_id = r.id ORDER BY u.id ASC");
$liste_utilisateurs = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM roles ORDER BY id ASC");
$liste_roles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des utilisateurs - PlagiaTrack</title>
</head>
<body>
    <h1>Gestion des utilisateurs</h1>
    <?php if (!empty($error)): ?>
        <p style="color:red;"><?=htmlspecialchars($error)?></p>
    <?php endif; ?>

    <h2>Ajouter un utilisateur</h2>
    <form method="post" action="?action=ajouter">
        <label>Nom : <input type="text" name="nom" required></label><br>
        <label>Prénom : <input type="text" name="prenom" required></label><br>
        <label>Email : <input type="email" name="email" required></label><br>
        <label>Mot de passe : <input type="password" name="mot_de_passe" required></label><br>
        <label>Rôle :
            <select name="role_id" required>
                <?php foreach ($liste_roles as $role): ?>
                    <option value="<?=htmlspecialchars($role['id'])?>"><?=htmlspecialchars($role['nom'])?></option>
                <?php endforeach; ?>
            </select>
        </label><br>
        <button type="submit">Ajouter</button>
    </form>

    <h2>Liste des utilisateurs</h2>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>ID</th><th>Nom</th><th>Prénom</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($liste_utilisateurs as $utilisateur): ?>
                <tr>
                    <td><?=htmlspecialchars($utilisateur['id'])?></td>
                    <td><?=htmlspecialchars($utilisateur['nom'])?></td>
                    <td><?=htmlspecialchars($utilisateur['prenom'])?></td>
                    <td><?=htmlspecialchars($utilisateur['email'])?></td>
                    <td><?=htmlspecialchars($utilisateur['role_nom'])?></td>
                    <td><?=htmlspecialchars($utilisateur['statut'])?></td>
                    <td>
                        <a href="?action=reprimander&id=<?=htmlspecialchars($utilisateur['id'])?>" onclick="return confirm('Réprimander cet utilisateur ?')">Réprimander</a> |
                        <a href="?action=supprimer&id=<?=htmlspecialchars($utilisateur['id'])?>" onclick="return confirm('Supprimer cet utilisateur ?')">Supprimer</a> |
                        <a href="modifier_utilisateur.php?id=<?=htmlspecialchars($utilisateur['id'])?>">Modifier</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p><a href="dashboard.php">Retour au tableau de bord</a></p>
</body>
</html>
