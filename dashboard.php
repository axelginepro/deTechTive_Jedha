<?php
session_start();

// ==========================================
// VERSION : v4.0 (Scan Direct Racine du Serveur)
// ==========================================

// --- 1. CONFIGURATION ---
$bdd_ip = "192.168.10.11";
$file_server_name = "file-server"; 
$msg_status = "";

// VERSION 4.0 : On cible directement la racine pour voir tous les partages (administration, etc.)
$root_path = "\\\\".$file_server_name."\\";

// --- 2. CONNEXION BDD ---
try {
    $pdo = new PDO("mysql:host=$bdd_ip;dbname=detective_db;charset=utf8", "admin", "password", [
        PDO::ATTR_TIMEOUT => 2,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $db_online = true;
} catch (Exception $e) {
    $db_online = false;
}

// --- 3. LECTURE DYNAMIQUE DE LA RACINE DU SERVEUR ---
$dossiers_detectes = [];
if (is_dir($root_path)) {
    // scandir va lister tous les partages réseau visibles sur le serveur
    $contenu = scandir($root_path);
    foreach ($contenu as $item) {
        // On ignore les éléments système et on valide que c'est un répertoire accessible
        if ($item != "." && $item != ".." && is_dir($root_path . $item)) {
            $dossiers_detectes[] = $item;
        }
    }
} else {
    $msg_status = "⚠️ Erreur : Impossible de scanner la racine de \\\\$file_server_name. Vérifiez les permissions du compte de service.";
}

// --- 4. ACTION : UPLOAD DANS LE DOSSIER SÉLECTIONNÉ ---
if (isset($_FILES['evidence']) && isset($_POST['target_folder'])) {
    $folder_selected = $_POST['target_folder'];
    
    // Construction du chemin final (ex: \\file-server\administration\fichier.jpg)
    $final_upload_dir = $root_path . $folder_selected . "\\";
    $file_name = basename($_FILES["evidence"]["name"]);
    $destination = $final_upload_dir . $file_name;

    if (move_uploaded_file($_FILES["evidence"]["tmp_name"], $destination)) {
        $msg_status = "✅ Fichier envoyé avec succès dans le partage : " . $folder_selected;
    } else {
        $msg_status = "❌ Erreur : Impossible d'écrire dans //".$file_server_name."/".$folder_selected;
    }
}

// Récupération des missions BDD
$missions = $db_online ? $pdo->query("SELECT * FROM missions ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de Bord Enquêteur - v4.0</title>
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
            <h1>Dossiers en cours : Agent Demey (v4.0)</h1>
            <a href="index.php" style="color: var(--accent-color); text-decoration: none; border: 1px solid; padding: 5px 10px;">Terminer le service</a>
        </div>

        <section>
            <h2>📋 Missions Assignées (BDD : <?php echo $bdd_ip; ?>)</h2>
            <?php if (empty($missions)): ?>
                <div class="mission-card"><div><strong>Aucune mission active enregistrée.</strong></div></div>
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
        </section>

        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 40px 0;">

        <section>
            <h2>📁 Preuves Numériques (File Server : //<?php echo $file_server_name; ?>)</h2>
            <p>Sélectionnez un dossier partagé directement sur le serveur.</p>
            
            <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                <select name="target_folder" required style="width: 100%; padding: 10px; margin-bottom: 10px; background: #222; color: white; border: 1px solid #444;">
                    <option value="">-- Sélectionner un partage détecté --</option>
                    
                    <?php if (empty($dossiers_detectes)): ?>
                        <option value="" disabled>Aucun partage accessible sur //<?php echo $file_server_name; ?></option>
                    <?php else: ?>
                        <?php foreach($dossiers_detectes as $folder): ?>
                            <option value="<?php echo htmlspecialchars($folder); ?>">
                                📂 <?php echo htmlspecialchars($folder); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>

                <input type="file" name="evidence" required>
                <button type="submit" style="background: #e74c3c; color: white;">Transférer vers le serveur de fichiers</button>
            </form>
        </section>

        <footer style="margin-top: 50px; font-size: 0.8rem; color: #666; text-align: center;">
            Version logicielle : <strong>4.0</strong> | Scan racine : <strong>Activé</strong> | Chemin : <?php echo $root_path; ?>
        </footer>
    </div>
</body>
</html>