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

## Fichiers envoyés et déploiement

Les fichiers envoyés depuis l'application ne sont **jamais** rangés dans `public/` (dossier remplacé à
chaque déploiement) :

| Fichiers | Où | Accès |
|---|---|---|
| Pièces des assurés (PDF, photos) | `storage/app/private/assures/<N° FIF>/` | uniquement par l'application, après contrôle des droits |
| Images, documents, médiathèque, logo du site public | `storage/app/public/site/…` (disque `SITE_UPLOADS_DISK`) | public, à l'adresse `/storage/site/…` |

À prévoir à l'hébergement :

1. **Conserver `storage/`** d'un déploiement à l'autre (dossier partagé / volume). Sans cela, les fichiers sont perdus.
2. `php artisan storage:link` : sert `/storage` directement par le serveur web (plus rapide). Sans ce lien,
   Laravel sert les fichiers lui-même : le site fonctionne, un peu plus lentement.
3. Hébergement sans disque persistant (conteneurs, plateformes éphémères) : `SITE_UPLOADS_DISK=s3` (et les
   variables `AWS_*`). Les pièces des assurés, elles, doivent rester sur un disque privé persistant.
4. Si le site contient déjà des fichiers dans `public/images/cms`, `public/documents/cms`, `public/media/cms`,
   `public/images/partners` ou `public/media/branding` : `php artisan uploads:move-to-storage` les reprend et
   corrige les adresses (puis `--delete-originals` après vérification).

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
