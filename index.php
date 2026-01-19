<?php
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $agent_code_input = $_POST['agent_code'];
    $password_input   = $_POST['password'];

    // --- CONNEXION SÉCURISÉE (UNIQUE MÉTHODE) ---
    if (!file_exists('config.php')) { die("Erreur critique : Fichier de configuration manquant."); }
    require_once 'config.php';

    // Tentative de connexion à la BDD
    $conn = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    if ($conn) {
        // Protection contre les injections SQL basiques pour le username
        $agent_safe = mysqli_real_escape_string($conn, $agent_code_input);
        
        // 1. ON CHERCHE L'UTILISATEUR PAR SON NOM SEULEMENT
        $sql = "SELECT * FROM agents WHERE username = '$agent_safe'";
        $result = mysqli_query($conn, $sql);

        if ($row = mysqli_fetch_assoc($result)) {
            // 2. VÉRIFICATION DU HASH DU MOT DE PASSE
            // password_verify compare le texte clair avec le hash stocké en BDD
            if (password_verify($password_input, $row['password'])) {
                
                // SUCCÈS : On initialise la session
                $_SESSION['agent_id'] = $row['id'];
                // On utilise le nom complet si dispo, sinon le username
                $_SESSION['agent_name'] = isset($row['agent_name']) ? $row['agent_name'] : $row['username'];
                
                header("Location: dashboard.php");
                exit();

            } else {
                // Mauvais mot de passe
                $error = "⛔ ACCÈS REFUSÉ : Identifiants invalides.";
            }
        } else {
            // Utilisateur inconnu
            $error = "⛔ ACCÈS REFUSÉ : Identifiants invalides.";
        }
    } else {
        // Échec de la connexion au serveur MySQL
        $error = "⚠️ ERREUR SYSTÈME : Connexion à la base de données impossible.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TERMINAL D'IDENTIFICATION</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header-flex">
            <h1>AUTHENTIFICATION REQUISE</h1>
        </div>

        <?php if($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <label>CODE AGENT :</label>
            <input type="text" name="agent_code" placeholder="Identifiant..." required>
            <label>MOT DE PASSE :</label>
            <input type="password" name="password" placeholder="Code secret..." required>
            <div style="text-align: right; margin-top: 20px;">
                <button type="submit" name="login">S'AUTHENTIFIER</button>
            </div>
        </form>
    </div>
    <footer>
        <p>&copy; 2026 Detecthive Inc. Tous droits réservés.</p>
    </footer>
</body>
</html>