# Recueil de besoins — contenus & référentiels CAMA

**Objet** : collecter auprès des métiers / communication / RH les éléments nécessaires au remplissage de la plateforme CAMA (site public, CMS, inscription assuré, exports).

| Champ | À renseigner |
|---|---|
| Date du recueil | … / … / …… |
| Interlocuteur métier | |
| Service / direction | |
| Contact (e-mail / tél.) | |
| Validateur final | |
| Version du document | 1.0 |

> **Consigne** : remplir les tableaux ci-dessous (ou joindre des fichiers Excel/CSV équivalents). Indiquer `N/A` si non applicable, et `À confirmer` si l’info existe mais n’est pas encore validée.

---

## 1. Images pour les carrousels (page d’accueil / bannières)

Chaque slide du carrousel nécessite une **image**, un **titre**, un **sous-titre**, et éventuellement un **lien** (bouton).

**Spécifications techniques recommandées**

| Critère | Valeur attendue |
|---|---|
| Format | JPG / JPEG / PNG / WEBP |
| Orientation | Paysage (horizontal) |
| Résolution cible | ≥ 1920 × 800 px (idéal) |
| Poids max. | ≤ 1,5 Mo par image |
| Droits | Images libres de droits ou propriété CAMA / Ministère |

### Tableau à remplir — slides carrousel

| N° | Titre (court) | Sous-titre / accroche | Nom du fichier image | Lien cible (URL ou page) | Libellé du bouton | Actif (Oui/Non) | Ordre d’affichage | Fourni le | Commentaire |
|---|---|---|---|---|---|---|---|---|---|
| 1 | | | | | | Oui | 1 | | |
| 2 | | | | | | Oui | 2 | | |
| 3 | | | | | | Oui | 3 | | |
| 4 | | | | | | | | | |
| 5 | | | | | | | | | |

**Livrables attendus** : fichiers images nommés clairement (ex. `carousel-01-sante-heros.jpg`) + ce tableau complété.

---

## 2. Coordonnées de contact & réseaux sociaux

Informations affichées sur la page **Contact**, le pied de page, et éventuellement les e-mails institutionnels.

| Élément | Valeur à fournir | Obligatoire | Commentaire |
|---|---|---|---|
| Nom de l’institution (affichage) | Caisse d’Assurance Maladie des Armées (CAMA) | Oui | |
| Adresse postale (siège) | | Oui | |
| Ville / pays | | Oui | |
| Téléphone principal | | Oui | Format international recommandé : +226 … |
| Téléphone secondaire / standard | | Non | |
| WhatsApp (si applicable) | | Non | |
| E-mail de contact public | | Oui | Ex. contact@cama.bf |
| E-mail réclamations | | Non | Si différent |
| E-mail administratif | | Non | |
| Horaires d’ouverture | | Oui | Ex. Lun–Ven 08h00–15h00 |
| Lien Facebook | | Non | URL complète |
| Lien X / Twitter | | Non | |
| Lien LinkedIn | | Non | |
| Lien YouTube | | Non | |
| Site web officiel (si autre) | | Non | |
| Coordonnées GPS siège (lat / long) | | Non | Pour la carte |
| Lien Google Maps | | Non | |

### Antennes / délégations régionales (si distinctes du siège)

| Nom de l’antenne | Ville | Adresse | Téléphone | E-mail | Horaires | Latitude | Longitude | Lien Maps | Publié (Oui/Non) |
|---|---|---|---|---|---|---|---|---|---|
| | | | | | | | | | |
| | | | | | | | | | |
| | | | | | | | | | |

---

## 3. Listes organisationnelles — Région / Corps / Service / Section / Sous-section

Ces listes alimentent l’**inscription assuré** et le **profil** (chaîne de rattachement administratif).

**Hiérarchie attendue**

```
Région militaire
 └── Corps (régiment / bataillon / état-major…)
      └── Service
           └── Section
                └── Sous-section (bureau…)
```

**Exemple (à titre d’illustration)**  
`3e Région Militaire — Centre-Nord (Kaya)` → `11e Régiment…` → `Service Administratif` → `Section Personnel` → `Bureau Solde`

