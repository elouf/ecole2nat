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
- portail parents sécurisé.

## Portail parents

1. Créez et publiez une page WordPress, par exemple **Mon parcours de natation**.
2. Placez-y uniquement le shortcode :

```text
[e2n_parent_report]
```

3. Dans **Ecole2Nat' → Nageurs**, ouvrez **Accès parents** sur la ligne d'un nageur.
4. Générez un code de 8 caractères et remettez le coupon aux parents.

Le code n'est jamais conservé en clair. Il n'est affiché dans l'administration qu'après sa génération ou sa régénération.

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

## Évaluations progressives

Le module Évaluations permet de suivre le niveau courant de chaque nageur sur les compétences du référentiel de la catégorie de son groupe. Les trois états disponibles sont : Non observé, En cours et Acquis. Les évaluations sont mises à jour au fil de l'eau, sans campagne ni calendrier imposé.

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

## Modèle de données

Voir [`docs/database.md`](docs/database.md).
