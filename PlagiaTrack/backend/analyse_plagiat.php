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
$resultatAnalyseRaw = null;
$resultatAnalyseData = null;
$globalSuspicionIA = null;

// Scan reports_pdf directory for PDF reports
$reportsPdfDir = __DIR__ . '/reports_pdf/';
$pdfReports = [];
if (is_dir($reportsPdfDir)) {
    $allFiles = scandir($reportsPdfDir);
    foreach ($allFiles as $file) {
        if (is_file($reportsPdfDir . $file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf') {
            $pdfReports[] = $file;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documents = $_POST['documents'] ?? [];
    $options = $_POST['options'] ?? [];

    if (empty($documents)) {
        $errors[] = "Veuillez sélectionner au moins un document pour l'analyse.";
    } else {
        // Préparer la liste des fichiers à analyser
        $fichiersAAnalyser = [];

        // Ajouter les documents sélectionnés
        foreach ($documents as $docId) {
            $stmt = $pdo->prepare("SELECT chemin FROM documents WHERE id = ? AND utilisateur_id = ?");
            $stmt->execute([$docId, $user['id']]);
            $doc = $stmt->fetch();
            if ($doc) {
                $fichiersAAnalyser[] = $doc['chemin'];
            }
        }

        // Supprimer les doublons dans la liste des fichiers à analyser
        $fichiersAAnalyser = array_unique($fichiersAAnalyser);

        if (count($fichiersAAnalyser) < 1) {
            $errors[] = "Il faut au moins un document pour effectuer une analyse.";
        } else {
            // Construire la commande d'analyse
            $cmd_parts = array_merge(['python', 'python/analyse_plagiat.py'], $fichiersAAnalyser);

            // Ajouter options de détection selon sélection utilisateur
            if (in_array('detection_ia', $options)) {
                $cmd_parts[] = '--detect_ia';
            }
            if (in_array('comparaison_en_ligne', $options)) {
                $cmd_parts[] = '--comparaison_en_ligne';
            }
            if (in_array('analyse_nlp', $options)) {
                $cmd_parts[] = '--analyse_nlp';
            }

            $cmd = implode(' ', array_map('escapeshellarg', $cmd_parts));

            // Exécuter la commande et capturer stdout et stderr
            $descriptorspec = [
                1 => ['pipe', 'w'], // stdout
                2 => ['pipe', 'w']  // stderr
            ];
            $process = proc_open($cmd, $descriptorspec, $pipes);
            if (is_resource($process)) {
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $error_output = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                $return_var = proc_close($process);

                if ($return_var === 0) {
                    $resultatAnalyseRaw = $output;
                    $success = "Analyse terminée avec succès.";

                    // Tenter de décoder le JSON complet
                    $decoded = json_decode($output, true);
                    if ($decoded !== null && isset($decoded['sections_similaires'])) {
                        $resultatAnalyseData = $decoded['sections_similaires'];
                        $globalSuspicionIA = $decoded['indice_suspicion_IA'] ?? null;
                    } else {
                        $resultatAnalyseData = null;
                    }
                } else {
                    $errors[] = "Erreur lors de l'exécution de l'analyse Python : " . htmlspecialchars($error_output);
                }
            } else {
                $errors[] = "Impossible de lancer le processus d'analyse Python.";
            }
        }
    }
}

// Récupérer les groupes de documents de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM groupes_documents WHERE createur_id = ? ORDER BY date_creation DESC");
$stmt->execute([$user['id']]);
$groupes = $stmt->fetchAll();

// Pour chaque groupe, récupérer les documents associés
foreach ($groupes as &$groupe) {
    $stmt = $pdo->prepare("SELECT id, nom_fichier FROM documents WHERE groupe_id = ? AND utilisateur_id = ? ORDER BY nom_fichier ASC");
    $stmt->execute([$groupe['id'], $user['id']]);
    $groupe['documents'] = $stmt->fetchAll();
}
unset($groupe);

// Récupérer tous les documents de l'utilisateur pour le tableau global
$stmt = $pdo->prepare("SELECT id, nom_fichier FROM documents WHERE utilisateur_id = ? ORDER BY nom_fichier ASC");
$stmt->execute([$user['id']]);
$tousDocuments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Analyse de plagiat - PlagiaTrack</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 1em;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 0.5em;
            text-align: left;
        }
        th {
            background-color: #eee;
        }
        input[type="text"] {
            margin-bottom: 0.5em;
            padding: 0.3em;
            width: 100%;
            box-sizing: border-box;
        }
        .btn {
            padding: 10px 15px;
            background-color: #007BFF;
            border: none;
            color: white;
            cursor: pointer;
            border-radius: 5px;
            margin: 10px 0;
        }
        .close {
            background: red;
            float: right;
        }
        .option-description {
            font-size: 0.9em;
            color: #555;
            margin-left: 20px;
            margin-bottom: 10px;
        }
        .accordion {
            background-color: #eee;
            cursor: pointer;
            padding: 10px;
            width: 100%;
            border: none;
            text-align: left;
            outline: none;
            font-size: 1em;
            transition: background-color 0.2s ease;
            margin-top: 5px;
            border-radius: 5px;
        }
        .accordion.active, .accordion:hover {
            background-color: #ccc;
        }
        .panel {
            padding: 0 10px;
            display: none;
            background-color: white;
            overflow: hidden;
            border: 1px solid #ccc;
            border-top: none;
            border-radius: 0 0 5px 5px;
            margin-bottom: 10px;
        }
        .panel table {
            width: 100%;
            border: none;
        }
        .panel th, .panel td {
            border: none;
            padding: 5px;
        }
        #allDocumentsTable {
            margin-top: 2em;
        }
        #toggleViewBtn {
            margin-bottom: 1em;
        }
        #resultContainer {
            background-color: #f5f0e6;
            border: 1px solid #d2c9b8;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
        }
        #resultTable {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        #resultTable th, #resultTable td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        #resultTable th {
            background-color: #ddd;
        }
        #pdfReportsTable {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }
        #pdfReportsTable th, #pdfReportsTable td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        #pdfReportsTable th {
            background-color: #ddd;
        }
    </style>
