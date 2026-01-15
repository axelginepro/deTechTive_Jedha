<?php
session_start();

// --- CONFIGURATION TEST EN DUR ---
$valid_agent_code = "test";
$valid_password   = "test123";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $agent_code_input = $_POST['agent_code'];
    $password_input   = $_POST['password'];

    if ($agent_code_input === $valid_agent_code && $password_input === $valid_password) {
        // On stocke les informations pour le Dashboard
        $_SESSION['agent_id'] = 999;
        $_SESSION['agent_name'] = "Agent de Test"; // C'est ce nom qui s'affichera
        
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "IDENTIFIANTS INCORRECTS - ACCES REFUSE";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>TERMINAL D'IDENTIFICATION</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header-flex">
            <h1>AUTHENTIFICATION REQUISE</h1>
        </div>

        <?php if($error): ?>
            <div style="color: var(--accent-color); border: 1px solid var(--accent-color); padding: 10px; margin-bottom: 20px; text-align: center; font-weight: bold;">
                [!] <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <p style="font-size: 0.8rem; color: #555; margin-bottom: 15px;">LOGIN : test / PASS : test123</p>
            
            <label>CODE AGENT :</label>
            <input type="text" name="agent_code" placeholder="Identifiant..." required>

            <label>MOT DE PASSE :</label>
            <input type="password" name="password" placeholder="Code secret..." required>

            <div style="text-align: right; margin-top: 20px;">
                <button type="submit" name="login">S'AUTHENTIFIER</button>
            </div>
        </form>
    </div>
</body>
</html>