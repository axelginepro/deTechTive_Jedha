<?php
session_start();

// --- SÉCURITÉ : Vérifier si l'agent est connecté ---
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}

$agent_id_session = $_SESSION['agent_id'];
$nom_agent = $_SESSION['agent_name'];

// --- 1. CONFIGURATION ---
$bdd_ip = "192.168.10.11";
$file_server_name = "file-server"; 
$msg_status = "";
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
    $msg_status = "⚠️ Erreur BDD : " . $e->getMessage();
}

// --- 3. RÉCUPÉRATION DES MISSIONS SÉGRÉGUÉES ---
$missions = [];
if ($db_online) {
    /* LOGIQUE : On récupère l'ID de l'équipe de l'agent, 
       puis on affiche les investigations liées à cette équipe uniquement.
    */
    $sql = "SELECT i.title, i.status, i.investigation_code 
            FROM investigations i
            INNER JOIN agents a ON i.team_id = a.team_id
            WHERE a.id = ? 
            ORDER BY i.creation_date DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$agent_id_session]);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- 4. LECTURE DES PARTAGES (FILE SERVER) ---
$dossiers_detectes = [];
if (is_dir($root_path)) {
    $contenu = scandir($root_path);
    foreach ($contenu as $item) {
        if ($item != "." && $item != ".." && is_dir($root_path . $item)) {
            $dossiers_detectes[] = $item;
        }
    }
}

// --- 5. ACTION : UPLOAD ---
if (isset($_FILES['evidence']) && isset($_POST['target_folder'])) {
    $folder_selected = $_POST['target_folder'];
    $final_upload_dir = $root_path . $folder_selected . "\\";
    $file_name = basename($_FILES["evidence"]["name"]);
    $destination = $final_upload_dir . $file_name;

    if (move_uploaded_file($_FILES["evidence"]["tmp_name"], $destination)) {
        $msg_status = "✅ Preuve envoyée avec succès dans : " . $folder_selected;
    } else {
        $msg_status = "❌ Erreur d'écriture dans //".$file_server_name."/".$folder_selected;
    }
}

// --- 6. FEATURE APERÇU ---
$current_view = isset($_POST['target_folder']) ? $_POST['target_folder'] : "";
$apercus = [];
if ($current_view && is_dir($root_path . $current_view)) {
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
    <title>Dashboard - <?php echo htmlspecialchars($nom_agent); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-top: 20px; }
        .preview-card { background: #252525; border: 1px solid var(--border-color); padding: 10px; text-align: center; }
        .preview-img { width: 100%; height: 100px; object-fit: cover; border-bottom: 1px solid #333; margin-bottom: 5px; background: #111; }
        .file-icon { font-size: 3rem; display: block; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <?php if($msg_status): ?>
            <div style="padding: 10px; background: rgba(241, 196, 15, 0.2); border: 1px solid #e74c3c; margin-bottom: 20px; font-size: 0.8rem;">
                <?php echo $msg_status; ?>
            </div>
        <?php endif; ?>

        <div class="header-flex">
            <h1>Dossiers : <?php echo htmlspecialchars($nom_agent); ?></h1>
            <a href="index.php" style="color: #e74c3c; text-decoration: none; border: 1px solid; padding: 5px 10px;">DECONNEXION</a>
        </div>

        <section>
            <h2>📋 Vos Investigations (Sécurisées)</h2>
            <?php if (empty($missions)): ?>
                <div class="mission-card"><div><strong>Aucune mission affectée à votre équipe.</strong></div></div>
            <?php else: ?>
                <?php foreach($missions as $m): ?>
                <div class="mission-card">
                    <div>
                        <strong><?php echo htmlspecialchars($m['title']); ?></strong><br>
                        <small>Code : <?php echo htmlspecialchars($m['investigation_code']); ?></small>
                    </div>
                    <span class="status-badge"><?php echo htmlspecialchars($m['status']); ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <hr style="border: 0; border-top: 1px solid #333; margin: 40px 0;">

        <section>
            <h2>📁 Coffre-fort : <?php echo $file_server_name; ?></h2>
            <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                <select name="target_folder" onchange="this.form.submit()" required style="width: 100%; padding: 10px; margin-bottom: 10px; background: #222; color: white; border: 1px solid #444;">
                    <option value="">-- Sélectionner un dossier --</option>
                    <?php foreach($dossiers_detectes as $folder): ?>
                        <option value="<?php echo htmlspecialchars($folder); ?>" <?php echo ($current_view == $folder) ? 'selected' : ''; ?>>
                            📂 <?php echo htmlspecialchars($folder); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="file" name="evidence" required>
                <button type="submit" style="background: var(--accent-color); color: black; border: none; padding: 10px; width: 100%; cursor: pointer; font-weight: bold;">
                    TRANSFÉRER
                </button>
            </form>

            <?php if ($current_view && !empty($apercus)): ?>
                <div class="preview-grid">
                    <?php foreach ($apercus as $file): ?>
                        <div class="preview-card">
                            <?php if (in_array($file['ext'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                <?php 
                                    $img_data = base64_encode(file_get_contents($file['path']));
                                    $src = 'data:image/' . $file['ext'] . ';base64,' . $img_data;
                                ?>
                                <img src="<?php echo $src; ?>" class="preview-img">
                            <?php else: ?>
                                <span class="file-icon">📄</span>
                            <?php endif; ?>
                            <div style="font-size: 0.6rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?php echo htmlspecialchars($file['name']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>