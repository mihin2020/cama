# Sécurité de l'application — OWASP Top 10 (2025)

Document de suivi : pour chaque risque, la mesure en place, où la trouver dans le code et le test qui la vérifie.
Les tests sont dans `tests/Feature` (lancer `php artisan test`).

| Risque | Mesure | Où | Test |
|---|---|---|---|
| **A01 Contrôle d'accès défaillant** | Rôles et permissions en base, vérifiés à chaque route ; un compte ne gère qu'un rôle de rang inférieur ; l'assuré n'accède qu'à ses fichiers ; les fichiers sont servis par des routes protégées (jamais de chemin interne envoyé au navigateur) ; adresses par UUID (non devinables) | `App\Support\AdminPermissions`, `Admin\Http\Middleware\…permission`, `StoredFileResponse`, trait `HasUuid` | `AdminRolesTest`, `AdminPermissionGovernanceTest`, `FileAccessTest`, `UuidRoutesTest` |
| **A02 Défaillances cryptographiques** | Mots de passe en bcrypt (12 tours) ; codes à usage unique stockés sous forme d'empreinte HMAC ; sessions chiffrées par défaut en production ; cookies `Secure` + `HttpOnly` + `SameSite=Lax` ; HSTS en HTTPS | `config/hashing.php`, `OneTimeCodeService`, `config/session.php`, `SecurityHeaders` | `OneTimeCodeTest`, `AdminAuthSecurityTest` |
| **A03 Chaîne d'approvisionnement** | `composer audit` et `npm audit` à 0 avertissement ; PHP ≥ 8.3.37 imposé par `composer.json` ; plus aucun script, police ou image chargé depuis un site tiers (tout est servi par le site) | `composer.json`, `package.json`, lot « zéro CDN » | contrôle manuel : aucune requête vers un domaine tiers (voir ci-dessous) |
| **A04 Conception non sécurisée** | Codes à 6 caractères jamais réutilisés, 5 essais, expiration 10 min ; envoi des e-mails après la réponse ; validation à deux niveaux | `OneTimeCodeService`, `DossierSupervisorAssignmentTest` | `OneTimeCodeTest`, `MailAfterResponseTest` |
| **A05 Mauvaise configuration / injection** | **CSP** stricte avec nonce par requête (voir ci-dessous) ; en-têtes `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `COOP` ; requêtes SQL paramétrées (Eloquent) ; sorties échappées (Vue), contenu éditorial assaini ; `APP_DEBUG=false` en production | `App\Http\Middleware\SecurityHeaders`, `SanitizesSections` | `OwaspHardeningTest`, `UploadAndPieceSecurityTest` |
| **A06 Composants vulnérables** | Voir A03 ; mises à jour via `npm audit fix` / `composer update` | — | — |
| **A07 Authentification** | Limite des tentatives (5 par IP+compte, 20 par compte, blocage 5 min) ; `throttle` sur connexion, 2FA, renvoi de code, mot de passe oublié, invitation, vérification d'e-mail ; **session renouvelée dès l'étape « en attente de 2FA »** (anti-fixation) puis à l'ouverture de session ; déconnexion des autres appareils au changement de mot de passe ; double authentification | `LoginThrottle`, `Auth\LoginController`, routes `admin.php` / `assure.php` | `AdminAuthSecurityTest`, `AssureSessionInvalidationTest`, `OwaspHardeningTest` |
| **A08 Intégrité des logiciels et des données** | Aucun script tiers ; téléversements contrôlés : PDF uniquement (type réel, en-tête, sans JavaScript ni fichier embarqué), photo en JPG/PNG/WebP vérifiée, nom normalisé | `Rules\PdfDocument`, `Rules\IdentityPhoto`, `UploadFilename` | `UploadAndPieceSecurityTest`, `UploadNamingAndPdfTest` |
| **A09 Journalisation et alertes** | Échecs de connexion, connexions bloquées, échecs de 2FA, accès refusés (403) et excès de requêtes (429) sont journalisés (IP, adresse, compte ; jamais de mot de passe ni de code) ; journal d'activité du studio et des dossiers | `App\Support\SecurityLog`, `LoginThrottle`, `ErrorPage` | `OwaspHardeningTest` |
| **A10 Gestion des erreurs** | Pages d'erreur personnalisées sans détail technique ; en production, aucune trace affichée | `App\Support\ErrorPage`, `resources/views/errors` | `ErrorPagesTest` |

## Politique de sécurité du contenu (CSP)

Envoyée à chaque réponse par `SecurityHeaders` :

```
default-src 'self'
script-src 'self' 'nonce-…'        ← un nonce différent à chaque requête
style-src 'self' 'nonce-…'
style-src-attr 'unsafe-inline'     ← seulement les attributs style="…" du contenu éditorial
img-src 'self' data: blob: https:  ← images https saisies dans le CMS
font-src 'self' data:
connect-src 'self'
frame-src 'self' blob:
object-src 'none'
base-uri 'self'
form-action 'self'
frame-ancestors 'self'
```

Conséquences pour le développement :

- Une balise `<script>` ou `<style>` en ligne doit porter `nonce="{{ $cspNonce }}"` (voir `resources/views/app.blade.php`). Les balises ajoutées par `@vite` et `@routes` le reçoivent déjà.
- Ne pas injecter de `<style>` ou de script par JavaScript (la barre de progression d'Inertia est donc fournie dans `resources/css/app.css`).
- L'aperçu du constructeur de pages lit le nonce dans `<meta name="csp-nonce">` pour son `<style>` en ligne.
- En développement avec `npm run dev`, l'adresse du serveur Vite (fichier `public/hot`) est ajoutée automatiquement, uniquement en environnement `local`.

## Vérifier qu'aucune ressource externe n'est chargée

1. `npm run build`, puis ouvrir le site avec les outils du navigateur (onglet Réseau) : toutes les requêtes doivent viser le domaine du site.
2. Aucune erreur « Content Security Policy » dans la console.

## Liste de contrôle avant mise en production

- `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` (site en HTTPS), `SESSION_ENCRYPT=true` (valeur par défaut en production).
- PHP ≥ 8.3.37 (ou 8.4.28) ; extensions `pdo_pgsql`, `fileinfo`.
- `composer audit` et `npm audit` sans avertissement.
- Sauvegardes de la base et du dossier `storage/app` (pièces des assurés) ; `storage/app/public` ou un stockage objet pour les images du site.
- Journaux (`storage/logs`) surveillés : rechercher « Sécurité — » pour les échecs de connexion et accès refusés.
