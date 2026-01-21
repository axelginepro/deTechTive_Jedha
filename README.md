🕵️‍♂️ Detechtive Agency - Secure Infrastructure & Intranet
Projet de fin d'études - Certification AIS (Administrateur d'Infrastructures Sécurisées) - RNCP Niveau 6 > Jedha Bootcamp

📖 À propos
Detechtive Agency est un projet de mise en situation réelle déployant une infrastructure sécurisée complète pour une agence de détectives fictive. Le projet comprend la conception de l'architecture réseau, la virtualisation, la sécurisation des flux et le développement d'un intranet métier interconnecté aux services d'infrastructure (Active Directory, File Server, Base de données).

L'objectif est de démontrer la capacité à construire un environnement "Secure by Design" en segmentant le réseau et en chiffrant les communications critiques.

🏗️ Architecture & Infrastructure
L'infrastructure est entièrement virtualisée et simulée via GNS3. Elle repose sur une segmentation stricte pour limiter la surface d'attaque.

Topologie Réseau
L'architecture est divisée en plusieurs zones sécurisées via des VLANs, filtrés par un pare-feu pfSense :

VLAN 10 (Management & Sécurité) : 192.168.10.8/29

Serveur SIEM (Wazuh) pour la surveillance des logs.

Webterm d'administration.

VLAN 20 (Serveurs / DMZ Interne) : 192.168.10.16/28

Serveur Web : Apache/PHP (Héberge l'intranet).

Active Directory (SRV-AD-01) : Gestion centralisée des identités et des accès.

File Server : Stockage des preuves, droits gérés via l'AD.

Database : MariaDB (Données des missions).

VLAN 30 (Postes Clients) : 192.168.10.128/25

Workstations des agents (Windows).

Zone Externe :

Poste Attaquant (Kali Linux) pour les tests de pénétration.

Schéma de l'infrastructure
(Insérer ici l'image gns3.png ou ip.png fournie dans le repo)

🛠️ Stack Technique
Système & Réseau
Virtualisation : GNS3, VMware Workstation.

Pare-feu / Routeur : pfSense (Filtrage de paquets, Routing inter-VLAN).

OS Serveurs : Windows Server 2019 (AD, FS), Debian/Ubuntu (Web, DB).

SIEM : Wazuh (Détection d'intrusions).

Application Web (Intranet)
Une application développée "from scratch" pour interagir avec l'infrastructure :

Frontend : HTML5, CSS3 (Thème "Terminal/Hacker").

Backend : PHP Natif (Pas de framework pour une maîtrise totale des flux).

Base de Données : MariaDB / MySQL.

Connectivité Spéciale : Utilisation de commandes système (net use) via PHP pour monter des lecteurs réseaux sécurisés vers le File Server Windows.

🔒 Sécurité & Implémentations
Ce projet met l'accent sur la sécurité des données en transit et au repos :

Chiffrement de bout en bout :

Application Web accessible uniquement en HTTPS.

Liaison WebApp ↔ Base de données chiffrée en SSL/TLS (SHA256). Le code vérifie activement l'état du chiffrement (Ssl_cipher) avant de valider les transactions.

Gestion des Identités (IAM) :

Les dossiers partagés sur le File Server sont strictement cloisonnés.

L'accès aux fichiers se fait via une authentification SMB passée par l'application Web.

Protection Applicative :

Upload de fichiers sécurisé (Whitelist d'extensions, renommage automatique, anti-path traversal).

Nettoyage des entrées SQL (mysqli_real_escape_string, Requêtes préparées PDO).

🚀 Fonctionnalités de l'Intranet
Authentification Agent : Login sécurisé contre la base SQL.

Dashboard de Mission :

Création de nouvelles investigations.

Attribution de codes de mission et statuts (En cours, Urgent, Terminé).

Coffre-fort Numérique (File Server) :

Explorateur de fichiers intégré au navigateur.

Upload de preuves directement vers le serveur de fichiers Windows (au travers du réseau via SMB).

Visualisation des images et logs directement dans l'interface.

📂 Organisation du Projet
Gestion de projet : Trello (Suivi des tâches et sprint).

Conception : Excalidraw (Schémas d'architecture et adressage IP).

Versioning : Git & GitHub (Code source privé).

⚙️ Installation (Démo)
Pour reproduire l'environnement :

Importer l'infrastructure dans GNS3.

Configurer pfSense selon le plan d'adressage IP fourni.

Déployer la BDD : Importer le script SQL detechtive_db.sql dans MariaDB.

Configurer l'App :

Placer les fichiers PHP dans /var/www/html/.

Modifier config.php avec les IPs de votre infra GNS3.

Générer les certificats SSL pour la liaison MySQL et les placer dans le chemin défini (C:/webapp/... ou /etc/ssl/...).