### 3.1 Armées / catégories (référentiels connexes)

| Armées à conserver / ajouter | Commentaire |
|---|---|
| Armée de Terre | |
| Armée de l’Air | |
| Gendarmerie Nationale | |
| Sapeurs-Pompiers Militaires | |
| Autre : … | |

| Catégories de personnel | Actif (Oui/Non) |
|---|---|
| Officier | |
| Sous-officier | |
| Militaire du rang | |
| Personnel civil | |

### 3.2 Tableau maître — structure organisationnelle (à remplir exhaustivement)

> Astuce : vous pouvez dupliquer ce tableau dans Excel. Une ligne = un nœud feuille (sous-section), ou une ligne par niveau si vous préférez.

| ID (interne, optionnel) | Région militaire | Corps | Service | Section | Sous-section | Actif (Oui/Non) | Remarque |
|---|---|---|---|---|---|---|---|
| | Ex. 1re Région Militaire — Centre (Ouagadougou) | État-major de la 1re RM | Service Administratif | Section Personnel | Bureau Effectifs | Oui | |
| | | | | | Bureau Solde | Oui | |
| | | 11e Régiment d’Infanterie Commando (11e RIC) | Service Administratif | Section Personnel | | Oui | |
| | 2e Région Militaire — Hauts-Bassins (Bobo-Dioulasso) | | | | | | |
| | 3e Région Militaire — Centre-Nord (Kaya) | | | | | | |
| | 4e Région Militaire — Nord (Ouahigouya) | | | | | | |
| | … (ajouter toutes les régions / corps manquants) | | | | | | |

**Livrable préféré** : fichier Excel nommé `structure-organisationnelle-cama.xlsx` avec colonnes exactes :  
`region` · `corps` · `service` · `section` · `sous_section` · `actif` · `remarque`

---

## 4. Formats des champs d’inscription assuré

Objectif : figer le **format officiel** de chaque identifiant saisi à l’inscription, pour pouvoir valider automatiquement les saisies (longueur, préfixe, caractères autorisés, unicité).

> Aujourd’hui la plateforme accepte des textes libres (max. 50 caractères) avec des exemples indicatifs. **Les règles métier ci-dessous doivent être validées** avant de durcir les contrôles.

### 4.1 Identifiants administratifs (priorité haute)

| Champ (libellé écran) | Clé technique | Obligatoire | Exemple actuel (maquette) | Format officiel attendu (à préciser) | Longueur (min / max) | Préfixe imposé | Caractères autorisés | Unicité | Commentaire / source du numéro |
|---|---|---|---|---|---|---|---|---|---|
| Matricule militaire | `matricule` | Oui | `12345-X` | | / | | Ex. chiffres + tiret + lettre ? | Oui (unique) | |
| N° informatique | `numero_informatique` | Oui | `INF-4521` | | / | | | Non / Oui ? | |
| N° CIM | `numero_cim` | Oui | `CIM-104521` | | / | | | Non / Oui ? | |
| N° Carte CAMA | `numero_cama` | Oui | `CAMA-104521` | | / | | | Oui / Non ? | |
| N° IUP (Identifiant Unique de la Personne) | `numero_iup` | Non (optionnel) | libre | | / | | | Non / Oui ? | |

**À remplir pour chaque champ** — exemples de réponses attendues :

| Élément de format | Exemple de formulation métier |
|---|---|
| Structure | `CAMA-` + 6 chiffres |
| Masque / regex | `^CAMA-[0-9]{6}$` |
| Sensible à la casse | Oui / Non (forcer majuscules ?) |
| Espaces / tirets autorisés | Oui / Non |
| Message d’erreur utilisateur | Ex. « Le N° Carte CAMA doit être au format CAMA-XXXXXX » |

### 4.2 Identité & situation

| Champ | Obligatoire | Type / format attendu | Longueur max | Valeurs autorisées / remarques |
|---|---|---|---|---|
| Nom | Oui | Texte | 100 | Majuscules ? Accents OK ? |
| Prénom(s) | Oui | Texte | 150 | |
| Sexe | Oui | Liste | — | `Masculin` / `Féminin` (autres à prévoir ?) |
| Grade | Oui | Liste déroulante | 100 | Voir référentiel grades |
| Catégorie | Oui | Liste déroulante | 100 | Officier, Sous-officier, etc. |
| Armée | Oui | Liste déroulante | 150 | Voir référentiel armées |

