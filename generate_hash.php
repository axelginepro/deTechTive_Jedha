<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Générateur de Mot de Passe Hashé</title>
    <style>
        body { font-family: sans-serif; background: #222; color: #fff; text-align: center; padding: 50px; }
        input, button { padding: 10px; font-size: 1.2rem; }
        .result { background: #444; padding: 20px; margin-top: 20px; word-break: break-all; border: 1px dashed #f1c40f; }
    </style>
</head>
<body>
    <h2>🔐 Générateur de Hash</h2>
    <p>Tapez le mot de passe que vous voulez donner à votre nouvel agent :</p>
    
    <form method="POST">
        <input type="text" name="password" placeholder="Ex: JamesBond007" required>
        <button type="submit">HASHER !</button>
    </form>

    <?php
    if (isset($_POST['password'])) {
        $pass = $_POST['password'];
        // C'est ici que la magie opère
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        
        echo "<div class='result'>";
        echo "<strong>Mot de passe :</strong> " . htmlspecialchars($pass) . "<br><br>";
        echo "<strong>Hash à copier dans la BDD :</strong><br>";
        echo "<h3 style='color:#2ecc71;'>" . $hash . "</h3>";
        echo "</div>";
    }
    ?>
</body>
</html>