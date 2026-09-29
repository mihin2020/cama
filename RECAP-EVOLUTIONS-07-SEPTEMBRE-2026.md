# Récapitulatif des évolutions du 07 septembre 2026

Document destiné à l’implémentation des mêmes fonctionnalités dans le projet Laravel (`platform/`).  
Portée initiale : prototype HTML (`admin/`, `assure/`, pages publiques).

---

## 1. Connexion assuré — 2FA par e-mail

**Contexte :** le code 2FA était envoyé, mais l’interface de saisie n’était pas visible / claire après e-mail + mot de passe.

### À reproduire côté Laravel

- Après login avec `deux_fa_active`, afficher clairement l’étape de saisie du code (même page ou écran dédié bien visible).
- Codes **à 4 chiffres**, **jamais réutilisés** pour un même assuré (historique des codes déjà émis).
- Message de succès avec e-mail masqué.
- Renvoyer le code / annuler la 2FA.
- Envoi e-mail asynchrone si SMTP réel (éviter le blocage UI).

### Fichiers HTML / JS de référence

- `assure/espace-assure` équivalent → `platform` : `Login.vue`, `AssureLogin2faService`, etc.
- Prototype : logique déjà côté Laravel dans une session antérieure ; à vérifier en parité.

---

## 2. Mentions institutionnelles — Ministère

**Remplacer partout (HTML public) :**

| Ancien | Nouveau |
|--------|---------|
| Ministère de la Défense | **Ministère de la Guerre et de la Défense patriotique** |

### Fichiers touchés (prototype)

- `site-shell.js` (footer)
- `apropos.html`

### Laravel

- Aligner textes CMS / seed / pages publiques (`builderConstants`, contenus seedés, footer site).

---

## 3. Accueil — « La CAMA en chiffres »

**Supprimer :** `250k` — Bénéficiaires couverts  

**Remplacer par :**

| Valeur | Libellé | Icône |
|--------|---------|--------|
| **13** | Régions couvertes au Burkina Faso | `map` |

### Fichiers prototype

- `site-content.js` (`DEFAULT_CHIFFRES` + migration localStorage)
- `admin/cms/chiffres-cles.html`

### Laravel

- Seed / table `cms_key_figures` (ou équivalent) : mettre à jour le chiffre clé concerné.

---

## 4. Mentions légales

**Page :** `mention_legales.html`

### Suppressions

- Section **Éditeur du Site** (siège, contacts, etc.)
- Section **Hébergement** (ANPTIC, etc.)
- Liens de navigation associés

### Renommage

| Ancien | Nouveau |
|--------|---------|
| Droits des Militaires | **Droits des assurés** |

### Laravel

- Page légale / CMS : retirer éditeur & hébergeur ; renommer la section droits.

---

## 5. Ressources documentaires

**Page :** `ressources.html` (via `site-content.js`)

| Ancien | Nouveau |
|--------|---------|
| Textes législatifs | **Textes réglementaires et législatifs** |

Également la catégorie des documents associés (ex. décret de création).

### Laravel

- Catégories de ressources CMS + seed.

---

## 6. Inscription assuré — N° informatique

**Page :** `inscription-assure.html`

- Champ **N° informatique** : **obligatoire** (label `*`, attribut `required`, validation étape 2).

### Laravel

- `Registration.vue` / `RegisterAssureRequest` : rendre `numero_informatique` required.

---

## 7. Filiation « Enfant adopté » + pièces

**Pages :** `assure/ajouter-membre.html`, `admin/parametres.html`

### Nouveau type de filiation

- **Enfant adopté** (activable / désactivable / renommable côté admin)

### Pièces par défaut si « Enfant adopté »

1. Acte de naissance *(obligatoire)*
2. **Certificat de tutelle** *(obligatoire)*

### Config admin (onglet Membres rattachés)

- Liste des filiations enfants (biologique, du conjoint, adopté)
- Pour chaque filiation : actif, libellé, pièces (libellé + obligatoire oui/non + ajout/suppression)

### Laravel

