# Spécification CMS — Page Builder type Elementor

## Document de cadrage pour implémentation sur un nouveau projet

**Version :** 1.0  
**Date :** 19 juin 2026  
**Référence :** Module « Studio de contenu » du projet CAMA (maquette HTML/JS)  
**Objectif :** Fournir un cahier des charges complet pour reproduire une gestion de contenu institutionnelle avec éditeur visuel par blocs, équivalent WordPress + Elementor.

---

## 1. Contexte et objectifs

### 1.1 Problème à résoudre

Les équipes communication / institutionnelles doivent pouvoir :

- Créer et modifier des **pages web** sans compétence technique
- Gérer la **navigation**, les **actualités**, les **bannières**, les **chiffres clés** et la **FAQ**
- Prévisualiser le rendu **desktop, tablette et mobile**
- Travailler en **brouillon** puis **publier** en toute autonomie

### 1.2 Périmètre fonctionnel

| Inclus | Hors périmètre (phase 1) |
|--------|--------------------------|
| Studio de contenu (CMS) | Back-office métier (CRM, dossiers, etc.) |
| Éditeur visuel par blocs | E-commerce |
| Gestion des menus | Multilingue avancé |
| Actualités / blog | Workflow de validation multi-niveaux |
| Bannière carousel | Médiathèque avancée (crop, CDN) |
| Chiffres clés & FAQ | |

### 1.3 Utilisateurs cibles

| Rôle | Droits |
|------|--------|
| **Superviseur / Responsable contenu** | Accès complet au Studio CMS |
| **Administrateur technique** | Accès CMS + paramètres avancés |
| **Gestionnaire métier** | Pas d'accès CMS (back-office séparé) |
| **Visiteur public** | Lecture seule du site publié |

---

## 2. Architecture globale

### 2.1 Séparation des modules

```
┌─────────────────────────────────────────────────────────┐
│                    BACK-OFFICE                          │
├─────────────────────────┬───────────────────────────────┤
│   Back-office métier    │   Studio de contenu (CMS)     │
│   (dossiers, users…)    │   (pages, menus, actus…)      │
└─────────────────────────┴───────────────────────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │   API + BDD     │
                    └─────────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │   Site public   │
                    └─────────────────┘
```

Le CMS doit être un **module indépendant** avec sa propre navigation latérale, connecté au même système d'authentification que le back-office global.

### 2.2 Flux de données

1. L'éditeur sauvegarde le contenu en **JSON structuré** (arbre Section → Colonne → Widget)
2. Le statut **brouillon / publié** est géré côté serveur
3. Le site public **consomme** uniquement le contenu publié via API ou rendu SSR/SSG
4. Les modules structurés (actualités, FAQ, slides…) ont leurs propres entités, distinctes des pages builder

### 2.3 Stack technique recommandée (à valider)

| Couche | Recommandation |
|--------|----------------|
| Backend | Node.js (NestJS) ou Laravel |
| Base de données | PostgreSQL |
| API | REST ou GraphQL |
| Builder (admin) | React + TypeScript |
| Site public | Next.js / Nuxt (SSG ou SSR) |
| Auth | JWT + rôles (RBAC) |
| Médias | S3 / stockage objet + table `media` |

---

## 3. Modules du Studio de contenu

### 3.1 Vue d'ensemble (Dashboard CMS)

**Écran :** tableau de bord du studio

**Contenu :**

- Bandeau d'accueil avec CTA « Ouvrir l'éditeur de pages »
- **4 indicateurs** : articles publiés, pages gérées, slides bannière, questions FAQ
- **Grille de raccourcis** vers chaque module
- **Journal d'activité récente** (qui a modifié quoi, quand)

**Critères d'acceptation :**

- [ ] Chaque carte redirige vers le module correspondant
- [ ] Les compteurs reflètent les données réelles en BDD
- [ ] Responsive mobile (sidebar repliable)

---

### 3.2 Gestion des pages (liste)

**Écran :** liste type WordPress « Pages »

**Fonctionnalités :**

