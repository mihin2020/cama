# CAMA Platform — Laravel + Vue + Inertia

Application monolithique CAMA (sans starter kit Breeze/Jetstream).
Authentification **manuelle** avec deux guards séparés : `assure` et `admin`.

## Prérequis

- **PHP 8.3.37+ ou 8.4.28+** (versions corrigées des failles critiques CVSS 9.2 de 2026 :
  PHP2Shell CVE-2026-109618/109619, phpAfterShell CVE-2026-126714, CVE-2026-129289…).
  PHP 8.3.36 et antérieures, ainsi que 8.1 et plus anciennes, ne doivent plus être utilisées.
  Extensions requises : `pdo_pgsql` (ou `pdo_mysql`), `fileinfo`, `mbstring`, `openssl`.
  `composer install` refuse de s'exécuter (et l'application refuse de démarrer) si la version de PHP est trop ancienne.
- Composer 2 (avec TLS activé : ne pas utiliser `disable-tls`)
- Node.js 18+
- **MySQL 8** (base `cama`)

## Installation

```bash
# 1. Créer la base MySQL (phpMyAdmin, HeidiSQL ou ligne de commande)
CREATE DATABASE cama CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 2. Configurer .env (déjà prévu pour MySQL)
# DB_DATABASE=cama
# DB_USERNAME=root
# DB_PASSWORD=votre_mot_de_passe

# 3. Migrations + données de démo
php artisan migrate:fresh --seed

# 4. Front-end
npm install
npm run build
# ou en dev : npm run dev

# 5. Lancer le serveur
php artisan serve
```

## URLs

| Zone | URL |
|------|-----|
| Accueil | http://localhost:8000 |
| Connexion assuré | http://localhost:8000/espace-assure/connexion |
| Connexion admin | http://localhost:8000/admin/connexion |

## Comptes de démo (après seed)

| Rôle | E-mail | Mot de passe |
|------|--------|--------------|
| Assuré | issouf.traore@armee.bf | Demo2026! |
| Admin | admin@cama.bf | Demo2026! |
| Gestionnaire | gestionnaire@cama.bf | Demo2026! |

## Architecture auth

- **Pas de Breeze / Jetstream** — contrôleurs et vues écrits à la main
- Guard `assure` → table `assures` (N° CIM + N° Carte CAMA saisis à l'inscription)
- Guard `admin` → table `admin_users` (rôles : gestionnaire, superviseur, administrateur, direction)
- JSON MySQL (`json` Laravel) pour le futur Page Builder CMS

## Prototype HTML

La maquette statique reste à la racine du dépôt (`../index.html`, `../assure/`, etc.) pour référence visuelle pendant la migration.
