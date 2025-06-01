<?php
require_once 'config.php';
require_once 'auth.php';

if (!Auth::estConnecte()) {
    header('Location: login.php');
    exit;
}

$user = Auth::getUser();

$directory = __DIR__ . '/reports_pdf/';
$files = [];

if (is_dir($directory)) {
    $allFiles = scandir($directory);
    foreach ($allFiles as $file) {
        if (is_file($directory . $file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf') {
            $files[] = $file;
        }
    }
} else {
    $error = "Le dossier des rapports PDF n'existe pas.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapports PDF de plagiat - PlagiaTrack</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 1em;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 0.5em;
        }
        th {
            background-color: #eee;
        }
        a {
            color: #007BFF;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>Rapports PDF de plagiat</h1>
    <?php if (!empty($error)): ?>
        <p style="color:red;"><?=htmlspecialchars($error)?></p>
    <?php endif; ?>

    <?php if (empty($files)): ?>
        <p>Aucun rapport PDF disponible.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Nom du fichier</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td><?=htmlspecialchars($file)?></td>
                        <td>
                            <a href="reports_pdf/<?=rawurlencode($file)?>" target="_blank">Voir</a> |
                            <a href="reports_pdf/<?=rawurlencode($file)?>" download>Télécharger</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p><a href="dashboard.php">Retour au tableau de bord</a></p>
</body>
</html>
