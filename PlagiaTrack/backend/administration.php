<?php
require_once 'config.php';
require_once 'auth.php';

if (!Auth::estConnecte()) {
    header('Location: login.php');
    exit;
}

$user = Auth::getUser();

// Vérifier que l'utilisateur est administrateur ou super admin
if (!in_array($user['role_id'], [1, 2])) {
    echo "Accès refusé.";
    exit;
}

$errors = [];
$success = '';

// Nettoyage des documents ou rapports obsolètes
if (isset($_POST['nettoyer_documents'])) {
    // Suppression des documents supprimés depuis plus de 30 jours (exemple)
    $stmt = $pdo->prepare("DELETE FROM documents WHERE statut = 'supprime' AND date_soumission < NOW() - INTERVAL 30 DAY");
    $stmt->execute();
    $success = "Documents obsolètes nettoyés.";
}

if (isset($_POST['nettoyer_rapports'])) {
    // Suppression des rapports archivés depuis plus de 90 jours (exemple)
    $stmt = $pdo->prepare("DELETE FROM rapports_plagiat WHERE statut = 'archive' AND date_analyse < NOW() - INTERVAL 90 DAY");
    $stmt->execute();
    $success = "Rapports obsolètes nettoyés.";
}

// Gestion des sanctions disciplinaires
if (isset($_POST['sanctionner'])) {
    $utilisateur_id = intval($_POST['utilisateur_id'] ?? 0);
    $type_sanction = trim($_POST['type_sanction'] ?? '');
    $raison = trim($_POST['raison'] ?? '');

    if ($utilisateur_id && $type_sanction && $raison) {
        $stmt = $pdo->prepare("INSERT INTO sanctions (utilisateur_id, type_sanction, raison) VALUES (?, ?, ?)");
        $stmt->execute([$utilisateur_id, $type_sanction, $raison]);
        $stmt = $pdo->prepare("UPDATE utilisateurs SET statut = 'sanctionne' WHERE id = ?");
        $stmt->execute([$utilisateur_id]);
        $success = "Sanction appliquée.";
    } else {
        $errors[] = "Tous les champs de sanction sont obligatoires.";
    }
}

// Récupérer la liste des utilisateurs pour sanctions
$stmt = $pdo->query("SELECT id, nom, prenom FROM utilisateurs ORDER BY nom ASC");
$utilisateurs = $stmt->fetchAll();

// Journalisation des actions critiques (audit)
$stmt = $pdo->query("SELECT ja.*, u.nom, u.prenom FROM journal_actions ja LEFT JOIN utilisateurs u ON ja.utilisateur_id = u.id ORDER BY ja.date_action DESC LIMIT 50");
$actions = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration - PlagiaTrack</title>
</head>
<body>
    <h1>Administration et supervision</h1>
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

    <h2>Nettoyage</h2>
    <form method="post" action="">
        <button type="submit" name="nettoyer_documents">Nettoyer documents obsolètes</button>
        <button type="submit" name="nettoyer_rapports">Nettoyer rapports obsolètes</button>
    </form>

    <h2>Sanctions disciplinaires</h2>
    <form method="post" action="">
        <label>Utilisateur :
            <select name="utilisateur_id" required>
                <option value="">-- Sélectionner --</option>
                <?php foreach ($utilisateurs as $u): ?>
                    <option value="<?=htmlspecialchars($u['id'])?>"><?=htmlspecialchars($u['nom'] . ' ' . $u['prenom'])?></option>
                <?php endforeach; ?>
            </select>
        </label><br>
        <label>Type de sanction : <input type="text" name="type_sanction" required></label><br>
        <label>Raison : <textarea name="raison" required></textarea></label><br>
        <button type="submit" name="sanctionner">Appliquer la sanction</button>
    </form>

    <h2>Journalisation des actions critiques (audit)</h2>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>Date</th><th>Utilisateur</th><th>Action</th><th>Cible</th><th>Détails</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($actions as $action): ?>
                <tr>
                    <td><?=htmlspecialchars($action['date_action'])?></td>
                    <td><?=htmlspecialchars($action['nom'] . ' ' . $action['prenom'])?></td>
                    <td><?=htmlspecialchars($action['action'])?></td>
                    <td><?=htmlspecialchars($action['cible'])?></td>
                    <td><?=htmlspecialchars($action['details'])?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p><a href="dashboard.php">Retour au tableau de bord</a></p>
</body>
</html>
