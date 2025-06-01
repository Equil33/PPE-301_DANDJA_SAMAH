<?php
require_once 'config.php';
require_once 'auth.php';

if (!Auth::estConnecte()) {
    header('Location: login.php');
    exit;
}

$user = Auth::getUser();

$errors = [];
$success = '';

// Activer/désactiver notifications
if (isset($_GET['action']) && in_array($_GET['action'], ['activer', 'desactiver'])) {
    $etat = $_GET['action'] === 'activer' ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE utilisateurs SET notifications_active = ? WHERE id = ?");
    $stmt->execute([$etat, $user['id']]);
    $success = "Paramètre de notification mis à jour.";
}

// Marquer notification comme lue
if (isset($_GET['marquer_lu'])) {
    $idNotif = intval($_GET['marquer_lu']);
    $stmt = $pdo->prepare("UPDATE notifications SET statut = 'lu' WHERE id = ? AND utilisateur_id = ?");
    $stmt->execute([$idNotif, $user['id']]);
    $success = "Notification marquée comme lue.";
}

// Récupérer les notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE utilisateur_id = ? ORDER BY date_envoi DESC");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

// Récupérer l'état des notifications de l'utilisateur
$stmt = $pdo->prepare("SELECT notifications_active FROM utilisateurs WHERE id = ?");
$stmt->execute([$user['id']]);
$etatNotif = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Notifications - PlagiaTrack</title>
</head>
<body>
    <h1>Notifications</h1>
    <?php if ($errors): ?>
        <ul style="color:red;">
            <?php foreach ($errors as $error): ?>
                <li><?=htmlspecialchars($error)?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="color:green;"><?=htmlspecialchars($success)?></p>
    <?php endif; ?>

    <p>Notifications : 
        <?php if ($etatNotif): ?>
            Activées (<a href="?action=desactiver">Désactiver</a>)
        <?php else: ?>
            Désactivées (<a href="?action=activer">Activer</a>)
        <?php endif; ?>
    </p>

    <h2>Liste des notifications</h2>
    <ul>
        <?php foreach ($notifications as $notif): ?>
            <li style="font-weight: <?= $notif['statut'] === 'non_lu' ? 'bold' : 'normal' ?>">
                <?=htmlspecialchars($notif['message'])?> - <?=htmlspecialchars($notif['date_envoi'])?>
                <?php if ($notif['statut'] === 'non_lu'): ?>
                    <a href="?marquer_lu=<?=htmlspecialchars($notif['id'])?>">Marquer comme lu</a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <p><a href="dashboard.php">Retour au tableau de bord</a></p>
</body>
</html>
