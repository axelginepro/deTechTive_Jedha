<?php
session_start();

// --- 0. CHARGEMENT DE LA SÉCURITÉ (CONFIG.PHP) ---
if (!file_exists('config.php')) {
    die("Erreur critique : Le fichier de configuration 'config.php' est manquant.");
}
require_once 'config.php';

/**
 * ============================================================
 * 1. CONFIGURATION DE L'INFRASTRUCTURE
 * ============================================================
 */
// Utilisation des constantes définies dans config.php
$file_server_name = defined('FS_IP') ? FS_IP : "192.168.10.19";
$share_name = defined('FS_SHARE_NAME') ? FS_SHARE_NAME : "resources";

// Construction du chemin UNC (Network Path)
$root_path = "\\\\" . $file_server_name . "\\" . $share_name . "\\"; 
$msg_status = "";
$fs_connected = false; // Variable d'état pour le File Server

/**
 * ============================================================
 * 2. SÉCURITÉ : VÉRIFICATION DE LA SESSION
 * ============================================================
 */
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}

$agent_id_session = $_SESSION['agent_id'];
$nom_agent = $_SESSION['agent_name'];

// --- ANTI-DOUBLON ---
if (isset($_SESSION['flash_message'])) {
    $msg_status = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); 
}

/**
 * ============================================================
 * 3. CONNEXION À LA BASE DE DONNÉES (MySQL)
 * ============================================================
 */ 
try {
    if (!extension_loaded('pdo_mysql')) { throw new Exception("Driver pdo_mysql manquant."); }

    $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8";
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
        PDO::ATTR_TIMEOUT => 2,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $db_online = true;

} catch (Exception $e) {
    $db_online = false;
    $msg_status = "⚠️ ERREUR BDD : " . $e->getMessage();
}

/**
 * ============================================================
 * 4. LOGIQUE : AJOUTER UNE MISSION
 * ============================================================
 */
if (isset($_POST['add_mission']) && $db_online) {
    $new_title = $_POST['title'];
    $new_code = $_POST['code'];
    $new_status = $_POST['status'];

    try {
        $stmt_team = $pdo->prepare("SELECT team_id FROM agents WHERE id = ?");
        $stmt_team->execute([$agent_id_session]);
        $agent_data = $stmt_team->fetch(PDO::FETCH_ASSOC);
        
        if ($agent_data) {
            $my_team_id = $agent_data['team_id'];
            $sql_insert = "INSERT INTO investigations (title, investigation_code, status, team_id) VALUES (?, ?, ?, ?)";
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([$new_title, $new_code, $new_status, $my_team_id]);

            $_SESSION['flash_message'] = "✅ Mission '$new_code' ajoutée avec succès !";
            header("Location: dashboard.php"); 
            exit(); 
        }
    } catch (Exception $e) {
        $msg_status = "❌ Erreur création : " . $e->getMessage();
    }
}

/**
 * ============================================================
 * 5. RÉCUPÉRATION DES MISSIONS
 * ============================================================
 */
$missions = [];
if ($db_online) {
    $sql = "SELECT i.title, i.status, i.investigation_code 
            FROM investigations i
            INNER JOIN agents a ON i.team_id = a.team_id
            WHERE a.id = ? 
            ORDER BY i.creation_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$agent_id_session]);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * ============================================================
 * 6. GESTION DU SERVEUR DE FICHIERS (TEST DE CONNEXION)
 * ============================================================
 */
$dossiers_detectes = [];
$apercus = [];
$fs_error_details = "";

// 1. Test initial de connexion
if (is_dir($root_path)) {
    $fs_connected = true;
    
    // 2. Scan des dossiers
    $contenu = scandir($root_path);
    foreach ($contenu as $item) {
        if ($item != "." && $item != ".." && !strpos($item, '$') && 
            $item != "System Volume Information" && 
            $item != "RECYCLE.BIN" &&
            is_dir($root_path . $item)) {
            $dossiers_detectes[] = $item;
        }
    }
} else {
    $fs_connected = false;
    $fs_error_details = "Impossible d'atteindre le chemin : " . $root_path;
}