### 4.3 Rattachement organisationnel

| Champ | Obligatoire | Format | Remarque |
|---|---|---|---|
| Région | Oui | Liste (libellé exact) | Chaîne dépendante : région → corps → service → section → sous-section |
| Corps | Non | Liste | |
| Service | Non | Liste | |
| Section | Non | Liste | |
| Sous-section | Non | Liste | |

### 4.4 Coordonnées & sécurité

| Champ | Obligatoire | Format attendu | Longueur max | Remarque |
|---|---|---|---|---|
| Téléphone(s) | Oui | International `+226…` ? | 255 | Plusieurs numéros possibles ? Séparateur ? |
| E-mail | Oui | E-mail valide | 255 | Unique |
| Personne à prévenir | Non | Texte libre | 200 | |
| Tél. personne à prévenir | Non | Même règle que téléphone | 255 | |
| Mot de passe | Oui | ≥ 12 car., maj/min, chiffre, symbole | — | Règle technique déjà en place |

### 4.5 Décisions à prendre sur les formats

| # | Question | Réponse métier |
|---|---|---|
| 1 | Le N° IUP doit-il devenir **obligatoire** ? | |
| 2 | Le N° informatique et le N° CIM sont-ils **uniques** par assuré ? | |
| 3 | Faut-il **normaliser** automatiquement (majuscules, suppression d’espaces) ? | |
| 4 | Existe-t-il un document / circulaire définissant ces formats officiels ? (joindre la référence) | |
| 5 | En cas de format inconnu (personnel civil, gendarmerie…), y a-t-il des variantes ? | |

**Livrable préféré** : Excel `formats-champs-inscription-cama.xlsx` (voir aussi `modeles-recueil/formats-champs-inscription-cama.csv`).

---

## 5. Exports CSV / Excel — colonnes attendues

La plateforme propose déjà des exports. Merci de **valider** les colonnes ci-dessous et d’indiquer les colonnes **manquantes** ou **à retirer**.

### 5.1 Export « Membres enrôlés »

| Colonne actuelle | Conserver (Oui/Non) | Renommer en… | Commentaire métier |
|---|---|---|---|
| Référence | | | |
| Assuré | | | |
| Matricule | | | |
| Bénéficiaire | | | |
| Lien (lien de parenté) | | | |
| Statut | | | |
| Soumis le | | | |
| Gestionnaire | | | |

**Colonnes supplémentaires demandées**

| Nom de colonne | Description / règle | Priorité (Haute/Moyenne/Basse) |
|---|---|---|
| | | |
| | | |

### 5.2 Export « Dossiers d’enrôlement »

| Colonne actuelle | Conserver (Oui/Non) | Renommer en… | Commentaire métier |
|---|---|---|---|
| Référence | | | |
| Assuré principal | | | |
| Membre | | | |
| Statut | | | |
| Date soumission | | | |
| Date décision | | | |
| Motif refus | | | |
| Gestionnaire | | | |

**Colonnes supplémentaires demandées**

| Nom de colonne | Description / règle | Priorité |
|---|---|---|
| | | |

### 5.3 Autres exports souhaités (hors périmètre actuel)

| Nom de l’export | Public cible | Colonnes souhaitées (liste) | Format (CSV / Excel / PDF) | Fréquence |
|---|---|---|---|---|
| Ex. Journal d’audit | Admin | date, acteur, action, cible… | CSV | À la demande |
| Ex. Liste des assurés | | | | |
| Ex. Newsletter abonnés | | email, nom, segment, statut… | CSV | |

### 5.4 Fiche dossier PDF

| Élément à afficher sur la fiche PDF | Obligatoire (Oui/Non) | Commentaire |
|---|---|---|
| Identité assuré (nom, prénom, matricule, grade, armée) | | |
| N° CAMA | | |
| Identité du membre / ayant droit | | |
| Pièces jointes listées | | |
| Historique des décisions | | |
| Autre : … | | |

