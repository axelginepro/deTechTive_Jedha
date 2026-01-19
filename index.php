<?php
session_start();

$error = "";

// --- CONFIG BACKDOOR (Reste inchangé pour ton exercice) ---
$backup_user = "test";
$backup_pass = "test"; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $agent_code_input = $_POST['agent_code'];
    $password_input   = $_POST['password'];

    // --- A. BACKDOOR ---
    if ($agent_code_input === $backup_user && $password_input === $backup_pass) {
        $_SESSION['agent_id'] = 999;
        $_SESSION['agent_name'] = "Agent TEST (Mode Secours)";
        header("Location: dashboard.php");
        exit();
    } 
    
    // --- B. CONNEXION SÉCURISÉE AVEC SSL ---
    else {
        if (!file_exists('config.php')) { die("Erreur config."); }
        require_once 'config.php';

        // --- DÉBUT MODIFICATION SSL ---
        $conn = mysqli_init();

        // Le chemin exact trouvé dans ta capture
        mysqli_ssl_set($conn, NULL, NULL, "C:/webapp/deTechTive_Jedha/ca-cert.pem", NULL, NULL);

        // Désactiver la vérification stricte (utile en labo local)
        mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);

        // Connexion
        $is_connected = @mysqli_real_connect($conn, DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        // --- FIN MODIFICATION SSL ---

        if ($is_connected) {
            $agent_safe = mysqli_real_escape_string($conn, $agent_code_input);
            
            // 1. ON CHERCHE L'UTILISATEUR PAR SON NOM SEULEMENT
            $sql = "SELECT * FROM agents WHERE username = '$agent_safe'";
            $result = mysqli_query($conn, $sql);

            if ($row = mysqli_fetch_assoc($result)) {
                // 2. VÉRIFICATION DU HASH
                if (password_verify($password_input, $row['password'])) {
                    
                    // SUCCÈS : On connecte
                    $_SESSION['agent_id'] = $row['id'];
                    $_SESSION['agent_name'] = isset($row['agent_name']) ? $row['agent_name'] : $row['username'];
                    header("Location: dashboard.php");
                    exit();

                } else {
                    $error = "⛔ ACCÈS REFUSÉ : Identifiants invalides.";
                }
            } else {
                $error = "⛔ ACCÈS REFUSÉ : Identifiants invalides.";
            }
        } else {
            // Affiche l'erreur système si la connexion échoue (ex: certificat introuvable)
            $error = "⚠️ ERREUR SYSTÈME : Connexion BDD impossible (" . mysqli_connect_error() . ")";
        }
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