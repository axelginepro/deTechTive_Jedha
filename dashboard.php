<?php
session_start();

// --- 0. CHARGEMENT DE LA SÉCURITÉ (CONFIG.PHP) ---
// On charge les accès BDD depuis le fichier sécurisé
if (!file_exists('config.php')) {
    die("Erreur critique : Le fichier de configuration 'config.php' est manquant.");
}
require_once 'config.php';

/**
 * ============================================================
 * 1. CONFIGURATION DE L'INFRASTRUCTURE (PARTIE FICHIERS)
 * ============================================================
 */
// Hostname de ton serveur de fichiers (ton domaine AD)
// (On laisse ça ici car c'est spécifique au dashboard)
$file_server_name = "detechtive.local"; 

// Chemin racine pour voir TOUS les partages du serveur (Chemin UNC)
$root_path = "\\\\" . $file_server_name . "\\"; 

$msg_status = "";

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

/**
 * ============================================================
 * 3. CONNEXION À LA BASE DE DONNÉES (MySQL)
 * ============================================================
 */ 
try {
    // Vérifier si le driver PDO MySQL est bien activé
    if (!extension_loaded('pdo_mysql')) {
        throw new Exception("Le driver 'pdo_mysql' est manquant. Activez-le dans le php.ini.");
    }

    // --- MISE A JOUR SÉCURISÉE ---
    // On utilise les constantes de config.php au lieu d'écrire en dur
    $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8";
    
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
        PDO::ATTR_TIMEOUT => 2,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $db_online = true;

} catch (Exception $e) {
    $db_online = false;
    // Affiche l'erreur technique seulement si nécessaire
    $msg_status = "⚠️ ERREUR BDD : " . $e->getMessage();
}

/**
 * ============================================================
 * 4. RÉCUPÉRATION DES MISSIONS (SÉGRÉGATION)
 * ============================================================
 */
$missions = [];
if ($db_online) {
    // Utilisation de requêtes préparées pour la sécurité (Injections SQL)
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
 * 5. GESTION DU SERVEUR DE FICHIERS (SMB/UNC)
 * ============================================================
 */
$dossiers_detectes = [];
// Vérification si le chemin réseau est accessible par le compte de service IIS
if (is_dir($root_path)) {
    $contenu = scandir($root_path);
    foreach ($contenu as $item) {
        // On liste les dossiers visibles et on ignore les partages système cachés ($)
        if ($item != "." && $item != ".." && !strpos($item, '$') && is_dir($root_path . $item)) {
            $dossiers_detectes[] = $item;
        }
    }
} else {
    // Correction de l'erreur "Serveur introuvable"
    $msg_status .= "<br>❌ Accès impossible au serveur de fichiers '$file_server_name'. Vérifiez les DNS et les permissions réseau.";
}

// ACTION : UPLOAD DANS LE DOSSIER SÉLECTIONNÉ
if (isset($_FILES['evidence']) && isset($_POST['target_folder'])) {
    $folder_selected = $_POST['target_folder'];
    $final_upload_dir = $root_path . $folder_selected . "\\";
    $file_name = basename($_FILES["evidence"]["name"]);
    $destination = $final_upload_dir . $file_name;

    if (move_uploaded_file($_FILES["evidence"]["tmp_name"], $destination)) {
        $msg_status = "✅ Preuve déposée avec succès dans : " . $folder_selected;
    } else {
        $msg_status = "❌ Échec de l'écriture réseau vers : " . $destination;
    }
}

// FEATURE : APERÇU DES FICHIERS DU DOSSIER COURANT
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
    <title>Detechtive Dashboard - <?php echo htmlspecialchars($nom_agent); ?></title>
    <style>
        :root { --accent: #f1c40f; --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .container { max-width: 900px; margin: auto; }
        .alert { padding: 15px; background: rgba(192, 57, 43, 0.9); border: 2px solid #e74c3c; border-radius: 5px; margin-bottom: 25px; font-weight: bold; }
        .header-flex { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 15px; margin-bottom: 30px; }
        .mission-card { background: var(--card); padding: 15px; border-left: 5px solid var(--accent); margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; margin-top: 20px; }
        .preview-card { background: #252525; border: 1px solid #444; padding: 10px; text-align: center; border-radius: 4px; }
        .preview-img { width: 100%; height: 110px; object-fit: cover; background: #000; margin-bottom: 8px; }
        select, button { width: 100%; padding: 12px; margin-bottom: 10px; background: #2c2c2c; color: white; border: 1px solid #444; border-radius: 4px; }
        .btn-upload { background: var(--accent); color: black; border: none; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-upload:hover { background: #d4ac0d; }
    </style>
</head>
<body>
    <div class="container">
        
        <?php if($msg_status): ?>
            <div class="alert"><?php echo $msg_status; ?></div>
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

        <hr style="margin: 45px 0; border: 0; border-top: 1px solid #333;">

        <section>
            <h2>📁 Coffre-fort Numérique : <?php echo $file_server_name; ?></h2>
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
                <div class="preview-grid">
                    <?php foreach ($apercus as $file): ?>
                        <div class="preview-card">
                            <?php if (in_array($file['ext'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                <?php 
                                    // Lecture sécurisée du fichier réseau en base64 pour l'affichage
                                    $img_data = base64_encode(file_get_contents($file['path']));
                                    $src = 'data:image/' . $file['ext'] . ';base64,' . $img_data;
                                ?>
                                <img src="<?php echo $src; ?>" class="preview-img">
                            <?php else: ?>
                                <div style="font-size: 3rem; margin-bottom: 10px;">📄</div>
                            <?php endif; ?>
                            <div style="font-size: 0.65rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
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