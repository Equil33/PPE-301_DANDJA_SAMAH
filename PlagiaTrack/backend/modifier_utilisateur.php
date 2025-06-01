<?php
require_once 'auth.php';
require_once 'utilisateurs.php';

if (!Auth::estConnecte()) {
    header('Location: login.php');
    exit;
}

$user = Auth::getUser();
$utilisateurs = new Utilisateurs($pdo);

// Vérifier que l'utilisateur est administrateur ou super admin
if (!in_array($user['role_id'], [1, 2])) { // 1 = super_admin, 2 = administrateur
    echo "Accès refusé.";
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: gestion_utilisateurs.php');
    exit;
}

$utilisateur = $utilisateurs->getUtilisateurById($id);
if (!$utilisateur) {
    echo "Utilisateur non trouvé.";
    exit;
}

$stmt = $pdo->query("SELECT * FROM roles ORDER BY id ASC");
$liste_roles = $stmt->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role_id = intval($_POST['role_id'] ?? 0);
    $statut = $_POST['statut'] ?? 'actif';

    if ($nom && $prenom && $email && $role_id && $statut) {
        $utilisateurs->modifierUtilisateur($id, $nom, $prenom, $email, $role_id, $statut);
        header('Location: gestion_utilisateurs.php');
        exit;
    } else {
        $errors[] = "Tous les champs sont obligatoires.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier utilisateur - PlagiaTrack</title>
</head>
<body>
    <h1>Modifier utilisateur</h1>
    <?php if ($errors): ?>
        <ul style="color:red;">
            <?php foreach ($errors as $error): ?>
                <li><?=htmlspecialchars($error)?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <form method="post" action="">
        <label>Nom : <input type="text" name="nom" value="<?=htmlspecialchars($utilisateur['nom'])?>" required></label><br>
        <label>Prénom : <input type="text" name="prenom" value="<?=htmlspecialchars($utilisateur['prenom'])?>" required></label><br>
        <label>Email : <input type="email" name="email" value="<?=htmlspecialchars($utilisateur['email'])?>" required></label><br>
        <label>Rôle :
            <select name="role_id" required>
                <?php foreach ($liste_roles as $role): ?>
                    <option value="<?=htmlspecialchars($role['id'])?>" <?=($role['id'] == $utilisateur['role_id']) ? 'selected' : ''?>><?=htmlspecialchars($role['nom'])?></option>
                <?php endforeach; ?>
            </select>
        </label><br>
        <label>Statut :
            <select name="statut" required>
                <option value="actif" <?=($utilisateur['statut'] == 'actif') ? 'selected' : ''?>>Actif</option>
                <option value="inactif" <?=($utilisateur['statut'] == 'inactif') ? 'selected' : ''?>>Inactif</option>
                <option value="sanctionne" <?=($utilisateur['statut'] == 'sanctionne') ? 'selected' : ''?>>Sanctionné</option>
            </select>
        </label><br>
        <button type="submit">Modifier</button>
    </form>
    <p><a href="gestion_utilisateurs.php">Retour à la gestion des utilisateurs</a></p>
</body>
</html>
