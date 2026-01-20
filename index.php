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

    // --- 2. CONNEXION BDD AVEC SSL (SÉCURISÉ) ---
    $conn = mysqli_init();
    if (!$conn) { die("Erreur initialisation MySQLi"); }

    // Configuration SSL : On indique le chemin vers l'autorité de certification
    mysqli_ssl_set($conn, NULL, NULL, 'C:/webapp/Detechtive_Jedha/ca-cert.pem', NULL, NULL);

    // Tentative de connexion réelle
    // Le @ est utilisé pour éviter que PHP n'affiche les erreurs techniques à l'utilisateur
    if (!@mysqli_real_connect($conn, DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME)) {
        $conn = false; // La connexion a échoué
    }

    if ($conn) {
        // --- 3. SÉCURISATION DES ENTRÉES ---
        $agent_safe = mysqli_real_escape_string($conn, $agent_code_input);
        
        // --- 4. REQUÊTE D'AUTHENTIFICATION ---
        $sql = "SELECT * FROM agents WHERE username = '$agent_safe'";
        $result = mysqli_query($conn, $sql);

        if ($row = mysqli_fetch_assoc($result)) {
            // --- 5. VÉRIFICATION DU MOT DE PASSE HACHÉ ---
            if (password_verify($password_input, $row['password'])) {
                
                // SUCCÈS : Création de la session
                $_SESSION['agent_id'] = $row['id'];
                // On récupère le nom complet s'il existe, sinon le pseudo
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
        // En cas d'échec SSL ou d'erreur réseau/identifiants
        // Tu peux ajouter . mysqli_connect_error() pour le debug si besoin
        $error = "⚠️ ERREUR SYSTÈME : Connexion BDD sécurisée impossible.";
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