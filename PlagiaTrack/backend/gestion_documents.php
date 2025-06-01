<?php
require_once 'config.php';
require_once 'auth.php';

if (!Auth::estConnecte()) {
    header('Location: login.php');
    exit;
}

$user = Auth::getUser();

$role_id = $user['role_id'];
$peutSoumettre = in_array($role_id, [1, 2, 3, 4]); // super_admin, admin, prof, etudiant

if (!$peutSoumettre) {
    echo "Accès refusé.";
    exit;
}

$errors = [];
$success = '';

// Gestion création groupe
if (isset($_POST['creer_groupe'])) {
    $nomGroupe = trim($_POST['nom_groupe'] ?? '');
    if ($nomGroupe === '') {
        $errors[] = "Le nom du groupe est obligatoire.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO groupes_documents (nom, createur_id, date_creation) VALUES (?, ?, NOW())");
        $stmt->execute([$nomGroupe, $user['id']]);
        $success = "Groupe créé avec succès.";
    }
}

// Gestion soumission documents
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['documents'])) {
    $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];
    $uploadDir = __DIR__ . '/../documents/' . $user['id'] . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $professeurDestinataire = $_POST['professeur_destinataire'] ?? 'tous';

    foreach ($_FILES['documents']['tmp_name'] as $key => $tmpName) {
        $fileName = basename($_FILES['documents']['name'][$key]);
        $fileType = $_FILES['documents']['type'][$key];
        $fileSize = $_FILES['documents']['size'][$key];

        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Le fichier $fileName n'est pas dans un format supporté.";
            continue;
        }

        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($tmpName, $targetFile)) {
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $typeFichier = strtolower($ext);
            if (!in_array($typeFichier, ['pdf', 'docx', 'txt'])) {
                $typeFichier = 'txt';
            }

            $stmt = $pdo->prepare("INSERT INTO documents (utilisateur_id, nom_fichier, chemin, type_fichier, statut, est_enregistre, destinataire_professeur) VALUES (?, ?, ?, ?, 'en_attente', 0, ?)");
            $stmt->execute([$user['id'], $fileName, $targetFile, $typeFichier, $professeurDestinataire]);
            $success = "Documents soumis avec succès.";
        } else {
            $errors[] = "Erreur lors du téléchargement du fichier $fileName.";
        }
    }
}

// Gestion recherche et affichage documents
$searchNom = $_GET['search_nom'] ?? '';
$searchType = $_GET['search_type'] ?? '';
$searchDateDebut = $_GET['search_date_debut'] ?? '';
$searchDateFin = $_GET['search_date_fin'] ?? '';

// Construction de la requête selon rôle
if (in_array($role_id, [1, 2])) {
    // admin et super_admin voient tous les documents
    $query = "SELECT d.*, u.nom AS nom_utilisateur, u.prenom AS prenom_utilisateur FROM documents d JOIN utilisateurs u ON d.utilisateur_id = u.id WHERE 1=1";
    $params = [];
} elseif ($role_id == 3) {
    // prof voit ses documents et ceux destinés à lui ou à tous
    $query = "SELECT d.*, u.nom AS nom_utilisateur, u.prenom AS prenom_utilisateur FROM documents d JOIN utilisateurs u ON d.utilisateur_id = u.id WHERE (d.destinataire_professeur = ? OR d.destinataire_professeur = 'tous') AND (u.id = ? OR d.destinataire_professeur = 'tous')";
    $params = [$user['id'], $user['id']];
} else {
    // eleve voit ses documents uniquement
    $query = "SELECT d.*, u.nom AS nom_utilisateur, u.prenom AS prenom_utilisateur FROM documents d JOIN utilisateurs u ON d.utilisateur_id = u.id WHERE d.utilisateur_id = ?";
    $params = [$user['id']];
}

// Ajout filtres recherche
if ($searchNom !== '') {
    $query .= " AND d.nom_fichier LIKE ?";
    $params[] = "%$searchNom%";
}
if ($searchType !== '') {
    $query .= " AND d.type_fichier = ?";
    $params[] = $searchType;
}
if ($searchDateDebut !== '') {
    $query .= " AND d.date_soumission >= ?";
    $params[] = $searchDateDebut;
}
if ($searchDateFin !== '') {
    $query .= " AND d.date_soumission <= ?";
    $params[] = $searchDateFin;
}

$query .= " ORDER BY d.date_soumission DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$documents = $stmt->fetchAll();

