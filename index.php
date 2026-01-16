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
        // CORRECTION 1 : On pointe vers l'IP de la VM DATABASE
        $host = "192.168.10.18"; 
        
        // CORRECTION 2 : On utilise l'utilisateur 'admin' configuré pour l'accès distant
        $user_db = "admin";
        $pass_db = "admin";
        $db_name = "detechtive_db";

        // On tente la connexion (avec un @ pour masquer les erreurs techniques brutes)
        $conn = @mysqli_connect($host, $user_db, $pass_db, $db_name);

        if ($conn) {
            $agent_safe = mysqli_real_escape_string($conn, $agent_code_input);
            
            // CORRECTION 3 : On cherche dans la table 'agents' (et plus 'users')
            $sql = "SELECT * FROM agents WHERE username = '$agent_safe' AND password = '$password_input'";
            
            $result = mysqli_query($conn, $sql);

            if (mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $_SESSION['agent_id'] = $row['id'];
                
                // On récupère 'agent_name' pour un affichage plus joli sur le dashboard
                $_SESSION['agent_name'] = isset($row['agent_name']) ? $row['agent_name'] : $row['username'];
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Identifiants inconnus.";
            }
        } else {
            // Affiche l'erreur précise pour t'aider à débugger le réseau
            $error = "Erreur de connexion à la BDD ($host) : " . mysqli_connect_error();
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
            <div style="color: red; border: 1px solid red; padding: 10px; margin-bottom: 20px; text-align: center; font-weight: bold; background-color: #ffe6e6;">
                [!] <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <p style="font-size: 0.8rem; color: #555; margin-bottom: 15px; text-align:center;">LOGIN : test / PASS : test123</p>
            
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
        <p>v1.0</p>
    </footer>

</body>
</html>