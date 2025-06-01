<?php
session_start();

class Auth {
    // Connecte un utilisateur (stocke les données en session)
    public static function login($user) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom'],
            'email' => $user['email'],
            'role_id' => $user['role_id'],
            'est_super_admin' => $user['est_super_admin']
        ];
    }

    // Déconnecte l'utilisateur (détruit la session)
    public static function logout() {
        session_unset();
        session_destroy();
    }

    // Vérifie si un utilisateur est connecté
    public static function estConnecte() {
        return isset($_SESSION['user']);
    }

    // Récupère les données de l'utilisateur connecté
    public static function getUser() {
        return $_SESSION['user'] ?? null;
    }

    // Vérifie si l'utilisateur connecté est super admin
    public static function estSuperAdmin() {
        return self::estConnecte() && !empty($_SESSION['user']['est_super_admin']);
    }

    // Vérifie si l'utilisateur connecté a un rôle spécifique
    public static function aRole($role_id) {
        return self::estConnecte() && $_SESSION['user']['role_id'] == $role_id;
    }
}
?>
