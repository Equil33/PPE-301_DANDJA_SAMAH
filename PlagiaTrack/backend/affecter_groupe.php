<?php
require_once 'config.php';
require_once 'auth.php';

if (!Auth::estConnecte()) {
    header('Location: login.php');
    exit;
}

$user = Auth::getUser();
$role_id = $user['role_id'];

if (!in_array($role_id, [1, 2, 3, 4])) {
    echo "Accès refusé.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupe_id = intval($_POST['groupe_id'] ?? 0);
    $document_ids = $_POST['document_ids'] ?? [];

    if ($groupe_id <= 0 || empty($document_ids)) {
        echo "Groupe ou documents non sélectionnés.";
        exit;
    }

    // Vérifier que le groupe appartient à l'utilisateur (créateur)
    $stmt = $pdo->prepare("SELECT createur_id FROM groupes_documents WHERE id = ?");
    $stmt->execute([$groupe_id]);
    $groupe = $stmt->fetch();

    if (!$groupe || $groupe['createur_id'] != $user['id']) {
        echo "Groupe invalide ou accès refusé.";
        exit;
    }

    // Mettre à jour les documents sélectionnés pour les affecter au groupe
    $placeholders = implode(',', array_fill(0, count($document_ids), '?'));
    $params = $document_ids;
    $params[] = $groupe_id;

    $sql = "UPDATE documents SET groupe_id = ? WHERE id IN ($placeholders)";
    // Note: groupe_id param should be first, so reorder params
    $params = array_merge([$groupe_id], $document_ids);

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        header('Location: gestion_documents.php?success=Documents affectés au groupe avec succès');
        exit;
    } else {
        echo "Erreur lors de l'affectation des documents au groupe.";
        exit;
    }
} else {
    header('Location: gestion_documents.php');
    exit;
}
?>