// Récupérer la liste des professeurs pour sélection destinataire
$stmt = $pdo->query("SELECT id, nom, prenom FROM utilisateurs WHERE role_id = 3 ORDER BY nom ASC");
$professeurs = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des documents - PlagiaTrack</title>
</head>
<body>
    <h1>Gestion des documents</h1>
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

    <h2>Créer un groupe de documents</h2>
    <form method="post" action="">
        <label>Nom du groupe : <input type="text" name="nom_groupe" required></label>
        <button type="submit" name="creer_groupe">Créer</button>
    </form>

    <h2>Soumettre des documents</h2>
    <form method="post" action="" enctype="multipart/form-data">
        <label>Choisir le professeur destinataire :</label>
        <select name="professeur_destinataire" required>
            <option value="tous">Tous</option>
            <?php foreach ($professeurs as $prof): ?>
                <option value="<?=htmlspecialchars($prof['id'])?>"><?=htmlspecialchars($prof['nom'] . ' ' . $prof['prenom'])?></option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Choisir un ou plusieurs documents (formats .pdf, .docx, .txt) :</label><br>
        <input type="file" name="documents[]" multiple required><br>
        <button type="submit">Soumettre</button>
    </form>

    <h2>Rechercher des documents</h2>
    <form method="get" action="">
        <label>Nom du fichier : <input type="text" name="search_nom" value="<?=htmlspecialchars($searchNom)?>"></label>
        <label>Type de fichier :
            <select name="search_type">
                <option value="">Tous</option>
                <option value="pdf" <?= $searchType == 'pdf' ? 'selected' : '' ?>>PDF</option>
                <option value="docx" <?= $searchType == 'docx' ? 'selected' : '' ?>>DOCX</option>
                <option value="txt" <?= $searchType == 'txt' ? 'selected' : '' ?>>TXT</option>
            </select>
        </label>
        <label>Date début : <input type="date" name="search_date_debut" value="<?=htmlspecialchars($searchDateDebut)?>"></label>
        <label>Date fin : <input type="date" name="search_date_fin" value="<?=htmlspecialchars($searchDateFin)?>"></label>
        <button type="submit">Rechercher</button>
    </form>

    <h2>Documents soumis</h2>
    <form method="post" action="affecter_groupe.php">
        <button type="submit" name="affecter_groupe">Affecter au groupe sélectionné</button>
        <select name="groupe_id" required>
            <option value="">-- Sélectionner un groupe --</option>
            <?php
            // Récupérer les groupes pour l'utilisateur
            $stmt = $pdo->prepare("SELECT id, nom FROM groupes_documents WHERE createur_id = ? ORDER BY nom ASC");
            $stmt->execute([$user['id']]);
            $groupes = $stmt->fetchAll();
            foreach ($groupes as $groupe):
            ?>
                <option value="<?=htmlspecialchars($groupe['id'])?>"><?=htmlspecialchars($groupe['nom'])?></option>
            <?php endforeach; ?>
        </select>
        <br><br>
        <table border="1" cellpadding="5" cellspacing="0" id="documentsTable">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>Nom du fichier</th>
                    <th>Type</th>
                    <th>Date de soumission</th>
                    <th>Statut</th>
                    <th>Utilisateur</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><input type="checkbox" name="document_ids[]" value="<?=htmlspecialchars($doc['id'])?>"></td>
                        <td><?=htmlspecialchars($doc['nom_fichier'])?></td>
                        <td><?=htmlspecialchars($doc['type_fichier'])?></td>
                        <td><?=htmlspecialchars($doc['date_soumission'])?></td>
                        <td><?=htmlspecialchars($doc['statut'])?></td>
                        <td><?=htmlspecialchars($doc['nom_utilisateur'] . ' ' . $doc['prenom_utilisateur'])?></td>
                            <td>
                                <?php
                                $peutSupprimer = false;
                                $peutTelecharger = false;
                                if (in_array($role_id, [1, 2])) {
                                    $peutSupprimer = true;
                                    $peutTelecharger = true;
                                } elseif (($role_id == 3 || $role_id == 4) && $doc['utilisateur_id'] == $user['id']) {
                                    $peutSupprimer = true;
                                    $peutTelecharger = true;
                                }
                                if ($peutTelecharger):
                                ?>
                                    <a href="<?=htmlspecialchars($doc['chemin'])?>" download> Télécharger </a><br>
                                <?php endif; ?>
                                <?php if ($peutSupprimer): ?>
                                    <a href="supprimer_document.php?id=<?=htmlspecialchars($doc['id'])?>" onclick="return confirm('Supprimer ce document ?')">Supprimer</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>
    <script>
        document.getElementById('selectAll').addEventListener('change', function() {
            var checkboxes = document.querySelectorAll('#documentsTable tbody input[type="checkbox"]');
            for (var checkbox of checkboxes) {
                checkbox.checked = this.checked;
            }
        });
    </script>

    <p><a href="dashboard.php">Retour au tableau de bord</a></p>
    <p><a href="gestion_groupes.php">Gestion des groupes créés</a></p>
</body>
</html>
