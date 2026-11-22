# Dimensionnement de la base de données et test de montée en charge

> **État : mesures de la base réalisées (section 6) ; test web complet (k6) à réaliser.** Le test web n'a pas pu être
> exécuté sur la machine de développement (ni Docker, ni k6). Les valeurs de la section « Valeurs de départ » sont des
> **estimations de départ**, pas des résultats : elles doivent être confirmées (ou corrigées) par les mesures de la
> section « Résultats », à remplir après l'exécution du test.

## 1. Ce qui est fourni

| Élément | Fichier |
|---|---|
| Pile proche de la production : nginx + PHP-FPM 8.3 (opcache) + PostgreSQL 17 (`pg_stat_statements`) | `tests/Load/docker/` |
| Jeu de données : 50 000 assurés, ~150 000 dossiers, notifications, connexions (insertion par lots) | `database/seeders/LoadTestSeeder.php` |
| Scénarios k6 : pages publiques, parcours assuré, parcours gestionnaire | `tests/Load/public.js`, `assure.js`, `admin.js` |
| Index des requêtes fréquentes (notifications non lues, journal, dossiers par assuré et statut) | migration `2026_11_23_140000_add_load_related_indexes` |

`php artisan serve` est mono-processus : il fausserait toute mesure, d'où la pile Docker (PHP-FPM avec plusieurs processus).

## 2. Exécuter le test

Depuis la racine du projet (Docker Desktop et k6 installés) :

```bash
C=tests/Load/docker/docker-compose.yml
docker compose -f $C up -d --build
docker compose -f $C exec app php artisan migrate --force
docker compose -f $C exec -e LOAD_TEST_CONFIRM=1 -e LOAD_ASSURES=50000 app php artisan db:seed --class=LoadTestSeeder --force

# un scénario à la fois ; paliers 10 → 50 → 100 → 200 utilisateurs simultanés (VUS_MAX pour changer le plafond)
docker compose -f $C run --rm k6 run /scripts/public.js
docker compose -f $C run --rm k6 run /scripts/assure.js
docker compose -f $C run --rm k6 run /scripts/admin.js
```

Le seeder refuse de tourner sans `LOAD_TEST_CONFIRM=1` ou en production : **jamais sur une base réelle**.
Les comptes de charge ont la 2FA désactivée et l'e-mail vérifié (mot de passe `Charge2026!`).

Faire varier ensuite `PHP_FPM_CHILDREN` (docker-compose) et refaire l'essai pour trouver le point d'équilibre
processus PHP / connexions PostgreSQL.

## 3. Mesures à relever pendant chaque palier

