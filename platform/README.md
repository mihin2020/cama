# CAMA Platform — Laravel + Vue + Inertia

Application monolithique CAMA (sans starter kit Breeze/Jetstream).
Authentification **manuelle** avec deux guards séparés : `assure` et `admin`.

## Prérequis

- PHP 8.3+
- Composer
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