| Action | Détail |
|--------|--------|
| Lister | Titre, slug, statut, date modification, nb sections |
| Rechercher | Par titre ou slug |
| Filtrer | Tous / En ligne / Brouillon |
| Créer | Titre → redirection vers l'éditeur |
| Renommer | Titre + slug (slug auto-généré, modifiable) |
| Dupliquer | Copie avec suffixe `-copie`, statut brouillon |
| Publier / Dépublier | Toggle statut |
| Supprimer | Confirmation + retrait du menu si liée |
| Éditer | Lien vers l'éditeur visuel |

**Modèle de données — `Page` :**

```json
{
  "id": "uuid",
  "title": "À propos",
  "slug": "apropos",
  "status": "draft | published",
  "sections": [],
  "seo": {
    "metaTitle": "",
    "metaDescription": "",
    "ogImage": ""
  },
  "createdAt": "ISO8601",
  "updatedAt": "ISO8601",
  "publishedAt": "ISO8601 | null",
  "authorId": "uuid"
}
```

**Endpoints API suggérés :**

```
GET    /api/cms/pages
POST   /api/cms/pages
GET    /api/cms/pages/:id
PATCH  /api/cms/pages/:id
DELETE /api/cms/pages/:id
POST   /api/cms/pages/:id/duplicate
PATCH  /api/cms/pages/:id/publish
PATCH  /api/cms/pages/:id/unpublish
```

---

### 3.3 Éditeur de pages (Page Builder)

**Écran :** éditeur plein écran, 3 panneaux (bibliothèque | canvas | propriétés)

#### 3.3.1 Hiérarchie de composition (modèle Elementor)

```
Page
 └── Section[]           ← bandeau horizontal (fond, padding, largeur)
      └── Column[]       ← colonnes en % (50/50, 33/67, etc.)
           └── Widget[]  ← bloc atomique de contenu
```

Chaque nœud possède :

- `uid` : identifiant unique stable
- `type` : section | column | widget type
- `settings` / `style` / `advanced` : objets de configuration
- `content` : données éditoriales (widgets uniquement)

#### 3.3.2 Interface — barre supérieure

| Élément | Fonction |
|---------|----------|
| Retour studio | Navigation |
| Sélecteur de page | Changer de page sans quitter l'éditeur |
| Badge statut | Brouillon / En ligne |
| Nouvelle page | Création rapide |
| Undo / Redo | Historique session (min. 50 étapes) |
| Navigateur | Arbre Section → Colonne → Widget |
| Templates | Bibliothèque de sections prédéfinies |
| Preview device | Desktop / Tablette / Mobile |
| Aperçu | Modal iframe plein écran |
| Exporter | HTML statique (optionnel phase 1) |
| Publier | Sauvegarde + passage statut `published` |

#### 3.3.3 Panneau gauche — Bibliothèque de widgets

Widgets organisés par catégorie, avec recherche et **drag & drop** vers le canvas.

**Catégorie « Base » :**

| Widget | Champs contenu |
|--------|----------------|
| Titre | Texte, balise (H1–H4, p) |
| Texte | Contenu riche (WYSIWYG) |
| Bouton | Libellé, URL, variante (primary/secondary/outline/ghost), icône |
| Image | Source, alt, ratio (16:9, carré, 4:3, libre) |
| Icône | Nom icône, taille px |
| Encart icône | Icône + titre + texte |
| Séparateur | — |
| Espace | Hauteur px |

**Catégorie « Composants » :**

| Widget | Champs contenu |
|--------|----------------|
| Cartes | Nb colonnes (2/3/4), liste { icône, titre, texte } |
| Chiffres clés | Liste { valeur, libellé } |
| Accordéon / FAQ | Liste { question, réponse } |
| Onglets | Liste { titre, contenu } |
| Témoignage | Citation, auteur, fonction, avatar |
| Galerie | Nb colonnes, liste images |
| Vidéo | URL embed (YouTube, etc.) |
| Appel à l'action (CTA) | Titre, texte, bouton, lien |
| Formulaire contact | Titre, libellé bouton (envoi = phase 2) |
| Alerte / Encadré | Type (info/success/warning/error), message |

