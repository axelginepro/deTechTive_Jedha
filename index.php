<?php
session_start();

$error = "";

// --- 1. CONFIGURATION DU COMPTE DE SECOURS (BACKDOOR) ---
$backup_user = "test";
$backup_pass = "test";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $agent_code_input = $_POST['agent_code'];
    $password_input   = $_POST['password'];

    // --- A. VÉRIFICATION DU COMPTE TEST (Backdoor) ---
    if ($agent_code_input === $backup_user && $password_input === $backup_pass) {
        $_SESSION['agent_id'] = 999;
        $_SESSION['agent_name'] = "Agent TEST (Mode Secours)";
        header("Location: dashboard.php");
        exit();
    } 
    
    // --- B. SINON, ON TENTE LA CONNEXION À LA BASE DISTANTE ---
    else {
        // CHARGEMENT DE LA CONFIGURATION
        if (!file_exists('config.php')) {
            die("Erreur critique : Le fichier de configuration est manquant.");
        }
        require_once 'config.php';

        // Le @ masque les erreurs PHP brutes, on gère l'erreur nous-mêmes via $conn
        $conn = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

        if ($conn) {
            $agent_safe = mysqli_real_escape_string($conn, $agent_code_input);
            $sql = "SELECT * FROM agents WHERE username = '$agent_safe' AND password = '$password_input'";
            $result = mysqli_query($conn, $sql);

            if (mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $_SESSION['agent_id'] = $row['id'];
                $_SESSION['agent_name'] = isset($row['agent_name']) ? $row['agent_name'] : $row['username'];
                header("Location: dashboard.php");
                exit();
            } else {
                // Erreur stylisée
                $error = "⛔ ACCÈS REFUSÉ : Identifiants invalides.";
            }
        } else {
            // Erreur technique
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
            <div class="alert-error">
                <?php echo $error; ?>
            </div>
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
        <p>v1.4</p>
    </footer>

</body>
</html>