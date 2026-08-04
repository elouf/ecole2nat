# Recette – Séances V1

## Avant le test

1. Sauvegarder la base locale.
2. Remplacer les fichiers du plugin par ceux de l'archive.
3. À la racine du plugin, exécuter :

```bash
composer install
composer dump-autoload -o
```

4. Recharger l'administration WordPress.

## Tests principaux

### Liste des séances

- la durée totale est calculée ;
- le nombre de parties est affiché ;
- les actions Modifier, Dupliquer, Imprimer et Activer/Désactiver fonctionnent.

### Duplication

- la nouvelle séance porte un nom de type `Copie de ...` ;
- les parties sont copiées ;
- les exercices, durées, consignes et positions sont copiés ;
- la séance originale n'est pas modifiée.

### Éditeur

- la catégorie, le nom et les objectifs sont préremplis ;
- le total de la séance correspond à la somme des exercices ;
- une partie peut être ajoutée, renommée, déplacée et supprimée ;
- supprimer une partie supprime uniquement ses associations d'exercices ;
- un exercice peut être modifié, déplacé et retiré.

### Impression

- le bouton Imprimer ouvre une vue dédiée ;
- les menus WordPress disparaissent à l'impression ;
- le document tient correctement sur des pages A4 ;
- la catégorie, les objectifs, les durées et les consignes apparaissent.

## Vérification technique

```bash
find src -name '*.php' -print0 | xargs -0 -n1 php -l
```