**Catégorie « Avancé » :**

| Widget | Champs contenu |
|--------|----------------|
| HTML libre | Code HTML (migration contenu existant) |

#### 3.3.4 Panneau droit — Propriétés (3 onglets)

**Onglet Contenu** (widgets uniquement)  
Champs dynamiques selon le schéma du widget (text, textarea, richtext, select, image, icon, repeater…).

**Onglet Style**  
Commun à section, colonne et widget :

- Alignement (gauche / centre / droite)
- Couleur texte et fond (palette thème + custom)
- Padding et margin (4 côtés)
- Bordure (épaisseur, couleur)
- Ombre (none / sm / md / lg / xl)
- Arrondi (px)
- Largeur max (px)

Spécifique **Section** :

- Largeur contenu : centré (boxed ~1100px) ou pleine largeur
- Type de fond : couleur / dégradé / image
- Hauteur minimale (px)
- Espacement entre colonnes (px)

Spécifique **Colonne** :

- Alignement vertical (haut / centre / bas)

**Onglet Avancé** :

- Animation d'entrée : none / fade / slide up / zoom
- Visibilité responsive : masquer sur desktop / tablette / mobile
- Classe CSS personnalisée
- Ancre HTML (`id`) pour liens `#section`

#### 3.3.5 Responsive (3 breakpoints)

| Device | Largeur cible | Clé settings |
|--------|---------------|--------------|
| Desktop | ≥ 1024px | `style` (défaut) |
| Tablette | 768–1023px | `style._tablet` |
| Mobile | < 768px | `style._mobile` |

Cascade : desktop → tablette → mobile (override).  
L'éditeur doit permettre de basculer le device actif pour ajuster les réglages par breakpoint.

#### 3.3.6 Interactions canvas

- Clic pour sélectionner (section / colonne / widget)
- Barre d'outils contextuelle au survol : réglages, dupliquer, supprimer
- Sections : monter, descendre, ajouter colonne, dupliquer, supprimer
- Drag & drop depuis bibliothèque → insertion dans colonne
- Drag & drop interne → réordonnancement widgets
- Ajout section → modal choix structure (100%, 50/50, 33/33/34, 33/67, 67/33, 4×25%)
- Zone vide colonne → message « Glissez un élément ici »
- Auto-save à chaque modification (debounce 500 ms)

#### 3.3.7 Templates de sections prédéfinis

| Template | Composition |
|----------|-------------|
| Bannière + texte | H1 centré + paragraphe |
| 3 atouts | Titre + widget Cartes (3 items) |
| Chiffres clés | Widget Stats |
| Image + texte | Col 50% image + col 50% titre/texte/bouton |
| Bandeau CTA | Widget CTA pleine largeur |
| FAQ | Titre + widget Accordéon |
| Témoignages | 2 colonnes × témoignage |
| Contact | Col texte + col formulaire |

#### 3.3.8 Aperçu et export

- **Aperçu** : iframe avec document HTML complet (styles thème, fonts, animations)
- **Export HTML** (optionnel) : génération fichier `.html` téléchargeable
- **Import** (optionnel) : importer HTML existant dans widget « HTML libre »

#### 3.3.9 Pattern technique — Widget Registry

Chaque widget est un objet extensible :

```typescript
interface WidgetDefinition {
  type: string;
  label: string;
  icon: string;
  category: 'Base' | 'Composants' | 'Avancé';
  defaults: {
    content: Record<string, unknown>;
    style: Record<string, unknown>;
  };
  contentSchema: ControlField[];
  extraStyleSchema?: ControlField[];
  render(widget: WidgetNode, context: RenderContext): string;
}

type ControlField = {
  key: string;
  type: 'text' | 'textarea' | 'richtext' | 'select' | 'color' |
        'image' | 'icon' | 'number' | 'range' | 'spacing' |
        'align' | 'seg' | 'repeater' | 'code' | 'visibility';
  label: string;
  options?: [string, string][];
  responsive?: boolean;
  when?: (settings: object) => boolean;
};
```

