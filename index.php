<?php
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $agent_code_input = $_POST['agent_code'];
    $password_input   = $_POST['password'];

    // --- 1. CHARGEMENT CONFIG ---
    if (!file_exists('config.php')) { 
        die("Erreur critique : config.php manquant."); 
    }
    require_once 'config.php';

    // --- 2. CONNEXION BDD (CORRECTION SSL) ---
    // On utilise mysqli_init pour pouvoir passer des options si besoin, 
    // mais on retire l'exigence SSL pour éviter l'erreur fatale.
    $conn = mysqli_init();

    // On désactive le SSL ici car ton serveur XAMPP n'est pas configuré pour.
    // Si tu veux réactiver le SSL plus tard, il faudra configurer MySQL d'abord.
    $is_connected = @mysqli_real_connect($conn, DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    if ($is_connected) {
        // --- 3. SÉCURISATION DES ENTRÉES ---
        $agent_safe = mysqli_real_escape_string($conn, $agent_code_input);
        
        // --- 4. REQUÊTE D'AUTHENTIFICATION ---
        // On cherche l'utilisateur par son identifiant unique
        $sql = "SELECT * FROM agents WHERE username = '$agent_safe'";
        $result = mysqli_query($conn, $sql);

        if ($row = mysqli_fetch_assoc($result)) {
            // --- 5. VÉRIFICATION DU MOT DE PASSE HACHÉ ---
            if (password_verify($password_input, $row['password'])) {
                
                // SUCCÈS : Création de la session
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
        mysqli_close($conn);
    } else {
        // En cas d'erreur de connexion (IP, identifiants BDD faux, etc.)
        $error = "⚠️ ERREUR SYSTÈME : Connexion BDD impossible.";
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