- Settings plateforme / matrice de pièces du wizard famille
- Option filiation `Enfant adopté` + pièces associées
- Filtre dossiers admin : option « Enfant adopté »

---

## 8. Photo d’identité des membres

**Pages :** `assure/ajouter-membre.html`, `admin/parametres.html`

### Formulaire assuré

- Upload **photo** pour conjoint et/ou enfant
- Formats : JPEG / PNG / WebP
- Position : **en tout premier** sur la fiche (sous le titre), format **compact** (portrait ~88×110 px)
- Jointe au dossier comme pièce « Photo du membre » à la soumission

### Config admin

| Réglage | Description |
|---------|-------------|
| Photo conjoint | Activer / désactiver + **obligatoire** ou non |
| Photo enfant | Activer / désactiver + **obligatoire** ou non |

Par défaut : activée, **optionnelle** pour les deux.

### Laravel

- Settings `membre_photo` (ou équivalent)
- Champ upload dans le wizard famille
- Validation conditionnelle si `required`

---

## 9. FIF signée — exigence configurable

**Pages :** `admin/parametres.html` (nouvel onglet **FIF signée**), `assure/ajouter-membre.html`

### Comportement

| État | Effet |
|------|--------|
| **Désactivée** (défaut) | Section « 4. FIF signée » masquée ; **pas** de blocage à la soumission ; pas de pièce FIF jointe |
| **Activée** | Section visible ; génération PDF + upload scan **obligatoires** avant soumission |

### Laravel

- Setting `fif_signee_requise` (bool, default `false`)
- Conditionner UI wizard + validation soumission lot familial
- Admin : écran / onglet paramètres dédié

---

## 10. Âge enfant & certificat de scolarité

**Remplace** l’ancien blocage dur « âge > plafond → impossible d’enrôler ».

### Nouvelle règle

1. **Âge seuil** configurable (défaut **26** ans) — plus de plafond bloquant à 26.
2. Toggle admin : **Exiger le certificat au-delà / à partir du seuil** (défaut : activé).
3. Libellé de la pièce configurable (défaut : « Certificat de scolarité »).

### Formulaire enfant

- Si règle **activée** et **âge ≥ seuil** → bandeau d’info + pièce **Certificat de scolarité** obligatoire.
- Si âge **< seuil** ou règle **désactivée** → pas de certificat.
- L’enfant **peut être soumis** quel que soit l’âge, dès que les pièces requises sont fournies.
- Recalcul dynamique dès changement de la date de naissance.

### Laravel

- Settings : `age_max_enfant` (seuil), `certificat_scolarite_actif`, `certificat_scolarite_label`
- Wizard : pièce conditionnelle selon âge calculé
- Ne plus refuser purement sur `age > 26`

---

## 11. Identifiants de démo (rappel)

Mot de passe commun : **`Demo2026!`**

| Espace | Exemples |
|--------|----------|
| Assuré | `issouf.traore@armee.bf`, etc. |
| Admin | `admin@cama.bf`, `gestionnaire@cama.bf`, `superviseur@cama.bf` |

---

## Ordre suggéré d’implémentation Laravel

1. Settings plateforme (FIF, photos, scolarité, filiations / pièces)  
2. Wizard famille assuré (filiation adopté, photo, scolarité, FIF conditionnelle)  
3. Inscription (`numero_informatique` required)  
4. Contenu public (ministère, chiffres clés, mentions légales, catégories ressources)  
5. Parité UI maquettes HTML ↔ Inertia/Vue  

---

## Fichiers prototype principaux à consulter

| Domaine | Fichiers |
|---------|----------|
| Paramètres admin | `admin/parametres.html` |
| Données / settings | `assure/assure-data.js` |
| Ajout membres | `assure/ajouter-membre.html` |
| Inscription | `inscription-assure.html` |
| Mentions légales | `mention_legales.html` |
| Chiffres / ressources | `site-content.js`, `apropos.html`, `site-shell.js` |

---

*Généré le 08 septembre 2026 — base des demandes du 07 septembre 2026.*