Le rendu public et l'export utilisent la **même fonction `render()`** par widget.

#### 3.3.10 Endpoints API éditeur

```
GET    /api/cms/pages/:id/sections
PUT    /api/cms/pages/:id/sections     ← sauvegarde complète
POST   /api/cms/pages/:id/publish
GET    /api/cms/pages/:id/preview      ← HTML rendu
GET    /api/cms/widget-registry        ← liste widgets + schémas
```

---

### 3.4 Menus du site

**Écran :** gestion navigation header (type WordPress « Apparence → Menus »)

**Fonctionnalités :**

- Aperçu live du header public
- Ajouter des **pages publiées** (multi-sélection)
- Ajouter des **liens personnalisés** (label + URL)
- **Drag & drop** pour réordonner
- **Indentation** pour créer des sous-menus (profondeur 0 ou 1)
- Monter / descendre par boutons

**Modèle — `MenuItem` :**

```json
{
  "id": "uuid",
  "type": "page | custom",
  "pageId": "uuid | null",
  "label": "Accueil",
  "url": "/accueil | null",
  "depth": 0,
  "sortOrder": 1
}
```

**Endpoints :**

```
GET    /api/cms/menu
PUT    /api/cms/menu
```

Le site public consomme `GET /api/public/menu` (cacheable).

---

### 3.5 Actualités

**Écran :** gestion articles / communiqués

**Champs :**

| Champ | Type |
|-------|------|
| Titre | text |
| Slug | text (auto) |
| Catégorie | select |
| Statut | Brouillon / Publié / Archivé |
| Auteur | auto (user connecté) |
| Date publication | datetime |
| Image à la une | media |
| Résumé | textarea |
| Contenu | richtext |
| À la une (featured) | boolean |

**Fonctionnalités :** CRUD, recherche, filtre statut/catégorie, aperçu article.

**Endpoints publics :**

```
GET /api/public/articles
GET /api/public/articles/:slug
```

---

### 3.6 Bannière d'accueil (Carousel)

**Écran :** gestion slides du hero page d'accueil

**Champs par slide :**

| Champ | Type |
|-------|------|
| Titre | text |
| Sous-titre | textarea |
| Image | media |
| Lien | URL |
| Libellé bouton | text |
| Ordre | number |
| Actif | boolean |

**Fonctionnalités :** CRUD, réordonnancement, toggle actif/inactif.

---

### 3.7 Chiffres clés

**Écran :** statistiques affichées sur la page d'accueil

**Champs :**

| Champ | Type |
|-------|------|
| Valeur | number |
| Suffixe | text (% , k, +…) |
| Libellé | text |
| Icône | icon picker |
| Ordre | number |

---

### 3.8 FAQ

**Écran :** questions fréquentes

**Champs :**

| Champ | Type |
|-------|------|
| Question | text |
| Réponse | richtext |
| Catégorie | select |
| Ordre | number |
| Publié | boolean |

Le site public peut filtrer par catégorie et n'afficher que les entrées publiées.

---

## 4. Modèle de données (schéma BDD)

