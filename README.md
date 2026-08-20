# Ecole2Nat'

Plugin WordPress de suivi pédagogique pour école de natation.

## Modules disponibles

- saisons ;
- catégories ;
- référentiel pédagogique : domaines et compétences ;
- bibliothèque d'exercices ;
- groupes ;
- nageurs ;
- évaluations progressives ;
- portail parents sécurisé ;
- distribution groupée des accès parents par email, coupons ou CSV ;
- synchronisation d’un classeur club avec saison cible ;
- maintenance et purge contrôlée des données ;
- portail Coach avec semaine type et évaluations rapides.

La synchronisation attend un onglet `Référentiel <catégorie>` par référentiel
(par exemple `Référentiel Némo`) avec les colonnes Domaine, Compétence et
Exercice(s). Les inscriptions ne sont importées que lorsque la colonne
`Renouvellement` contient explicitement `OUI` ou `NON`.

## Portail parents

1. Créez et publiez une page WordPress, par exemple **Mon parcours de natation**.
2. Placez-y uniquement le shortcode :

```text
[e2n_parent_report]
```

3. Utilisez **Ecole2Nat' → Accès parents** pour distribuer les accès d’un groupe par email, coupons ou CSV.
4. Les liens **Voir le parcours** du back-office permettent une prévisualisation administrateur sans exposer le code parent.
5. Personnalisez la signature des emails dans **Ecole2Nat' → Réglages** ; elle s'applique aussi aux renvois déclenchés depuis le portail Coach.

Le code n'est jamais conservé en clair : il est recalculé à la demande à partir de l'identifiant du nageur et d'un secret propre à l'installation. Il est créé automatiquement, reste identique lors des affichages et renvois, et ne change qu'après une réinitialisation explicite.

## Évaluations progressives

Le module Évaluations permet de suivre le niveau courant de chaque nageur sur les compétences du référentiel de la catégorie de son groupe. Les trois états disponibles sont : Non observé, En cours et Acquis. Chaque changement conserve sa date et le nom du coach dans un historique saisonnier.


## Portail Coach

Le shortcode `[e2n_coach_portal]` propose trois accès complémentaires aux nageurs : liste alphabétique avec recherche, classement par catégorie et semaine type permanente construite depuis les créneaux des groupes. Aucun créneau n'est associé à une date et aucune préparation de séance n'est requise. Depuis une fiche d'évaluation, un coach peut ouvrir une prévisualisation sécurisée de la fiche Parents, afficher et copier le code permanent, ou le renvoyer à l'adresse responsable enregistrée.

Un clic sur un créneau ouvre directement le groupe. Tous les comptes Coach peuvent mettre à jour les progressions de tous les groupes actifs, individuellement ou collectivement. Les titulaires habituels restent affichés à titre informatif.

Les changements sont enregistrés immédiatement et alimentent la chronologie de chaque compétence. Les informations médicales restent réservées au portail Coach et ne sont jamais exposées dans le portail Parents.

Le portail utilise une mise en page autonome, pensée comme une application mobile et tablette : navigation compacte et persistante, actions secondaires regroupées, résumé de progression et notes internes affichées à la demande. Il conserve les hooks WordPress nécessaires aux extensions et à la barre d'administration, mais ne reprend ni l'en-tête, ni le pied de page, ni les colonnes du thème actif.

Le portail Parents présente l'état courant de façon synthétique. Un contrôle replié sous chaque compétence permet d'afficher à la demande la date, le statut atteint et le coach ayant effectué chaque changement.

Comme le portail Coach, le portail Parents possède un gabarit autonome et responsive fourni par le plugin. La saisie du code, la fiche enfant et les prévisualisations Coach ou administrateur utilisent la même identité visuelle sans dépendre des fichiers du thème WordPress.

Le nom et le logo communs aux en-têtes Coach et Parents se configurent dans **Ecole2Nat' → Réglages**. Le logo est choisi dans la médiathèque WordPress ; en son absence, le monogramme E2N reste affiché.

La bibliothèque d'exercices reste disponible dans le back-office comme ressource pédagogique indépendante.

## Prérequis

- WordPress 6.0 ou supérieur ;
- PHP 8.1 ou supérieur ;
- Composer 2.

## Installation de développement

```bash
git clone https://github.com/elouf/ecole2nat.git
cd ecole2nat
composer install
```

## Construire une archive installable

```bash
composer build
```

Le ZIP est généré dans `build/` avec les dépendances de production. Il exclut
Git, les tests et la documentation de développement, et contient un dossier
racine `ecole2nat/` directement installable dans WordPress.

Placez ensuite le dépôt dans :

```text
wp-content/plugins/ecole2nat
```

Activez l'extension dans l'administration WordPress.

## Vérification de syntaxe

```bash
find src -name '*.php' -print0 | xargs -0 -n1 php -l
```

Les tests automatisés ciblés s’exécutent sans installation supplémentaire avec :

```bash
composer test
```

## Modèle de données

Voir [`docs/database.md`](docs/database.md).

## Synchronisation du classeur club

Le menu **Ecole2Nat' → Synchronisation** accepte un classeur `.xlsx` comprenant :

- `Inscriptions` ;
- `Groupes` ou `Catégories` ;
- `Référentiel`.

Les autres onglets et les colonnes comptables sont ignorés. Avant l'analyse, l'administrateur choisit la **saison cible** : la colonne Saison n'est donc pas nécessaire dans l'onglet Groupes. L'association d'un nageur à son groupe repose sur la concaténation de la catégorie et du créneau, par exemple `Dauphin` + `Lundi 17h15` → `Dauphin Lundi 17h15`.

L’onglet `Groupes` ou `Catégories` peut contenir une colonne facultative `Durée (min)`. Lorsqu’elle est renseignée, l’heure de fin est calculée depuis l’heure de début reconnue dans le nom du groupe. Lorsqu’elle est vide, une heure de fin déjà corrigée dans WordPress est conservée. Les groupes peuvent aussi être modifiés depuis **Ecole2Nat’ → Groupes → Modifier**.


## Maintenance

L'écran **Ecole2Nat' → Maintenance** permet à un administrateur de purger intégralement les données du plugin sans désinstaller Ecole2Nat'. La purge conserve le schéma de base de données et les options techniques de version, mais efface toutes les données métier, évaluations, accès parents et journaux de synchronisation.

Cette action est irréversible et exige une double confirmation explicite.

## Mise à jour du schéma

Ecole2Nat' compare automatiquement la version de base installée à `E2N_DB_VERSION`. Les migrations `dbDelta()` nécessaires sont appliquées au chargement après une mise à jour ; il n'est plus nécessaire de désactiver/réactiver l'extension pour ajouter les nouvelles colonnes.


## Saisons et historique pédagogique

Depuis la v0.9.0, les évaluations, le référentiel actif et les affectations de groupe sont historisés par saison. Une synchronisation Excel associe son référentiel à la saison cible choisie dans le back-office. Le portail parents permet de consulter les saisons précédentes sans altérer les acquis historiques.


## Synchronisation des informations nageur

Dans l’onglet `Inscriptions`, Ecole2Nat’ reconnaît notamment les colonnes `Info médicale` et `Droit à l'image`. Une cellule `Info médicale` non vide active uniquement un indicateur de santé : son texte n'est jamais stocké dans WordPress. Le droit à l’image accepte `OUI` ou `NON` (la cellule peut rester vide si l’information n’est pas connue). La colonne historique `Commentaire` est ignorée. Plusieurs emails ou téléphones responsables peuvent être séparés par `/` ou `;` ; ils sont conservés distinctement et les emails valides reçoivent chacun les envois Parents.
