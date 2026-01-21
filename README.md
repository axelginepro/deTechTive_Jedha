# 🕵️‍♂️ Detechtive Agency - Secure Infrastructure & Intranet

![Status](https://img.shields.io/badge/Status-Completed-success)
![Certification](https://img.shields.io/badge/Certification-AIS_Niveau_6-blue)
![Stack](https://img.shields.io/badge/Tech-GNS3_%7C_PfSense_%7C_PHP_%7C_Active_Directory-orange)

> **Projet de fin d'études - Certification AIS (Administrateur d'Infrastructures Sécurisées)**
> *RNCP Niveau 6 - Jedha Bootcamp*

---

## 📖 À propos

**Detechtive Agency** est un projet de mise en situation réelle déployant une infrastructure sécurisée complète pour une agence de détectives fictive. Le projet comprend la conception de l'architecture réseau, la virtualisation, la sécurisation des flux et le développement d'un intranet métier interconnecté aux services d'infrastructure (Active Directory, File Server, Base de données).

🎯 **Objectif :** Démontrer la capacité à construire un environnement **"Secure by Design"** en segmentant le réseau et en chiffrant les communications critiques (SSL/TLS, SMB).

---

## 🏗️ Architecture & Infrastructure

L'infrastructure est entièrement virtualisée et simulée via **GNS3**. Elle repose sur une segmentation stricte pour limiter la surface d'attaque.

### 🗺️ Topologie Réseau
L'architecture est divisée en zones sécurisées via des VLANs, filtrés par un pare-feu **pfSense**.

| Zone | VLAN | CIDR | Services Hébergés |
| :--- | :---: | :--- | :--- |
| **Management & Sécurité** | `10` | `192.168.10.8/29` | Serveur SIEM (Wazuh), Webterm d'admin |
| **Serveurs (DMZ Interne)** | `20` | `192.168.10.16/28` | Web (Apache), AD (SRV-AD-01), File Server, DB (MariaDB) |
| **Postes Clients** | `30` | `192.168.10.128/25` | Workstations des agents (Windows) |
| **Zone Externe** | `-` | `WAN` | Poste Attaquant (Kali Linux) pour Pentest |

### 📸 Schémas
*(Aperçu de la topologie GNS3 et du plan d'adressage)*

![Architecture GNS3](gns3.png)
![Plan IP](ip.png)

---

## 🛠️ Stack Technique

* **Virtualisation :** GNS3, VMware.
* **Réseau & Sécurité :** pfSense (Firewalling, Routing), Wazuh (SIEM).
* **Systèmes :** Windows Server 2019 (AD, DNS, FS), Debian (Web), Kali Linux (Audit).
* **Web App (Intranet) :**
    * **Frontend :** HTML5 / CSS3 (Thème Terminal).
    * **Backend :** PHP Natif (Connexion sécurisée BDD & SMB).
    * **Database :** MariaDB (Chiffrement SSL forcée).

---

## 🔐 Implémentations Sécurité (AIS)

Ce projet met en avant des compétences spécifiques d'administration sécurisée :

1.  **Chiffrement des Flux :**
    * Liaison PHP ↔ MySQL chiffrée en **SSL (SHA256)**.
    * Site accessible en HTTPS uniquement.
2.  **Gestion des Identités :**
    * Authentification centralisée via **Active Directory**.
    * Cloisonnement des droits NTFS sur le serveur de fichiers.
3.  **Interopérabilité Sécurisée :**
    * L'application web monte dynamiquement des lecteurs réseaux sécurisés (`net use`) pour déposer les preuves directement sur le serveur Windows, sans les stocker sur le serveur web.

---

## 👤 Auteur

**[Ton Nom/Prénom]**
* *Lien LinkedIn*
* *Lien Portfolio*

_Projet réalisé dans le cadre de la formation Cybersecurity Jedha Bootcamp - 2026_
