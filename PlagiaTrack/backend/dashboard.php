<?php
require_once 'auth.php';

if (!Auth::estConnecte()) {
    header('Location: login.php');
    exit;
}

$user = Auth::getUser();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de bord - PlagiaTrack</title>
</head>
<body>
    <h1>Bienvenue, <?=htmlspecialchars($user['prenom'])?> <?=htmlspecialchars($user['nom'])?></h1>
    <p>Rôle : <?=htmlspecialchars($user['role_id'])?></p>
    <nav>
        <ul>
            <li><a href="gestion_utilisateurs.php">Gestion des utilisateurs</a></li>
            <li><a href="gestion_documents.php">Gestion des documents</a></li>
            <li><a href="analyse_plagiat.php">Analyse de plagiat</a></li>
            <li><a href="rapports.php">Rapports de plagiat</a></li>
            <li><a href="administration.php">Administration</a></li>
            <li><a href="logout.php">Déconnexion</a></li>
        </ul>
    </nav>
</body>
</html>
