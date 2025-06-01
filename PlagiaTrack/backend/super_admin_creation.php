<?php
require_once 'config.php';
require_once 'utilisateurs.php';

$utilisateurs = new Utilisateurs($pdo);

if ($utilisateurs->superAdminExiste()) {
    header('Location: login.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';
    $motDePasseConfirm = $_POST['mot_de_passe_confirm'] ?? '';

    if (!$nom || !$prenom || !$email || !$motDePasse || !$motDePasseConfirm) {
        $errors[] = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email n'est pas valide.";
    } elseif ($motDePasse !== $motDePasseConfirm) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    } else {
        // Récupérer l'id du rôle super_admin
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE nom = 'super_admin'");
        $stmt->execute();
        $role = $stmt->fetch();
        if (!$role) {
            $errors[] = "Le rôle super_admin n'existe pas dans la base de données.";
        } else {
            $role_id = $role['id'];
            $utilisateurs->creerUtilisateur($nom, $prenom, $email, $motDePasse, $role_id, true);
            header('Location: login.php');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Création du Super Administrateur</title>
</head>
<body>
    <h1>Création du Super Administrateur</h1>
    <?php if ($errors): ?>
        <ul style="color:red;">
            <?php foreach ($errors as $error): ?>
                <li><?=htmlspecialchars($error)?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <form method="post" action="">
        <label>Nom : <input type="text" name="nom" required></label><br>
        <label>Prénom : <input type="text" name="prenom" required></label><br>
        <label>Email : <input type="email" name="email" required></label><br>
        <label>Mot de passe : <input type="password" name="mot_de_passe" required></label><br>
        <label>Confirmer mot de passe : <input type="password" name="mot_de_passe_confirm" required></label><br>
        <button type="submit">Créer le Super Administrateur</button>
    </form>
</body>
</html>