- **k6** : débit (requêtes/s), p95 et p99 par page (tag `name`), taux d'erreurs.
- **PostgreSQL** (pendant et après l'essai) :

```sql
-- connexions actives
SELECT state, count(*) FROM pg_stat_activity WHERE datname = 'cama' GROUP BY state;
-- requêtes les plus coûteuses
SELECT calls, round(mean_exec_time::numeric, 2) AS ms_moy, round(total_exec_time::numeric) AS ms_total, left(query, 90)
FROM pg_stat_statements ORDER BY total_exec_time DESC LIMIT 15;
-- taille des tables et index
SELECT relname, pg_size_pretty(pg_total_relation_size(relid)) AS total, n_live_tup
FROM pg_stat_user_tables ORDER BY pg_total_relation_size(relid) DESC LIMIT 15;
-- cache : doit rester > 99 %
SELECT round(100.0 * sum(blks_hit) / nullif(sum(blks_hit + blks_read), 0), 2) FROM pg_stat_database WHERE datname = 'cama';
```

- **Machine** : CPU et mémoire du conteneur `db` et du conteneur `app` (`docker stats`).

## 4. Valeurs de départ (estimations à confirmer)

Raisonnement : une page de l'application demande 5 à 15 requêtes SQL courtes ; une connexion PostgreSQL est utilisée
pendant la durée de la requête PHP, pas pendant le temps de réflexion de l'utilisateur. Avec 200 utilisateurs
« simultanés » (qui lisent 1 à 3 secondes entre deux pages), on attend environ 20 à 60 requêtes PHP réellement en cours.

| Paramètre | Valeur de départ | Pourquoi |
|---|---|---|
| Processus PHP-FPM (`pm.max_children`) | 16 à 32 par serveur | un processus ≈ 60 à 100 Mo ; plus de processus que de cœurs n'accélère rien |
| `max_connections` PostgreSQL | 100 à 200 | ≥ processus PHP × nombre de serveurs + tâches de maintenance |
| Pool de connexions (PgBouncer, mode transaction) | à ajouter dès que plusieurs serveurs PHP partagent la base | évite d'ouvrir une connexion PostgreSQL par processus |
| `shared_buffers` | 25 % de la mémoire vive | cache de PostgreSQL |
| `effective_cache_size` | 50 à 75 % de la mémoire vive | aide le planificateur à choisir les index |
| `work_mem` | 8 à 16 Mo | tris et regroupements des listes ; attention : par opération et par connexion |
| `maintenance_work_mem` | 256 Mo | création d'index, `VACUUM` |
| CPU / mémoire de la base | 2 à 4 cœurs, 4 à 8 Go | volume modeste : tout l'index tient en mémoire |

**Volumétrie estimée** (ordre de grandeur, à vérifier avec `pg_total_relation_size` après le seeder) : environ 1 à 2 Ko
par assuré, 1 Ko par dossier (hors fichiers), 0,3 Ko par notification → 50 000 assurés et 150 000 dossiers pèsent
quelques centaines de Mo. **Les pièces justificatives (PDF, 5 Mo maximum chacune) dominent largement** : prévoir environ
5 Mo × nombre de pièces (ex. 150 000 dossiers × 2 pièces ≈ 1,5 To au pire, souvent 10 fois moins) sur le disque ou le
stockage objet de `storage/app`, et sauvegarder ce dossier avec la base.

**Rétention** : la table `sessions` grossit à chaque visiteur connecté ; planifier `php artisan model:prune` ou une
purge des sessions expirées, de `assure_connexions` (journal des connexions) et de `one_time_codes` (codes expirés).

## 5. Résultats (à remplir)

| Scénario | Utilisateurs simultanés | Débit (req/s) | p95 (ms) | p99 (ms) | Erreurs | Connexions PG actives (max) | Observations |
|---|---|---|---|---|---|---|---|
| public | 100 | | | | | | |
| public | 200 | | | | | | |
| assuré | 100 | | | | | | |
| assuré | 200 | | | | | | |
| gestionnaire | 50 | | | | | | |
| gestionnaire | 100 | | | | | | |

Seuils visés (définis dans `tests/Load/common.js`) : moins de 1 % d'erreurs, p95 < 1,5 s, p99 < 3 s.

Après l'essai, consigner ici : les requêtes les plus lentes de `pg_stat_statements`, les index ajoutés si besoin, la
valeur finale retenue pour chaque paramètre du tableau de la section 4 et la configuration serveur recommandée.

## 6. Mesures réalisées : base PostgreSQL seule (sans serveur web)

Réalisées sur la machine de développement (PostgreSQL 19, Windows, base `cama_load`, 50 000 assurés, 150 000 dossiers,
127 Mo) avec `php tests/Load/db-benchmark.php`. Ce test exécute les vraies fonctions de l'application ; il ne mesure ni
nginx, ni PHP-FPM, ni le réseau.

| Scénario (une requête isolée) | Moyenne | Remarque |
|---|---|---|
| Assuré : tableau de bord | 13 à 22 ms | |
| Assuré : connexion | 4 ms | |
| Admin : liste des assurés avec recherche | 833 ms → **198 ms** | après les index trigrammes (migration `…_add_search_indexes_postgres`) |
| Admin : liste des assurés sans recherche | 460 à 495 ms | ≈ 83 requêtes SQL par page (à réduire) |
| **Gestionnaire : page Dossiers (≈ 7 500 dossiers)** | **78 s** | **plante avec la limite mémoire PHP par défaut (128 Mo)** |

Parallèle, 8 processus PHP pendant 20 s (62 % tableau de bord, 25 % connexion, 13 % listes admin) : 104 requêtes/s,
aucune erreur, latence moyenne 76 ms, p95 409 ms, p99 477 ms, 8 connexions actives au maximum, cache PostgreSQL 99,98 %.

### Constats

1. **La page Dossiers charge tous les dossiers visibles en mémoire** (`AdminDossierService::scopedDossiers` puis
   `groupFamilies`). Inutilisable au-delà de quelques centaines de dossiers par utilisateur ; impossible pour un compte
   qui voit tout (150 000). Il faut paginer côté serveur par famille et calculer les compteurs en SQL.
2. La liste des assurés exécute environ 4 requêtes par ligne (profil FIF, dossiers) : à regrouper.
3. Les recherches « contient » exigent les index trigrammes sur PostgreSQL (fournis).