<?php
session_start();

// --- 0. CHARGEMENT DE LA SÉCURITÉ ---
if (!file_exists('config.php')) { die("Erreur critique : config.php manquant."); }
require_once 'config.php';

/**
 * 1. CONFIG INFRASTRUCTURE
 * Correction ici : On vise le dossier racine visible sur ta capture
 */
$file_server_name = defined('FS_IP') ? FS_IP : "192.168.10.19";

// D'après ta capture, le dossier racine semble être "Detechtive"
// Si tu as partagé "resources" séparément, remets "resources" ici.
$share_name = "Detechtive"; 

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
    $new_title = $_POST['title'];
    $new_code = $_POST['code'];
    $new_status = $_POST['status'];
    $new_desc = $_POST['description']; 

    try {
        $stmt_team = $pdo->prepare("SELECT team_id FROM agents WHERE id = ?");
        $stmt_team->execute([$agent_id_session]);
        $agent_data = $stmt_team->fetch(PDO::FETCH_ASSOC);
        
        if ($agent_data) {
            $my_team_id = $agent_data['team_id'];
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
 * 5. RÉCUPÉRATION MISSIONS
 */
$missions = [];
if ($db_online) {
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
 * 6. GESTION FICHIERS (CORRECTIF DE CONNEXION)
 */
$dossiers_detectes = [];
$apercus = [];
$fs_error_details = "";
$debug_output = []; // Pour voir ce que Windows répond

// Nettoyage préventif
@exec("net use " . $root_path . " /delete /y");

$user_fs = "Administrator"; 
// UTILISATION DE SIMPLES QUOTES pour que PHP ne touche pas au ^
$pass_fs = '2opw=-nl5?^w161'; 

// On échappe les arguments pour que le CMD windows ne plante pas sur le ^
$cmd_auth = 'net use "' . $root_path . '" /user:"' . $user_fs . '" "' . $pass_fs . '"';

// On capture la sortie (output) pour le debug
exec($cmd_auth . " 2>&1", $debug_output, $return_var); 

if (is_dir($root_path)) {
    $fs_connected = true;
    
    // Scan du dossier racine
    $contenu = @scandir($root_path);
    
    // Si on trouve "resources" dans la liste, on rentre dedans automatiquement si tu veux
    // Sinon on liste tout ce qu'il y a dans "Detechtive"
    if ($contenu) {
        foreach ($contenu as $item) {
            if ($item != "." && $item != ".." && !strpos($item, '$') && 
                $item != "System Volume Information" && 
                $item != "RECYCLE.BIN" && 
                is_dir($root_path . $item)) {
                $dossiers_detectes[] = $item;
            }
        }
    }
} else {
    $fs_connected = false;
    // On affiche la réponse de Windows pour comprendre l'erreur
    $windows_msg = implode(" ", $debug_output);
    $fs_error_details = "Erreur Windows : " . $windows_msg;
}

// LOGIQUE UPLOAD
if (isset($_FILES['evidence']) && isset($_POST['target_folder']) && $fs_connected) {
    $folder_selected = str_replace(['/', '\\', '..'], '', $_POST['target_folder']);
    // On construit le chemin complet
    $dest = $root_path . $folder_selected . "\\" . basename($_FILES["evidence"]["name"]);
    
    if (move_uploaded_file($_FILES["evidence"]["tmp_name"], $dest)) {
        $msg_status = "✅ Preuve déposée dans : " . $folder_selected;
    } else {
        $msg_status = "❌ Échec upload (Vérifier droits d'écriture sur le dossier).";
    }
}

// LOGIQUE APERÇU
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
        
        .mission-card { background: var(--card); padding: 20px; border-left: 5px solid var(--accent); margin-bottom: 15px; border-radius: 4px; }
        .mission-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .mission-title { font-size: 1.2rem; font-weight: bold; color: #fff; margin: 0; }
        .mission-meta { font-size: 0.85rem; color: #888; margin-top: 5px; }
        .mission-desc { background: #252525; padding: 10px; border-radius: 4px; font-size: 0.95rem; color: #ccc; margin-top: 10px; border: 1px solid #333; }
        .badge { padding: 5px 10px; border-radius: 3px; font-size: 0.75rem; background: #27ae60; color: white; font-weight: bold; }

        input, select, textarea, button { width: 100%; padding: 12px; margin-bottom: 10px; background: #2c2c2c; color: white; border: 1px solid #444; border-radius: 4px; box-sizing: border-box; }
        textarea { height: 80px; resize: vertical; font-family: inherit; }
        .btn-action { background: var(--accent); color: black; border: none; font-weight: bold; cursor: pointer; display: inline-block; text-align: center; padding: 12px 20px; border-radius: 4px; font-size: 1rem; }
        .btn-action:hover { background: #d4ac0d; }

        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-top: 20px; }
        .preview-card { background: #252525; border: 1px solid #444; padding: 10px; text-align: center; border-radius: 4px; }
        .preview-img { width: 100%; height: 110px; object-fit: cover; background: #000; border-radius: 3px; }

        /* --- STYLES DU POPUP (MODAL) --- */
        .modal {
            display: none; /* Caché par défaut */
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.8); /* Fond noir semi-transparent */
            backdrop-filter: blur(5px);
        }
        .modal-content {
            background-color: #1a252f;
            margin: 5% auto; /* 5% du haut, centré */
            padding: 25px;
            border: 1px solid var(--accent);
            width: 90%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
            animation: slideDown 0.3s;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover { color: #fff; }
        @keyframes slideDown { from {transform: translateY(-50px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }
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
            <div>
                <button id="openModalBtn" class="btn-action" style="margin-right: 10px;">➕ Nouvelle Mission</button>
                <a href="index.php" style="color: #e74c3c; text-decoration: none; font-weight: bold;">[ DÉCONNEXION ]</a>
            </div>
        </div>

        <div id="missionModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3 style="margin-top: 0; color: var(--accent); text-align: center;">➕ Créer une nouvelle mission</h3>
                <form method="POST">
                    <label>Titre</label>
                    <input type="text" name="title" placeholder="Ex: Filature rue de la Paix" required>
                    
                    <label>Code & Statut</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="code" placeholder="Ex: OP-2026-XYZ" required style="flex: 1;">
                        <select name="status" style="flex: 1;">
                            <option value="En Cours">En Cours</option>
                            <option value="Urgent">Urgent</option>
                            <option value="Terminé">Terminé</option>
                            <option value="Classifié">Classifié</option>
                        </select>
                    </div>

                    <label>Description</label>
                    <textarea name="description" placeholder="Objectifs, suspects, notes..."></textarea>
                    
                    <button type="submit" name="add_mission" class="btn-action">ENREGISTRER</button>
                </form>
            </div>
        </div>

        <section>
            <h2 style="border-bottom: 2px solid #333; padding-bottom: 10px;">📋 Rapports de Missions</h2>
            <?php if (empty($missions)): ?>
                <div class="mission-card">Aucune mission assignée. Cliquez sur "Nouvelle Mission" pour commencer.</div>
            <?php else: ?>
                <?php foreach($missions as $m): ?>
                <div class="mission-card">
                    <div class="mission-header">
                        <div>
                            <div class="mission-title"><?php echo htmlspecialchars($m['title']); ?></div>
                            <div class="mission-meta">
                                🆔 <?php echo htmlspecialchars($m['investigation_code']); ?> &nbsp;|&nbsp; 
                                📅 <?php echo date("d/m/Y H:i", strtotime($m['creation_date'])); ?>
                            </div>
                        </div>
                        <span class="badge"><?php echo htmlspecialchars($m['status']); ?></span>
                    </div>
                    <?php if (!empty($m['description'])): ?>
                        <div class="mission-desc"><?php echo nl2br(htmlspecialchars($m['description'])); ?></div>
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
                        <div style="display: flex; gap: 10px;">
                            <input type="file" name="evidence" required style="margin-bottom:0;">
                            <button type="submit" class="btn-action" style="width: auto; margin-bottom:0;">Envoyer</button>
                        </div>
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
                    ❌ SERVEUR INACCESSIBLE <br>
                    <small>Chemin tenté : <?php echo $root_path; ?></small><br>
                    <small>Réponse Windows : <strong><?php echo htmlspecialchars($fs_error_details); ?></strong></small>
                </div>
            <?php endif; ?> 
        </section>
    </div>

    <script>
        // Récupération des éléments
        var modal = document.getElementById("missionModal");
        var btn = document.getElementById("openModalBtn");
        var span = document.getElementsByClassName("close")[0];

        // Ouvrir le popup au clic sur le bouton
        btn.onclick = function() {
            modal.style.display = "block";
        }

        // Fermer le popup au clic sur la croix (X)
        span.onclick = function() {
            modal.style.display = "none";
        }

        // Fermer le popup si on clique en dehors de la fenêtre
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>