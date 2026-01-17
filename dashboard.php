<?php
session_start();

// --- 0. CHARGEMENT DE LA SÉCURITÉ ---
if (!file_exists('config.php')) { die("Erreur critique : config.php manquant."); }
require_once 'config.php';

/**
 * 1. CONFIG INFRASTRUCTURE
 */
$file_server_name = defined('FS_IP') ? FS_IP : "192.168.10.19";
$share_name = "Detechtive"; // Racine du partage
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
 * 4. LOGIQUE : AJOUTER MISSION
 */
if (isset($_POST['add_mission']) && $db_online) {
    $new_title = $_POST['title']; $new_code = $_POST['code'];
    $new_status = $_POST['status']; $new_desc = $_POST['description']; 
    try {
        $stmt_team = $pdo->prepare("SELECT team_id FROM agents WHERE id = ?");
        $stmt_team->execute([$agent_id_session]);
        $agent_data = $stmt_team->fetch(PDO::FETCH_ASSOC);
        if ($agent_data) {
            $sql_insert = "INSERT INTO investigations (title, investigation_code, status, description, team_id) VALUES (?, ?, ?, ?, ?)";
            $pdo->prepare($sql_insert)->execute([$new_title, $new_code, $new_status, $new_desc, $agent_data['team_id']]);
            $_SESSION['flash_message'] = "✅ Mission '$new_code' créée !";
            header("Location: dashboard.php"); exit(); 
        }
    } catch (Exception $e) { $msg_status = "❌ Erreur création : " . $e->getMessage(); }
}

/**
 * 5. RÉCUPÉRATION MISSIONS
 */
