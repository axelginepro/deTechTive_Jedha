<?php
// migrate_passwords.php
require_once 'config.php';

$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if (!$conn) { die("Erreur connexion : " . mysqli_connect_error()); }

echo "<h2>Début de la migration des mots de passe...</h2>";

// 1. On récupère tous les agents
$sql = "SELECT id, username, password FROM agents";
$result = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($result)) {
    $id = $row['id'];
    $clear_password = $row['password'];

    // Si le mot de passe ressemble déjà à un hash (commence par $2y$), on l'ignore
    if (substr($clear_password, 0, 4) === '$2y$') {
        echo "L'utilisateur <strong>" . $row['username'] . "</strong> est déjà hashé. On passe.<br>";
        continue;
    }

    // 2. On HASH le mot de passe
    $hashed_password = password_hash($clear_password, PASSWORD_DEFAULT);

    // 3. On met à jour la base de données
    $update_sql = "UPDATE agents SET password = '$hashed_password' WHERE id = $id";
    
    if (mysqli_query($conn, $update_sql)) {
        echo "✅ Utilisateur <strong>" . $row['username'] . "</strong> : Mot de passe hashé avec succès.<br>";
    } else {
        echo "❌ Erreur pour " . $row['username'] . " : " . mysqli_error($conn) . "<br>";
    }
}

echo "<h3>Migration terminée ! Supprimez ce fichier par sécurité.</h3>";
?>