```sql
-- Pages builder
CREATE TABLE cms_pages (
  id            UUID PRIMARY KEY,
  title         VARCHAR(255) NOT NULL,
  slug          VARCHAR(255) UNIQUE NOT NULL,
  status        VARCHAR(20) DEFAULT 'draft',
  sections_json JSONB NOT NULL DEFAULT '[]',
  seo_json      JSONB DEFAULT '{}',
  author_id     UUID REFERENCES users(id),
  created_at    TIMESTAMPTZ DEFAULT NOW(),
  updated_at    TIMESTAMPTZ DEFAULT NOW(),
  published_at  TIMESTAMPTZ
);

-- Menu
CREATE TABLE cms_menu_items (
  id          UUID PRIMARY KEY,
  type        VARCHAR(20) NOT NULL,
  page_id     UUID REFERENCES cms_pages(id) ON DELETE SET NULL,
  label       VARCHAR(255) NOT NULL,
  url         VARCHAR(500),
  depth       SMALLINT DEFAULT 0,
  sort_order  INT DEFAULT 0
);

-- Actualités
CREATE TABLE cms_articles (
  id          UUID PRIMARY KEY,
  slug        VARCHAR(255) UNIQUE NOT NULL,
  title       VARCHAR(500) NOT NULL,
  category    VARCHAR(100),
  status      VARCHAR(20) DEFAULT 'draft',
  author_id   UUID REFERENCES users(id),
  published_at TIMESTAMPTZ,
  image_url   VARCHAR(500),
  excerpt     TEXT,
  body_html   TEXT,
  featured    BOOLEAN DEFAULT FALSE,
  created_at  TIMESTAMPTZ DEFAULT NOW(),
  updated_at  TIMESTAMPTZ DEFAULT NOW()
);

-- Slides bannière
CREATE TABLE cms_slides (
  id          UUID PRIMARY KEY,
  title       VARCHAR(255),
  subtitle    TEXT,
  image_url   VARCHAR(500),
  link_url    VARCHAR(500),
  link_label  VARCHAR(100),
  sort_order  INT DEFAULT 0,
  active      BOOLEAN DEFAULT TRUE
);

-- Chiffres clés
CREATE TABLE cms_stats (
  id          UUID PRIMARY KEY,
  value       DECIMAL,
  suffix      VARCHAR(20),
  label       VARCHAR(255),
  icon        VARCHAR(50),
  sort_order  INT DEFAULT 0
);

-- FAQ
CREATE TABLE cms_faq (
  id          UUID PRIMARY KEY,
  question    TEXT NOT NULL,
  answer_html TEXT NOT NULL,
  category    VARCHAR(100),
  sort_order  INT DEFAULT 0,
  published   BOOLEAN DEFAULT TRUE
);

-- Journal d'activité (optionnel)
CREATE TABLE cms_activity_log (
  id          UUID PRIMARY KEY,
  user_id     UUID REFERENCES users(id),
  action      VARCHAR(100),
  entity_type VARCHAR(50),
  entity_id   UUID,
  metadata    JSONB,
  created_at  TIMESTAMPTZ DEFAULT NOW()
);
```

---

## 5. Rendu public (site vitrine)

### 5.1 Pages builder

Route dynamique : `/[slug]` ou `/pages/[slug]`

Pipeline :

1. `GET /api/public/pages/:slug` → JSON sections (uniquement si `status = published`)
2. Moteur de rendu parcourt l'arbre et appelle `WidgetRegistry.render()` pour chaque widget
3. Injection dans layout site (header menu + footer)

### 5.2 Modules structurés

| Module | Consommation |
|--------|--------------|
| Menu | Header global |
| Slides | Section hero `index` |
| Stats | Section compteurs `index` |
| Articles | Page listing + `/actualites/:slug` |
| FAQ | Page services ou section dédiée |

### 5.3 Cache

- Pages publiées : cache CDN 5–15 min + invalidation à la publication
- Menu / FAQ / slides : cache 1–5 min

---

## 6. Authentification et permissions

| Permission | Superviseur | Admin technique |
|------------|:-----------:|:---------------:|
| Voir dashboard CMS | ✅ | ✅ |
| Gérer pages | ✅ | ✅ |
| Publier pages | ✅ | ✅ |
| Gérer menus | ✅ | ✅ |
| Gérer actualités | ✅ | ✅ |
| Gérer bannière / stats / FAQ | ✅ | ✅ |
| Supprimer pages | ✅ | ✅ |
| Paramètres CMS avancés | ❌ | ✅ |

Middleware API : vérifier JWT + rôle avant chaque endpoint `/api/cms/*`.

---

## 7. UX / UI — exigences transverses

- **Design system** cohérent avec le back-office existant (sidebar sombre, header blanc, cartes)
- **Responsive admin** : sidebar repliable mobile, tables → cartes sur petit écran
- **Toasts** de confirmation après chaque action
- **Confirmations** avant suppression
- **États vides** explicites (« Aucune page », « Glissez un élément ici »)
- **Indicateur sauvegarde** : « Enregistré » / « Enregistrement… »
- **Raccourcis clavier** : Ctrl+Z / Ctrl+Y (undo/redo)
- **Accessibilité** : labels ARIA, contraste, navigation clavier dans l'éditeur