$missions = [];
if ($db_online) {
    // On sélectionne bien la creation_date
    $stmt = $pdo->prepare("SELECT i.* FROM investigations i JOIN agents a ON i.team_id = a.team_id WHERE a.id = ? ORDER BY i.creation_date DESC");
    $stmt->execute([$agent_id_session]);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 6. GESTION FICHIERS (CORRECTIF AUTHENTIFICATION)
 */
$dossiers_detectes = [];
$apercus = [];
$fs_error_details = "";

// --- AUTHENTIFICATION WINDOWS ---
@exec("net use " . $root_path . " /delete /y");
$user_fs = "Administrator"; 
$pass_fs = '2opw=-nl5?^w161'; // Simples quotes pour protéger le ^
$cmd_auth = 'net use "' . $root_path . '" /user:"' . $user_fs . '" "' . $pass_fs . '"';
@exec($cmd_auth); 

if (is_dir($root_path)) {
    $fs_connected = true;
    $contenu = @scandir($root_path);
    if ($contenu) {
        foreach ($contenu as $item) {
            if ($item != "." && $item != ".." && !strpos($item, '$') && 
                $item != "System Volume Information" && $item != "RECYCLE.BIN" && 
                is_dir($root_path . $item)) {
                $dossiers_detectes[] = $item;
            }
        }
    }
} else {
    $fs_connected = false;
    $fs_error_details = "Impossible d'accéder au partage.";
}

// UPLOAD
if (isset($_FILES['evidence']) && isset($_POST['target_folder']) && $fs_connected) {
    $folder_selected = str_replace(['/', '\\', '..'], '', $_POST['target_folder']);
    $dest = $root_path . $folder_selected . "\\" . basename($_FILES["evidence"]["name"]);
    if (move_uploaded_file($_FILES["evidence"]["tmp_name"], $dest)) {
        $msg_status = "✅ Fichier transféré vers : " . $folder_selected;
    } else {
        $msg_status = "❌ Erreur d'écriture (Droits NTFS ?).";
    }
}

// GALERIE
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
        .header-flex { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 15px; margin-bottom: 30px; }
        
        .mission-card { background: var(--card); padding: 20px; border-left: 5px solid var(--accent); margin-bottom: 15px; border-radius: 4px; }
        .mission-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .badge { padding: 5px 10px; border-radius: 3px; font-size: 0.75rem; background: #27ae60; color: white; font-weight: bold; }
        
        input, select, textarea, button { width: 100%; padding: 12px; margin-bottom: 10px; background: #2c2c2c; color: white; border: 1px solid #444; border-radius: 4px; box-sizing: border-box; }
        .btn-action { background: var(--accent); color: black; border: none; font-weight: bold; cursor: pointer; }
        .btn-action:hover { background: #d4ac0d; }

        /* GALERIE */
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px; margin-top: 20px; }
        .preview-card { background: #252525; border: 1px solid #444; padding: 5px; text-align: center; border-radius: 4px; transition: transform 0.2s; cursor: pointer; }
        .preview-card:hover { transform: scale(1.05); border-color: var(--accent); }
        .preview-img { width: 100%; height: 120px; object-fit: cover; background: #000; border-radius: 3px; display: block; }
        .file-name { font-size: 0.7rem; color: #aaa; margin-top: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 0 5px; }

        /* LIGHTBOX */
        .lightbox { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); justify-content: center; align-items: center; }
        .lightbox img { max-width: 90%; max-height: 90%; border: 2px solid var(--accent); box-shadow: 0 0 20px rgba(241, 196, 15, 0.5); }
        .lightbox:target { display: flex; }

        /* MODAL */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px); }
        .modal-content { background-color: #1a252f; margin: 10% auto; padding: 25px; border: 1px solid var(--accent); width: 90%; max-width: 500px; border-radius: 8px; }
        .close { float: right; font-size: 28px; cursor: pointer; color: #aaa; }
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
                <small>Contact : <?php echo htmlspecialchars($agent_contact); ?></small>
            </div>
            <div>
                <button id="openModalBtn" class="btn-action" style="width:auto; margin-right:10px;">➕ Mission</button>
                <a href="index.php" style="color: #e74c3c; font-weight: bold; text-decoration: none;">[ DÉCO ]</a>
            </div>
        </div>

        <div id="missionModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3 style="color: var(--accent); text-align: center;">Nouvelle Mission</h3>
                <form method="POST">
                    <input type="text" name="title" placeholder="Titre" required>
                    <div style="display:flex; gap:10px;">
                        <input type="text" name="code" placeholder="Code" required style="flex:1;">
                        <select name="status" style="flex:1;">
                            <option>En Cours</option><option>Urgent</option><option>Terminé</option>
                        </select>
                    </div>
                    <textarea name="description" placeholder="Description..." style="height:80px;"></textarea>
                    <button type="submit" name="add_mission" class="btn-action">Créer</button>
                </form>
            </div>
        </div>

        <section>
            <h2>📋 Rapports</h2>
            <?php if (empty($missions)): ?>
                <div class="mission-card">Aucune mission.</div>
            <?php else: ?>
                <?php foreach($missions as $m): ?>
                <div class="mission-card">
                    <div class="mission-header">
                        <div>
                            <strong><?php echo htmlspecialchars($m['title']); ?></strong>
                            
                            <div style="font-size:0.8rem; color:#888; margin-top:4px;">
                                🆔 <?php echo htmlspecialchars($m['investigation_code']); ?> &nbsp;|&nbsp; 
                                📅 <?php echo date("d/m/Y", strtotime($m['creation_date'])); ?>
                            </div>
                        </div>
                        <span class="badge"><?php echo htmlspecialchars($m['status']); ?></span>
                    </div>
                    <div style="color:#ccc; font-size:0.9rem; margin-top:8px;"><?php echo nl2br(htmlspecialchars($m['description'])); ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <hr style="border-color:#333; margin:40px 0;">

        <section>
            <h2>📁 Preuves Numériques (File Server)</h2>
            <?php if ($fs_connected): ?>
                <div style="padding:10px; background:rgba(39, 174, 96, 0.3); border:1px solid #27ae60; text-align:center; margin-bottom:15px; color:#2ecc71;">
                    ✅ CONNECTÉ : <?php echo htmlspecialchars($share_name); ?>
                </div>
                
                <form method="POST" enctype="multipart/form-data" style="background:#252525; padding:15px; border-radius:5px;">
                    <div style="display:flex; gap:10px;">
                        <select name="target_folder" onchange="this.form.submit()" required style="margin:0;">
                            <option value="">-- Choisir un dossier --</option>
                            <?php foreach($dossiers_detectes as $folder): ?>
                                <option value="<?php echo htmlspecialchars($folder); ?>" <?php echo ($current_view == $folder) ? 'selected' : ''; ?>>
                                    📂 <?php echo htmlspecialchars($folder); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($current_view): ?>
                            <input type="file" name="evidence" style="margin:0;">
                            <button type="submit" class="btn-action" style="width:auto; margin:0;">Uploader</button>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($current_view && !empty($apercus)): ?>
                    <h3 style="margin-top:20px; border-bottom:1px solid #444; padding-bottom:5px;">Contenu de : <?php echo htmlspecialchars($current_view); ?></h3>
                    <div class="preview-grid">
                        <?php foreach ($apercus as $file): ?>
                            <?php 
                                $is_img = in_array($file['ext'], ['jpg', 'jpeg', 'png', 'gif']);
                                $src = "";
                                if ($is_img) {
                                    $content = @file_get_contents($file['path']);
                                    if ($content) {
                                        $src = 'data:image/'.$file['ext'].';base64,'.base64_encode($content);
                                    }
                                }
                            ?>
                            <div class="preview-card" onclick="<?php echo ($is_img && $src) ? "openLightbox('$src')" : ""; ?>">
                                <?php if ($is_img && $src): ?>
                                    <img src="<?php echo $src; ?>" class="preview-img">
                                <?php elseif ($is_img && !$src): ?>
                                    <div style="height:120px; display:flex; align-items:center; justify-content:center; color:#e74c3c;">🔒</div>
                                <?php else: ?>
                                    <div style="height:120px; display:flex; align-items:center; justify-content:center; font-size:3rem;">📄</div>
                                <?php endif; ?>
                                <div class="file-name"><?php echo htmlspecialchars($file['name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="padding:10px; background:rgba(192, 57, 43, 0.3); border:1px solid #c0392b; text-align:center; color:#e74c3c;">
                    ❌ SERVEUR NON DISPONIBLE
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div id="lightbox" class="lightbox" onclick="this.style.display='none'">
        <img id="lightbox-img" src="">
    </div>

    <script>
        // Gestion Modal Mission
        var modal = document.getElementById("missionModal");
        document.getElementById("openModalBtn").onclick = function() { modal.style.display = "block"; }
        document.getElementsByClassName("close")[0].onclick = function() { modal.style.display = "none"; }
        window.onclick = function(e) { if(e.target == modal) modal.style.display = "none"; }

        // Gestion Lightbox
        function openLightbox(src) {
            document.getElementById('lightbox-img').src = src;
            document.getElementById('lightbox').style.display = 'flex';
        }
    </script>
</body>
</html>