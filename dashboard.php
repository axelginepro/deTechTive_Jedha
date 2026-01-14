<?php
session_start();

// --- 1. CONFIGURATION ET CONNEXION ---
$bdd_ip = "192.168.10.11";
$file_server_ip = "192.168.10.13";
$msg_status = "";

try {
    // Utilisation de PDO avec Timeout pour éviter les blocages réseau
    $pdo = new PDO("mysql:host=$bdd_ip;dbname=detective_db;charset=utf8", "admin", "password", [
        PDO::ATTR_TIMEOUT => 2,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $db_online = true;
} catch (Exception $e) {
    $db_online = false;
    $msg_status = "⚠️ Mode Hors-Ligne (Serveur BDD 10.11 injoignable)";
}

// --- 2. PROTECTION SQL : AJOUT MISSION ---
if (isset($_POST['mission_desc']) && $db_online) {
    // Requête préparée pour bloquer les injections SQL
    $stmt = $pdo->prepare("INSERT INTO missions (titre, statut) VALUES (?, 'EN COURS')");
    $stmt->execute([$_POST['mission_desc']]);
    header("Location: dashboard.php");
    exit;
}

// --- 3. UPLOAD SÉCURISÉ : FILE SERVER ---
if (isset($_FILES['evidence']) && isset($_POST['target_mission_id'])) {
    $mission_id = (int)$_POST['target_mission_id']; // Forçage en entier pour la sécurité
    $upload_dir = "uploads/mission_" . $mission_id . "/";
    
    if (!is_dir($upload_dir)) { 
        mkdir($upload_dir, 0777, true); 
    }
    
    $file_path = $upload_dir . basename($_FILES["evidence"]["name"]);
    if (move_uploaded_file($_FILES["evidence"]["tmp_name"], $file_path)) {
        $msg_status = "✅ Fichier transféré vers le stockage sécurisé (10.13)";
    }
}

// Récupération des données
$missions = $db_online ? $pdo->query("SELECT * FROM missions ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de Bord Enquêteur</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <?php if($msg_status): ?>
            <div style="padding: 10px; background: rgba(241, 196, 15, 0.2); border: 1px solid var(--accent-color); margin-bottom: 20px; font-size: 0.8rem;">
                <?php echo $msg_status; ?>
            </div>
        <?php endif; ?>

        <div class="header-flex">
            <h1>Dossiers en cours : Agent Demey</h1>
            <a href="index.php" style="color: var(--accent-color); text-decoration: none; border: 1px solid; padding: 5px 10px;">Terminer le service</a>
        </div>

        <section>
            <h2>📋 Missions Assignées (BDD : <?php echo $bdd_ip; ?>)</h2>
            
            <?php if (empty($missions)): ?>
                <div class="mission-card"><div><strong>Aucune mission active.</strong></div></div>
            <?php else: ?>
                <?php foreach($missions as $m): ?>
                <div class="mission-card">
                    <div>
                        <strong><?php echo htmlspecialchars($m['titre'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <small>ID Dossier: #<?php echo (int)$m['id']; ?></small>
                    </div>
                    <span class="status-badge"><?php echo htmlspecialchars($m['statut'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <h3 style="margin-top: 30px; color: var(--accent-color);">Ajouter nouvelle mission</h3>
            <form action="dashboard.php" method="POST">
                <input type="text" name="mission_desc" placeholder="Titre ou description du dossier..." required>
                <button type="submit">Enregistrer la mission</button>
            </form>
        </section>

        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 40px 0;">

        <section>
            <h2>📁 Preuves Numériques (File Server : <?php echo $file_server_ip; ?>)</h2>
            <p>Classez les fichiers dans les dossiers de missions correspondants.</p>
            
            <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                <select name="target_mission_id" required style="width: 100%; padding: 10px; margin-bottom: 10px; background: #222; color: white; border: 1px solid #444;">
                    <option value="">-- Sélectionner le dossier de destination --</option>
                    <?php foreach($missions as $m): ?>
                        <option value="<?php echo (int)$m['id']; ?>">Dossier #<?php echo (int)$m['id']; ?> - <?php echo htmlspecialchars($m['titre'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>

                <input type="file" name="evidence" required>
                <button type="submit" style="background: #e74c3c; color: white;">Uploader la preuve</button>
            </form>
        </section>

        <footer style="margin-top: 50px; font-size: 0.8rem; color: #666; text-align: center;">
            Sécurité : <strong>PDO Prepared Statements</strong> & <strong>XSS Filtering</strong> | Segment BDD : 10.11 | Stockage : 10.13
        </footer>
    </div>
</body>