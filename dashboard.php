<?php
session_start();

// --- 0. CHARGEMENT DE LA SÉCURITÉ ---
if (!file_exists('config.php')) { die("Erreur critique : config.php manquant."); }
require_once 'config.php';

/**
 * 1. CONFIG INFRASTRUCTURE
 */
$file_server_name = defined('FS_IP') ? FS_IP : "192.168.10.19";
$share_name = defined('FS_SHARE_NAME') ? FS_SHARE_NAME : "resources";
$root_path = "\\\\" . $file_server_name . "\\" . $share_name . "\\"; 
$msg_status = "";
$fs_connected = false;

/**
 * 2. SÉCURITÉ SESSION
 */
if (!isset($_SESSION['agent_id'])) { header("Location: index.php"); exit(); }
$agent_id_session = $_SESSION['agent_id'];
$nom_agent = $_SESSION['agent_name'];

if (isset($_SESSION['flash_message'])) {
    $msg_status = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); 
}

/**
 * 3. CONNEXION BDD
 */ 
try {
    if (!extension_loaded('pdo_mysql')) { throw new Exception("Driver pdo_mysql manquant."); }
    $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8";
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]);
    $db_online = true;
} catch (Exception $e) {
    $db_online = false;
    $msg_status = "⚠️ ERREUR BDD : " . $e->getMessage();
}

// --- INFO CONTACT AGENT ---
$agent_contact = "Non renseigné"; 
if ($db_online) {
    try {
        $stmt_info = $pdo->prepare("SELECT contact FROM agents WHERE id = ?");
        $stmt_info->execute([$agent_id_session]);
        $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
        if ($info && !empty($info['contact'])) { $agent_contact = $info['contact']; }
    } catch (Exception $e) {}
}

/**
 * 4. LOGIQUE : AJOUTER MISSION (MODIFIÉ)
 */
if (isset($_POST['add_mission']) && $db_online) {
    $new_title = $_POST['title'];
    $new_code = $_POST['code'];
    $new_status = $_POST['status'];
    $new_desc = $_POST['description']; // NOUVEAU

    try {
        $stmt_team = $pdo->prepare("SELECT team_id FROM agents WHERE id = ?");
        $stmt_team->execute([$agent_id_session]);
        $agent_data = $stmt_team->fetch(PDO::FETCH_ASSOC);
        
        if ($agent_data) {
            $my_team_id = $agent_data['team_id'];
            // Ajout de la description dans l'INSERT
            $sql_insert = "INSERT INTO investigations (title, investigation_code, status, description, team_id) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([$new_title, $new_code, $new_status, $new_desc, $my_team_id]);

            $_SESSION['flash_message'] = "✅ Mission '$new_code' créée avec succès !";
            header("Location: dashboard.php"); 
            exit(); 
        }
    } catch (Exception $e) {
        $msg_status = "❌ Erreur création : " . $e->getMessage();
    }
}

/**
 * 5. RÉCUPÉRATION MISSIONS (MODIFIÉ)
 */
$missions = [];
if ($db_online) {
    // Ajout de description et creation_date dans le SELECT
    $sql = "SELECT i.title, i.status, i.investigation_code, i.description, i.creation_date 
            FROM investigations i
            INNER JOIN agents a ON i.team_id = a.team_id
            WHERE a.id = ? 
            ORDER BY i.creation_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$agent_id_session]);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 6. GESTION FICHIERS (CODE FIXÉ)
 */
$dossiers_detectes = [];
$apercus = [];
$fs_error_details = "";

@exec("net use " . $root_path . " /delete /y");
$user_fs = "Administrator"; 
$pass_fs = "2opw=-nl5?`^w161";
$cmd_auth = 'net use "' . $root_path . '" /user:"' . $user_fs . '" "' . $pass_fs . '"';
@exec($cmd_auth); 

if (is_dir($root_path)) {
    $fs_connected = true;
    $contenu = @scandir($root_path);
    if ($contenu) {
        foreach ($contenu as $item) {
            if ($item != "." && $item != ".." && !strpos($item, '$') && $item != "System Volume Information" && $item != "RECYCLE.BIN" && is_dir($root_path . $item)) {
                $dossiers_detectes[] = $item;
            }
        }
    }
} else {
    $fs_connected = false;
    $fs_error_details = "Impossible d'atteindre le chemin.";
}

