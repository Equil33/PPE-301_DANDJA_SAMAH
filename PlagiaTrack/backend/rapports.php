<?php
require_once 'config.php';
require_once 'auth.php';

if (!Auth::estConnecte()) {
    header('Location: login.php');
    exit;
}

$user = Auth::getUser();

// Vérifier les permissions (professeur, admin, super admin)
if (!in_array($user['role_id'], [1, 2, 3])) {
    echo "Accès refusé.";
    exit;
}

$errors = [];
$success = '';

$reportsDir = __DIR__ . '/reports/';

// Suppression d'un rapport PDF
if (isset($_GET['action'], $_GET['file']) && $_GET['action'] === 'supprimer') {
    $file = basename($_GET['file']); // Sécuriser le nom de fichier
    $filePath = realpath($reportsDir . $file);

    if ($filePath && strpos($filePath, realpath($reportsDir)) === 0 && is_file($filePath)) {
        if (unlink($filePath)) {
            $success = "Le rapport PDF '$file' a été supprimé avec succès.";
        } else {
            $errors[] = "Impossible de supprimer le fichier '$file'.";
        }
    } else {
        $errors[] = "Fichier invalide ou non trouvé.";
    }
}

// Lecture des fichiers PDF dans le dossier reports
$pdfFiles = [];
if (is_dir($reportsDir)) {
    $files = scandir($reportsDir);
    foreach ($files as $f) {
        if (is_file($reportsDir . $f) && strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'pdf') {
            $pdfFiles[] = $f;
        }
    }
} else {
    $errors[] = "Le dossier des rapports PDF n'existe pas.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapports PDF de plagiat - PlagiaTrack</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 1em;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
        }
        a {
            color: #007BFF;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .btn-delete {
            color: red;
            cursor: pointer;
        }
        .message-success {
            color: green;
            margin-bottom: 1em;
        }
        .message-error {
            color: red;
            margin-bottom: 1em;
        }
    </style>
</head>
<body>
    <h1>Rapports PDF de plagiat</h1>

    <?php if ($success): ?>
        <div class="message-success"><?=htmlspecialchars($success)?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="message-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?=htmlspecialchars($error)?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($pdfFiles)): ?>
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
                <?php foreach ($pdfFiles as $pdfFile): ?>
                    <tr>
                        <td><?=htmlspecialchars($pdfFile)?></td>
                        <td>
                            <a href="reports/<?=rawurlencode($pdfFile)?>" target="_blank" rel="noopener noreferrer">Voir</a> |
                            <a href="reports/<?=rawurlencode($pdfFile)?>" download>Télécharger</a> |
                            <a href="?action=supprimer&amp;file=<?=rawurlencode($pdfFile)?>" class="btn-delete" onclick="return confirm('Confirmez-vous la suppression du rapport PDF ?')">Supprimer</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p><a href="dashboard.php">Retour au tableau de bord</a></p>
</body>
</html>
