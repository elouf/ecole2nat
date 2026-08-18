# Tests automatisés

## Exécution

Depuis la racine du plugin, après `composer install` :

```bash
composer test
```

La commande doit se terminer avec un code `0` et afficher le nombre d’assertions réussies.

## Couverture initiale

- accès administrateur au portail Coach ;
- refus d’un utilisateur sans capacité Coach ;
- droits permanents d’un titulaire ;
- droit d’évaluation de tous les groupes pour chaque compte Coach ;
- libellés d’accès correspondants ;
- extraction du jour et de l’heure depuis un nom de groupe ;
- durée normale, horaire incomplet et créneau passant minuit ;
- calcul de l’heure de fin depuis une durée ;
- lecture réelle d’un mini-classeur `.xlsx` contenant `Durée (min)`.

## Fonctionnement

Le harnais `tests/run.php` utilise l’autoload Composer et des doublures minimales des fonctions WordPress nécessaires. Il ne charge pas WordPress, ne se connecte à aucune base et ne modifie aucune donnée du site.

Tout ajout de règle métier pure doit être accompagné d’au moins un cas nominal et un cas de refus ou de limite lorsqu’ils sont pertinents.

## Construction du paquet WordPress

Exécuter :

```bash
composer build
unzip -t build/ecole2nat-<version>.zip
```

Vérifier que l'archive ne contient qu'une racine `ecole2nat/`, qu'elle inclut
`vendor/autoload.php`, `src/`, `assets/` et `templates/`, et qu'elle n'inclut
ni `.git`, ni `.DS_Store`, ni tests ou documentation de développement.

Pour une version annonçant PHP 8.1, vérifier également que
`ecole2nat/vendor/composer/platform_check.php` dans le ZIP contrôle
`PHP_VERSION_ID >= 80100` et que `composer why-not php 8.1.0` ne retourne
aucune dépendance incompatible.
