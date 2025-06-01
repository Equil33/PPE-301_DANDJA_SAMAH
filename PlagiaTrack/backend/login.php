<?php
require_once 'config.php';
require_once 'utilisateurs.php';
require_once 'auth.php';

$utilisateurs = new Utilisateurs($pdo);

if ($utilisateurs->superAdminExiste() === false) {
    header('Location: super_admin_creation.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';

    if (!$email || !$motDePasse) {
        $errors[] = "Veuillez remplir tous les champs.";
    } else {
        $user = $utilisateurs->authentifier($email, $motDePasse);
        if ($user) {
            Auth::login($user);
            header('Location: dashboard.php');
            exit;
        } else {
            $errors[] = "Email ou mot de passe incorrect, ou compte inactif.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion - PlagiaTrack</title>
</head>
<body>
    <h1>Connexion</h1>
    <?php if ($errors): ?>
        <ul style="color:red;">
            <?php foreach ($errors as $error): ?>
                <li><?=htmlspecialchars($error)?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <form method="post" action="">
        <label>Email : <input type="email" name="email" required></label><br>
        <label>Mot de passe : <input type="password" name="mot_de_passe" required></label><br>
        <button type="submit">Se connecter</button>
    </form>
</body>
</html>