// 3. Action Upload
if (isset($_FILES['evidence']) && isset($_POST['target_folder']) && $fs_connected) {
    $folder_selected = str_replace(['/', '\\', '..'], '', $_POST['target_folder']);
    $final_upload_dir = $root_path . $folder_selected . "\\";
    $file_name = basename($_FILES["evidence"]["name"]);
    $destination = $final_upload_dir . $file_name;

    if (!is_writable($final_upload_dir)) {
        $msg_status = "⛔ Erreur Droits : Le serveur web n'a pas le droit d'écrire ici.";
    } elseif (move_uploaded_file($_FILES["evidence"]["tmp_name"], $destination)) {
        $msg_status = "✅ Preuve déposée avec succès dans : " . $folder_selected;
    } else {
        $msg_status = "❌ Échec de l'écriture réseau.";
    }
}

// 4. Action Aperçu (Thumbnails)
$current_view = isset($_POST['target_folder']) ? str_replace(['/', '\\', '..'], '', $_POST['target_folder']) : "";

if ($fs_connected && $current_view && is_dir($root_path . $current_view)) {
    $files = scandir($root_path . $current_view);
    foreach ($files as $f) {
        $full_p = $root_path . $current_view . "\\" . $f;
        if ($f != "." && $f != ".." && !is_dir($full_p)) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            $apercus[] = ['name' => $f, 'path' => $full_p, 'ext' => $ext];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Detechtive Dashboard - <?php echo htmlspecialchars($nom_agent); ?></title>
    <style>
        :root { --accent: #f1c40f; --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .container { max-width: 900px; margin: auto; }
        
        /* Alertes */
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 25px; font-weight: bold; border: 1px solid #555; }
        .alert-info { background: #34495e; border-color: #5d6d7e; }
        .alert-error { background: rgba(192, 57, 43, 0.9); border-color: #e74c3c; }
        
        .header-flex { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 15px; margin-bottom: 30px; }
        .mission-card { background: var(--card); padding: 15px; border-left: 5px solid var(--accent); margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
        
        /* Grille Aperçu */
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-top: 20px; }
        .preview-card { background: #252525; border: 1px solid #444; padding: 10px; text-align: center; border-radius: 4px; overflow: hidden; }
        .preview-img { width: 100%; height: 110px; object-fit: cover; background: #000; margin-bottom: 8px; border-radius: 3px; }
        
        /* Formulaires */
        select, button, input[type="text"] { width: 100%; padding: 12px; margin-bottom: 10px; background: #2c2c2c; color: white; border: 1px solid #444; border-radius: 4px; box-sizing: border-box; }
        .btn-upload { background: var(--accent); color: black; border: none; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-upload:hover { background: #d4ac0d; }
        .add-mission-box { background: #1a252f; border: 1px dashed #555; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        
        /* Status File Server */
        .fs-status { padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; font-weight: bold; }
        .fs-ok { background: rgba(39, 174, 96, 0.3); border: 1px solid #27ae60; color: #2ecc71; }
        .fs-ko { background: rgba(192, 57, 43, 0.3); border: 1px solid #c0392b; color: #e74c3c; }
    </style>
</head>
<body>
    <div class="container">
        
        <?php if($msg_status): ?>
            <div class="alert alert-info"><?php echo $msg_status; ?></div>
        <?php endif; ?>

        <div class="header-flex">
            <h1>Agent : <?php echo htmlspecialchars($nom_agent); ?></h1>
            <a href="index.php" style="color: #e74c3c; text-decoration: none; font-weight: bold;">[ DÉCONNEXION ]</a>
        </div>

        <section>
            <h2>📋 Vos Missions</h2>
            <?php if (empty($missions)): ?>
                <div class="mission-card">Aucune mission n'est actuellement assignée à votre équipe.</div>
            <?php else: ?>
                <?php foreach($missions as $m): ?>
                <div class="mission-card">
                    <div>
                        <strong><?php echo htmlspecialchars($m['title']); ?></strong><br>
                        <small style="color: #888;">Code : <?php echo htmlspecialchars($m['investigation_code']); ?></small>
                    </div>
                    <span style="background: #27ae60; padding: 5px 10px; border-radius: 3px; font-size: 0.75rem;">
                        <?php echo htmlspecialchars($m['status']); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <div class="add-mission-box">
            <h3 style="margin-top: 0; color: var(--accent);">➕ Créer une nouvelle mission</h3>
            <form method="POST">
                <input type="text" name="title" placeholder="Titre de la mission (ex: Filature rue de la Paix)" required>
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="code" placeholder="Code (ex: OP-2026-XYZ)" required style="flex: 1;">
                    <select name="status" style="flex: 1;">
                        <option value="En Cours">En Cours</option>
                        <option value="Urgent">Urgent</option>
                        <option value="Terminé">Terminé</option>
                        <option value="Classifié">Classifié</option>
                    </select>
                </div>
                <button type="submit" name="add_mission" class="btn-upload">ENREGISTRER LA MISSION</button>
            </form>
        </div>

        <hr style="margin: 45px 0; border: 0; border-top: 1px solid #333;">

        <section>
            <h2>📁 Coffre-fort Numérique (File Server)</h2>
            
            <?php if ($fs_connected): ?>
                <div class="fs-status fs-ok">
                    ✅ CONNECTÉ AU SERVEUR : <?php echo htmlspecialchars($file_server_name); ?>
                </div>
            <?php else: ?>
                <div class="fs-status fs-ko">
                    ❌ NON CONNECTÉ <br>
                    <small>Erreur : <?php echo $fs_error_details; ?></small><br>
                    <small><i>Astuce : Vérifiez que le partage "Tout le monde" est actif sur la VM.</i></small>
                </div>
            <?php endif; ?>

            <?php if ($fs_connected): ?>
                <form method="POST" enctype="multipart/form-data">
                    <label>Sélectionnez un dossier de partage :</label>
                    <select name="target_folder" onchange="this.form.submit()" required>
                        <option value="">-- Liste des dossiers détectés --</option>
                        <?php foreach($dossiers_detectes as $folder): ?>
                            <option value="<?php echo htmlspecialchars($folder); ?>" <?php echo ($current_view == $folder) ? 'selected' : ''; ?>>
                                📂 <?php echo htmlspecialchars($folder); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div style="padding: 15px; border: 1px dashed #444; border-radius: 5px; margin-top: 10px;">
                        <input type="file" name="evidence" required style="margin-bottom: 10px;">
                        <button type="submit" class="btn-upload">TÉLÉVERSER LA PREUVE</button>
                    </div>
                </form>

                <?php if ($current_view && !empty($apercus)): ?>
                    <h3 style="margin-top:20px; border-bottom:1px solid #444; padding-bottom:5px;">Aperçu du dossier : <?php echo htmlspecialchars($current_view); ?></h3>
                    <div class="preview-grid">
                        <?php foreach ($apercus as $file): ?>
                            <div class="preview-card">
                                <?php if (in_array($file['ext'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                    <?php 
                                        // Lecture sécurisée du contenu pour conversion Base64
                                        $content = @file_get_contents($file['path']); 
                                        if ($content !== false):
                                            $img_data = base64_encode($content);
                                            $src = 'data:image/' . $file['ext'] . ';base64,' . $img_data;
                                    ?>
                                        <img src="<?php echo $src; ?>" class="preview-img">
                                    <?php else: ?>
                                        <div style="height:110px; display:flex; align-items:center; justify-content:center; background:#000; color:red; font-size:0.8rem;">
                                            🔒 Accès Refusé
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div style="font-size: 3rem; margin-bottom: 10px; height:110px; display:flex; align-items:center; justify-content:center;">📄</div>
                                <?php endif; ?>
                                <div style="font-size: 0.65rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars($file['name']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?> </section>
    </div>
</body>
</html>