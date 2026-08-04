# Ecole2Nat'

Plugin WordPress de suivi et de préparation pédagogique pour école de natation.

## Modules disponibles

- saisons ;
- catégories ;
- référentiel pédagogique : domaines et compétences ;
- bibliothèque d'exercices ;
- groupes ;
- nageurs ;
- séances types avec parties, exercices, durées et consignes.

## Séances types

Le module permet de :

- créer et modifier une séance ;
- organiser la séance en parties ;
- ajouter des exercices depuis la bibliothèque ;
- définir une durée et des consignes propres à chaque utilisation ;
- réordonner ou retirer les exercices ;
- renommer, déplacer ou supprimer les parties ;
- calculer automatiquement les durées ;
- dupliquer une séance complète ;
- imprimer une fiche A4.

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

Place ensuite le dépôt dans :

```text
wp-content/plugins/ecole2nat
```

Active l'extension dans l'administration WordPress.

## Vérification de syntaxe

```bash
find src -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Modèle de données

Voir [`docs/database.md`](docs/database.md).

## Évaluations progressives

Le module Évaluations permet de suivre le niveau courant de chaque nageur sur les compétences du référentiel de la catégorie de son groupe. Les trois états disponibles sont : Non observé, En cours et Acquis. Les évaluations sont mises à jour au fil de l’eau, sans campagne ni calendrier imposé.