---

## 8. Phases de livraison suggérées

### Phase 1 — MVP (6–8 semaines)

- [ ] Auth + rôles CMS
- [ ] CRUD pages (liste + statuts)
- [ ] Page builder : Section/Colonne/Widget (widgets Base + Cartes + CTA + HTML)
- [ ] Responsive editor (3 breakpoints)
- [ ] Templates (4 minimum)
- [ ] Aperçu iframe
- [ ] Publication + rendu public dynamique
- [ ] Menus du site

### Phase 2 — Contenu enrichi (3–4 semaines)

- [ ] Widgets Composants complets (FAQ, stats, galerie, vidéo…)
- [ ] Actualités CRUD + rendu public
- [ ] Bannière carousel
- [ ] Chiffres clés + FAQ modules
- [ ] Médiathèque (upload images)
- [ ] Journal d'activité

### Phase 3 — Production (2–3 semaines)

- [ ] SEO (meta par page)
- [ ] Révisions / historique versions
- [ ] Export HTML
- [ ] Cache + invalidation
- [ ] Tests E2E éditeur
- [ ] Documentation utilisateur

---

## 9. Critères d'acceptation globaux

1. Un superviseur peut créer une page, la composer visuellement, prévisualiser sur mobile et la publier **sans aide technique**
2. Une page publiée est visible sur le site public en **< 30 secondes** (hors cache CDN)
3. Le menu public reflète les modifications **immédiatement** après sauvegarde
4. L'éditeur supporte **au minimum 15 types de widgets**
5. Undo/redo fonctionne sur **50 actions** minimum
6. Aucune régression responsive sur le rendu public (mobile-first)
7. Toutes les API CMS sont **protégées** par authentification

---

## 10. Référence implémentation CAMA (maquette)

Fichiers source de référence (projet CAMA, HTML/JS statique) :

| Fichier | Rôle |
|---------|------|
| `admin/cms/cms-data.js` | API données pages + menu (localStorage) |
| `admin/cms/page-builder.html` | Éditeur visuel complet (~1100 lignes) |
| `admin/cms/pages.html` | Liste et gestion pages |
| `admin/cms/menus.html` | Gestion navigation |
| `admin/cms/dashboard.html` | Dashboard studio |
| `admin/cms/actualites.html` | CRUD articles |
| `admin/cms/banniere.html` | CRUD slides |
| `admin/cms/chiffres-cles.html` | CRUD statistiques |
| `admin/cms/faq.html` | CRUD FAQ |
| `site-content.js` | Lecteur public (slides, articles, FAQ…) |

**Limitation maquette CAMA :** persistance localStorage, pas de rendu public automatique à la publication. L'implémentation cible doit corriger cela avec API + moteur de rendu serveur.

---

## 11. Annexes

### A. Structures de colonnes supportées

```
[100]
[50, 50]
[33, 33, 34]
[33, 67]
[67, 33]
[25, 25, 25, 25]
```

### B. Types de contrôles panneau propriétés

`text`, `textarea`, `richtext`, `select`, `color`, `image`, `icon`, `number`, `range`, `spacing`, `align`, `seg`, `repeater`, `code`, `visibility`

### C. Animations d'entrée

`none`, `fade`, `up` (slide up), `zoom` — déclenchées via Intersection Observer côté public

### D. Palette couleurs (exemple thème institutionnel)

Primary `#9e001f`, Secondary `#006e27`, Tertiary `#745b00`, Surface `#fbf9f8`, Error `#ba1a1a`  
(Les tokens sont configurables par projet via design system.)

---

## 12. Contact / validation

| | |
|---|---|
| **Document rédigé pour** | Équipe de développement du nouveau projet |
| **Basé sur** | Module Studio de contenu CAMA v2026 |
| **Prochaine étape** | Revue technique + estimation + choix stack |

---

*Fin du document*
