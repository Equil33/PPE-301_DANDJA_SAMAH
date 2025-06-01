<?php
require_once 'config.php';
require_once 'auth.php';

if (!Auth::estConnecte()) {
    header('Location: login.php');
    exit;
}

$user = Auth::getUser();
$role_id = $user['role_id'];

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo "ID du document manquant.";
    exit;
}

$doc_id = intval($_GET['id']);

// Récupérer le document
$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$doc_id]);
$document = $stmt->fetch();

if (!$document) {
    http_response_code(404);
    echo "Document non trouvé.";
    exit;
}

// Vérifier les permissions
$peutSupprimer = false;
if (in_array($role_id, [1, 2])) { // super_admin, admin
    $peutSupprimer = true;
} elseif (in_array($role_id, [3, 4]) && $document['utilisateur_id'] == $user['id']) { // prof, etudiant
    $peutSupprimer = true;
}

if (!$peutSupprimer) {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}

// Supprimer le fichier physique
if (file_exists($document['chemin'])) {
    unlink($document['chemin']);
}

// Supprimer l'entrée en base
$stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
$stmt->execute([$doc_id]);

// Redirection vers gestion_documents.php avec message
header('Location: gestion_documents.php?msg=Document supprimé avec succès');
exit;
?>