</head>
<body>
    <h1>Analyse de plagiat</h1>
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

    <form method="post" action="" id="analyseForm">
        <button type="button" id="toggleViewBtn" class="btn">Afficher le tableau complet</button>

        <div id="groupedDocumentsSection">
            <h2>Documents par groupes</h2>
            <input type="text" id="searchDocuments" placeholder="Rechercher des documents..." onkeyup="filterDocuments()">

            <?php foreach ($groupes as $groupe): ?>
                <button type="button" class="accordion"><?=htmlspecialchars($groupe['nom'])?></button>
                <div class="panel">
                    <?php if (!empty($groupe['documents'])): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th><input type="checkbox" class="selectAllGroup" data-group-id="<?=htmlspecialchars($groupe['id'])?>"></th>
                                    <th>Nom du document</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupe['documents'] as $doc): ?>
                                <tr>
                                    <td><input type="checkbox" name="documents[]" value="<?=htmlspecialchars($doc['id'])?>" class="docCheckbox group-<?=htmlspecialchars($groupe['id'])?>"></td>
                                    <td><?=htmlspecialchars($doc['nom_fichier'])?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Aucun document dans ce groupe.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="allDocumentsSection" style="display:none;">
            <h2>Tous les documents</h2>
            <table id="allDocumentsTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllDocuments"></th>
                        <th>Nom du document</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tousDocuments as $doc): ?>
                    <tr>
                        <td><input type="checkbox" name="documents[]" value="<?=htmlspecialchars($doc['id'])?>" class="docCheckbox"></td>
                        <td><?=htmlspecialchars($doc['nom_fichier'])?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button type="submit" class="btn">Lancer l'analyse</button>
    </form>

    <?php if ($resultatAnalyseData): ?>
        <div id="resultContainer">
            <h2>Résultat de l'analyse</h2>
            <table id="resultTable">
                <thead>
                    <tr>
                        <th>Document 1</th>
                        <th>Document 2</th>
                        <th>Taux de similarité (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($resultatAnalyseData as $item) {
                        if (isset($item['document1'], $item['document2'], $item['score_similarite'])) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars(basename($item['document1'])) . '</td>';
                            echo '<td>' . htmlspecialchars(basename($item['document2'])) . '</td>';
                            echo '<td>' . htmlspecialchars(round($item['score_similarite'] * 100, 2)) . '</td>';
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
            <?php if ($globalSuspicionIA !== null): ?>
                <p>Indice global de suspicion IA : <?=htmlspecialchars(round($globalSuspicionIA, 2))?>%</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($pdfReports)): ?>
        <div id="pdfReportsContainer">
            <h2>Rapports PDF générés</h2>
            <table id="pdfReportsTable">
                <thead>
                    <tr>
                        <th>Nom du fichier</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pdfReports as $pdfFile): ?>
                        <tr>
                            <td><?=htmlspecialchars($pdfFile)?></td>
                            <td>
                                <a href="reports_pdf/<?=rawurlencode($pdfFile)?>" target="_blank">Voir</a> |
                                <a href="reports_pdf/<?=rawurlencode($pdfFile)?>" download>Télécharger</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p><a href="dashboard.php">Retour au tableau de bord</a></p>

    <script>
        // Accordion functionality
        const accordions = document.getElementsByClassName("accordion");
        for (let i = 0; i < accordions.length; i++) {
            accordions[i].addEventListener("click", function() {
                this.classList.toggle("active");
                const panel = this.nextElementSibling;
                if (panel.style.display === "block") {
                    panel.style.display = "none";
                } else {
                    panel.style.display = "block";
                }
            });
        }

        // Select all documents in a group
        const selectAllGroupCheckboxes = document.querySelectorAll('.selectAllGroup');
        selectAllGroupCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const groupId = this.getAttribute('data-group-id');
                const groupCheckboxes = document.querySelectorAll('.group-' + groupId);
                groupCheckboxes.forEach(cb => cb.checked = this.checked);
            });
        });

        // Select all documents in the all documents table
        const selectAllDocumentsCheckbox = document.getElementById('selectAllDocuments');
        selectAllDocumentsCheckbox.addEventListener('change', function() {
            const allDocCheckboxes = document.querySelectorAll('#allDocumentsTable .docCheckbox');
            allDocCheckboxes.forEach(cb => cb.checked = this.checked);
        });

        // Filter documents by search input (for both grouped and all documents)
        function filterDocuments() {
            const filter = document.getElementById('searchDocuments').value.toLowerCase();

            // Filter grouped documents
            const panels = document.getElementsByClassName('panel');
            for (let i = 0; i < panels.length; i++) {
                const rows = panels[i].getElementsByTagName('tbody')[0].rows;
                let anyVisible = false;
                for (let j = 0; j < rows.length; j++) {
                    const nameCell = rows[j].cells[1];
                    const text = nameCell.textContent.toLowerCase();
                    if (text.includes(filter)) {
                        rows[j].style.display = '';
                        anyVisible = true;
                    } else {
                        rows[j].style.display = 'none';
                    }
                }
                // Show or hide the entire panel based on if any document is visible
                panels[i].style.display = anyVisible ? 'block' : 'none';
                // Also toggle accordion active class accordingly
                const accordion = panels[i].previousElementSibling;
                if (anyVisible) {
                    accordion.classList.add('active');
                } else {
                    accordion.classList.remove('active');
                }
            }

            // Filter all documents table
            const allDocRows = document.querySelectorAll('#allDocumentsTable tbody tr');
            allDocRows.forEach(row => {
                const nameCell = row.cells[1];
                const text = nameCell.textContent.toLowerCase();
                if (text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Toggle between grouped and all documents views
        const toggleViewBtn = document.getElementById('toggleViewBtn');
        const groupedSection = document.getElementById('groupedDocumentsSection');
        const allDocumentsSection = document.getElementById('allDocumentsSection');

        toggleViewBtn.addEventListener('click', () => {
            if (groupedSection.style.display === 'none') {
                groupedSection.style.display = 'block';
                allDocumentsSection.style.display = 'none';
                toggleViewBtn.textContent = 'Afficher le tableau complet';
            } else {
                groupedSection.style.display = 'none';
                allDocumentsSection.style.display = 'block';
                toggleViewBtn.textContent = 'Afficher la vue par groupes';
            }
        });
    </script>
</body>
</html>
