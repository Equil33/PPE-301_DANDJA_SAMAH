<?php
require_once 'auth.php';

if (!Auth::estConnecte()) {
    header('Location: login.php');
    exit;
}

$user = Auth::getUser();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: rapports.php');
    exit;
}

// Récupérer le rapport
$stmt = $pdo->prepare("SELECT * FROM rapports_plagiat WHERE id = ?");
$stmt->execute([$id]);
$rapport = $stmt->fetch();

if (!$rapport) {
    echo "Rapport non trouvé.";
    exit;
}

// Charger le contenu JSON du rapport
$chemin_rapport = $rapport['chemin_rapport'];
if (!file_exists($chemin_rapport)) {
    echo "Fichier de rapport introuvable.";
    exit;
}

$contenu_json = file_get_contents($chemin_rapport);
$donnees_rapport = json_decode($contenu_json, true);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Visualisation du rapport - PlagiaTrack</title>
    <style>
        .container {
            display: flex;
            flex-direction: row;
            gap: 20px;
        }
        .texte {
            width: 45%;
            border: 1px solid #ccc;
            padding: 10px;
            overflow-y: scroll;
            height: 400px;
            white-space: pre-wrap;
            font-family: monospace;
        }
        .section-plagiee {
            background-color: #ffcccc;
        }
    </style>
</head>
<body>
    <h1>Visualisation du rapport de plagiat</h1>
    <p>Document analysé : <?=htmlspecialchars($rapport['document_id'])?></p>
    <p>Pourcentage de plagiat : <?=htmlspecialchars($rapport['pourcentage_plagiat'])?>%</p>

    <?php if (empty($donnees_rapport) || !isset($donnees_rapport['sections_similaires'])): ?>
        <p>Le rapport est vide ou mal formaté.</p>
    <?php else: ?>
        <?php foreach ($donnees_rapport['sections_similaires'] as $item): ?>
            <h2>Comparaison entre <?=htmlspecialchars($item['document1'])?> et <?=htmlspecialchars($item['document2'])?></h2>
            <?php if (isset($item['sections_similaires'])): ?>
                <?php foreach ($item['sections_similaires'] as $section): ?>
                    <h3>Section similaire (similarité : <?=htmlspecialchars(round($section['similarite'] * 100, 2))?>%)</h3>
                    <div class="container">
                        <div class="texte">
                            <h4>Texte document 1</h4>
                            <pre><?=htmlspecialchars($section['document_1'])?></pre>
                        </div>
                        <div class="texte">
                            <h4>Texte document 2</h4>
                            <pre><?=htmlspecialchars($section['document_2'])?></pre>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <p><a href="rapports.php">Retour aux rapports</a></p>
</body>
</html>
