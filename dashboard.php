<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de Bord Enquêteur</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header-flex">
            <h1>Dossiers en cours : Agent Demey</h1>
            <a href="index.php" style="color: var(--accent-color); text-decoration: none;">Terminer le service</a>
        </div>

        <section>
            <h2>📋 Missions Assignées (BDD)</h2>
            <div class="mission-card">
                <div>
                    <strong>Affaire #402 - Vol de données industrielles "où est le baton de la mort ?"</strong><br>
                    <small>Cible : Corporation X - Localisation : Lyon</small>
                </div>
                <span class="status-badge">EN COURS</span>
            </div>

            <form action="#" method="POST" style="margin-top: 20px;">
                <input type="text" placeholder="Description du nouveau dossier..." required>
                <button type="submit">Enregistrer la mission</button>
            </form>
        </section>

        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 40px 0;">

        <section>
            <h2>📁 Preuves Numériques (File Server)</h2>
            <p>Déposez ici les scans ou les fichiers logs récupérés.</p>
            <form action="#" method="POST" enctype="multipart/form-data">
                <input type="file" name="evidence">
                <button type="submit" style="background: #e74c3c; color: white;">Uploader la preuve</button>
            </form>
        </section>

        <footer style="margin-top: 50px; font-size: 0.8rem; color: #666;">
            Système connecté à : <strong>Active Directory DC-01</strong> | Stockage : <strong>NFS-SHARE-01</strong>
        </footer>
    </div>
</body>
</html>