# Ecole2Nat'

Plugin WordPress de suivi et de préparation pédagogique pour école de natation.

## Modules disponibles

- saisons ;
- catégories ;
- référentiel pédagogique : domaines et compétences ;
- bibliothèque d'exercices ;
- groupes ;
- nageurs ;
- séances types avec parties, exercices, durées et consignes ;
- évaluations progressives ;
- portail parents sécurisé ;
- distribution groupée des accès parents par email, coupons ou CSV ;
- synchronisation d’un classeur club avec saison cible ;
- maintenance et purge contrôlée des données ;
- portail Coach avec planning, séance terrain, présences et évaluations rapides.

## Portail parents

1. Créez et publiez une page WordPress, par exemple **Mon parcours de natation**.
2. Placez-y uniquement le shortcode :

```text
[e2n_parent_report]
```

3. Utilisez **Ecole2Nat' → Accès parents** pour distribuer les accès d’un groupe par email, coupons ou CSV.
4. Les liens **Voir le parcours** du back-office permettent une prévisualisation administrateur sans exposer le code parent.

Le code n'est jamais conservé en clair. Il n'est affiché dans l'administration qu'après sa génération ou sa régénération. Un renvoi génère donc un nouveau code et invalide l'ancien.

## Séances types

Le module permet de :

- créer et modifier une séance ;
- organiser la séance en parties ;
- ajouter des exercices depuis la bibliothèque ;
- définir une durée et des consignes propres à chaque utilisation ;
- réutiliser plusieurs fois le même exercice avec des consignes différentes ;
- réordonner ou retirer les exercices ;
- renommer, déplacer ou supprimer les parties ;
- calculer automatiquement les durées ;
- dupliquer une séance complète ;
- imprimer une fiche A4.

## Évaluations progressives

Le module Évaluations permet de suivre le niveau courant de chaque nageur sur les compétences du référentiel de la catégorie de son groupe. Les trois états disponibles sont : Non observé, En cours et Acquis. Les évaluations sont mises à jour au fil de l'eau, sans campagne ni calendrier imposé.


## Portail Coach et séance terrain

Le shortcode `[e2n_coach_portal]` alimente l’espace frontend des coachs. Tous les coachs peuvent consulter les groupes actifs ; les modifications sont réservées aux coachs titulaires du groupe et aux remplaçants explicitement affectés pour la date du jour.

Les remplacements sont gérés depuis **Ecole2Nat' → Coachs**. Ils associent un coach, un groupe et une date précise. Le remplaçant peut préparer la séance avant cette date. Les présences, évaluations et actions terrain ne deviennent modifiables que le jour prévu, puis le droit expire automatiquement sans modifier les titulaires permanents.

Depuis un créneau, un titulaire ou le remplaçant autorisé pour la date peut :

- affecter ou changer la séance prévue pour la date ;
- créer une séance pour le créneau ou dupliquer la séance prévue, puis modifier cette copie (objectifs, parties, exercices, durées et consignes) avec autosauvegarde et recherche rapide dans la bibliothèque ;
- conserver explicitement une adaptation ponctuelle comme séance type réutilisable ;
- marquer la séance comme réalisée ;
- pointer rapidement les nageurs présents ou absents ;
- ouvrir une fiche nageur avec ses informations utiles et ses acquis ;
- évaluer individuellement un nageur ;
- choisir une compétence et évaluer collectivement tout le groupe.

Les présences sont historisées par date et ne modifient jamais l’affectation théorique du nageur à son groupe.

Les séances créées ou dupliquées depuis un créneau sont ponctuelles par défaut : elles restent consultables depuis ce créneau mais ne sont pas proposées dans la bibliothèque générale. Le coach peut choisir « Conserver comme séance type » pour les rendre réutilisables.

L’éditeur compare la durée préparée à la durée calculée depuis les horaires du groupe. Un éventuel dépassement est signalé mais ne bloque jamais l’enregistrement.

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

Dans l’onglet `Inscriptions`, Ecole2Nat’ reconnaît notamment les colonnes `Info médicale` et `Droit à l'image`. Le droit à l’image accepte `OUI` ou `NON` (la cellule peut rester vide si l’information n’est pas connue). La colonne historique `Commentaire` n’est plus utilisée pour alimenter les informations médicales.