---

## 6. Ensemble des fichiers documentaires (ressources téléchargeables)

Catégories prévues sur le site (page Ressources) :

1. Formulaires  
2. Guides  
3. Attestations  
4. Guide du prescripteur  
5. Textes réglementaires et législatifs  

### Tableau à remplir — documents à publier

| Titre du document | Catégorie | Description courte | Format (PDF/DOCX…) | Version / date | Fichier fourni (Oui/Non + nom) | Publier (Oui/Non) | Remplace un ancien doc ? |
|---|---|---|---|---|---|---|---|
| Formulaire d’enrôlement d’un ayant droit | Formulaires | | PDF | | | | |
| Formulaire de demande de remboursement | Formulaires | | PDF | | | | |
| Guide de l’assuré CAMA | Guides | | PDF | | | | |
| Attestation de prise en charge (modèle) | Attestations | | PDF | | | | |
| Guide du prescripteur | Guide du prescripteur | | PDF | | | | |
| Décret portant création de la CAMA | Textes réglementaires et législatifs | | PDF | | | | |
| | | | | | | | |
| | | | | | | | |

**Livrables attendus** : fichiers originaux (PDF de préférence) + ce tableau. Indiquer clairement le document **FIF** et tout texte législatif officiel à mettre en ligne.

---

## 7. Centres de santé & partenaires (avec localisation)

Affichés sur la page Contact / cartographie et éventuellement la page Services.

**Types possibles** : `Centre de santé` · `Partenaire` · `Antenne CAMA` · autre (à préciser)

| Nom | Type | Ville | Adresse | Téléphone | E-mail | Horaires | Description courte | Latitude | Longitude | Lien Google Maps | Image / logo (fichier) | Publié (Oui/Non) |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Ex. Hôpital Militaire de Ouagadougou | Centre de santé | Ouagadougou | | | | | | 12.3714 | -1.5197 | | | Oui |
| | | | | | | | | | | | | |
| | | | | | | | | | | | | |
| | | | | | | | | | | | | |

**Livrable préféré** : Excel `centres-et-partenaires-cama.xlsx` avec les mêmes colonnes.

---

## 8. Synthèse des livrables à remettre

| # | Livrable | Format | Statut (Fourni / Partiel / Manquant) | Date promise | Responsable |
|---|---|---|---|---|---|
| 1 | Images carrousel + textes associés | Images + tableau §1 | | | |
| 2 | Coordonnées contact & réseaux | Tableau §2 | | | |
| 3 | Structure Région → Sous-section | Excel / tableau §3 | | | |
| 4 | Formats champs inscription (N° IUP, info, CAMA…) | Excel / tableau §4 | | | |
| 5 | Validation / extension colonnes exports | Tableau §5 | | | |
| 6 | Fichiers documentaires (formulaires, guides, etc.) | PDF + tableau §6 | | | |
| 7 | Centres de santé & partenaires + GPS | Excel / tableau §7 | | | |

---

## 9. Questions ouvertes / points à trancher

| # | Question | Réponse métier | Décision |
|---|---|---|---|
| 1 | Combien de slides maximum sur le carrousel d’accueil ? | | |
| 2 | Les partenaires sans GPS doivent-ils apparaître sur la carte ? | | |
| 3 | La structure organisationnelle est-elle exhaustive au niveau national ? | | |
| 4 | Qui valide la publication des textes réglementaires ? | | |
| 5 | Faut-il d’autres catégories de ressources documentaires ? | | |
| 6 | Les exports doivent-ils être filtrables (période, statut, région) ? | | |
| 7 | Quels formats officiels pour matricule, N° informatique, CIM, CAMA, IUP ? | | |

---

## 10. Validation

| Rôle | Nom | Signature / visa | Date |
|---|---|---|---|
| Demandeur / chef de projet | | | |
| Métier (contenu) | | | |
| Communication | | | |
| Technique (intégration plateforme) | | | |

---

*Document généré pour le projet plateforme CAMA — à utiliser comme checklist de collecte avant intégration CMS / paramétrage.*