if (isset($_FILES['evidence']) && isset($_POST['target_folder']) && $fs_connected) {
    $folder_selected = str_replace(['/', '\\', '..'], '', $_POST['target_folder']);
    $dest = $root_path . $folder_selected . "\\" . basename($_FILES["evidence"]["name"]);
    if (move_uploaded_file($_FILES["evidence"]["tmp_name"], $dest)) {
        $msg_status = "✅ Preuve déposée dans : " . $folder_selected;
    } else {
        $msg_status = "❌ Échec upload.";
    }
}

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
    <title>Detechtive Dashboard</title>
    <style>
        :root { --accent: #f1c40f; --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .container { max-width: 900px; margin: auto; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 25px; border: 1px solid #555; background: #34495e; }
        .header-flex { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #333; padding-bottom: 15px; margin-bottom: 30px; }
        
        /* CARTE MISSION REVAMPED */
        .mission-card { background: var(--card); padding: 20px; border-left: 5px solid var(--accent); margin-bottom: 15px; border-radius: 4px; }
        .mission-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .mission-title { font-size: 1.2rem; font-weight: bold; color: #fff; margin: 0; }
        .mission-meta { font-size: 0.85rem; color: #888; margin-top: 5px; }
        .mission-desc { background: #252525; padding: 10px; border-radius: 4px; font-size: 0.95rem; color: #ccc; margin-top: 10px; border: 1px solid #333; }
        .badge { padding: 5px 10px; border-radius: 3px; font-size: 0.75rem; background: #27ae60; color: white; font-weight: bold; }

        /* FORMULAIRES */
        .add-mission-box { background: #1a252f; border: 1px dashed #555; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        input, select, textarea, button { width: 100%; padding: 12px; margin-bottom: 10px; background: #2c2c2c; color: white; border: 1px solid #444; border-radius: 4px; box-sizing: border-box; }
        textarea { height: 80px; resize: vertical; font-family: inherit; }
        .btn-upload { background: var(--accent); color: black; border: none; font-weight: bold; cursor: pointer; }
        .btn-upload:hover { background: #d4ac0d; }

        /* IMAGES */
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-top: 20px; }
        .preview-card { background: #252525; border: 1px solid #444; padding: 10px; text-align: center; border-radius: 4px; }
        .preview-img { width: 100%; height: 110px; object-fit: cover; background: #000; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <?php if($msg_status): ?>
            <div class="alert"><?php echo $msg_status; ?></div>
        <?php endif; ?>

        <div class="header-flex">
            <div>
                <h1 style="margin: 0;">Agent : <?php echo htmlspecialchars($nom_agent); ?></h1>
                <small style="color: #aaa;">📧 Contact : <span style="color: #fff; font-family: monospace;"><?php echo htmlspecialchars($agent_contact); ?></span></small>
            </div>
            <a href="index.php" style="color: #e74c3c; text-decoration: none; font-weight: bold;">[ DÉCONNEXION ]</a>
        </div>

        <div class="add-mission-box">
            <h3 style="margin-top: 0; color: var(--accent);">➕ Nouvelle Mission</h3>
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

                <textarea name="description" placeholder="Description détaillée de la mission, objectifs, suspects..."></textarea>
                
                <button type="submit" name="add_mission" class="btn-upload">ENREGISTRER LA MISSION</button>
            </form>
        </div>

        <section>
            <h2>📋 Rapports de Missions</h2>
            <?php if (empty($missions)): ?>
                <div class="mission-card">Aucune mission assignée.</div>
            <?php else: ?>
                <?php foreach($missions as $m): ?>
                <div class="mission-card">
                    <div class="mission-header">
                        <div>
                            <div class="mission-title"><?php echo htmlspecialchars($m['title']); ?></div>
                            <div class="mission-meta">
                                🆔 Code : <?php echo htmlspecialchars($m['investigation_code']); ?> &nbsp;|&nbsp; 
                                📅 Créé le : <?php echo date("d/m/Y à H:i", strtotime($m['creation_date'])); ?>
                            </div>
                        </div>
                        <span class="badge"><?php echo htmlspecialchars($m['status']); ?></span>
                    </div>
                    
                    <?php if (!empty($m['description'])): ?>
                        <div class="mission-desc">
                            <?php echo nl2br(htmlspecialchars($m['description'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <hr style="margin: 45px 0; border: 0; border-top: 1px solid #333;">

        <section>
            <h2>📁 Coffre-fort Numérique</h2>
            <?php if ($fs_connected): ?>
                <div style="padding: 10px; background: rgba(39, 174, 96, 0.3); border: 1px solid #27ae60; color: #2ecc71; text-align: center; margin-bottom: 15px;">
                    ✅ CONNECTÉ AU SERVEUR : <?php echo htmlspecialchars($file_server_name); ?>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <select name="target_folder" onchange="this.form.submit()" required>
                        <option value="">-- Sélectionner un dossier --</option>
                        <?php foreach($dossiers_detectes as $folder): ?>
                            <option value="<?php echo htmlspecialchars($folder); ?>" <?php echo ($current_view == $folder) ? 'selected' : ''; ?>>
                                📂 <?php echo htmlspecialchars($folder); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($current_view): ?>
                        <input type="file" name="evidence" required>
                        <button type="submit" class="btn-upload">TÉLÉVERSER</button>
                    <?php endif; ?>
                </form>

                <?php if ($current_view && !empty($apercus)): ?>
                    <div class="preview-grid">
                        <?php foreach ($apercus as $file): ?>
                            <div class="preview-card">
                                <?php if (in_array($file['ext'], ['jpg', 'jpeg', 'png', 'gif'])): 
                                    $content = @file_get_contents($file['path']); 
                                    if ($content): $src = 'data:image/'.$file['ext'].';base64,'.base64_encode($content); ?>
                                    <img src="<?php echo $src; ?>" class="preview-img">
                                <?php else: ?>
                                    <div style="height:110px; display:flex; align-items:center; justify-content:center; color:red;">🔒</div>
                                <?php endif; else: ?>
                                    <div style="font-size: 3rem; margin-bottom: 10px;">📄</div>
                                <?php endif; ?>
                                <div style="font-size: 0.7rem;"><?php echo htmlspecialchars($file['name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="padding: 10px; background: rgba(192, 57, 43, 0.3); border: 1px solid #c0392b; color: #e74c3c; text-align: center;">
                    ❌ SERVEUR INACCESSIBLE
                </div>
            <?php endif; ?> 
        </section>
    </div>
</body>
</html>