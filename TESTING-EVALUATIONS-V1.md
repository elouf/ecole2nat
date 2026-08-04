# Recette de test — Évaluations V1

## Installation

1. Remplacer le plugin par le contenu de l’archive.
2. Exécuter `composer install` puis `composer dump-autoload -o`.
3. Désactiver puis réactiver le plugin afin de créer la table `e2n_swimmer_skill_levels`.
4. Vérifier dans la base que la table existe.

## Préconditions

- une catégorie avec plusieurs domaines et compétences ;
- un groupe actif rattaché à cette catégorie ;
- au moins deux nageurs actifs dans ce groupe.

## Tests

1. Ouvrir **Ecole2Nat' → Évaluations**.
2. Sélectionner un groupe.
3. Vérifier que seuls les nageurs actifs du groupe apparaissent.
4. Vérifier que le nombre de compétences correspond au référentiel de la catégorie du groupe.
5. Cliquer sur **Évaluer** devant un nageur.
6. Vérifier que les compétences sont regroupées par domaine.
7. Passer plusieurs compétences à **En cours** et **Acquis**.
8. Ajouter une note sur une compétence.
9. Enregistrer puis vérifier le message de succès.
10. Recharger l’écran et vérifier que les niveaux, notes et dates sont conservés.
11. Revenir au groupe et vérifier les compteurs Non observé / En cours / Acquis.
12. Vérifier qu’un nageur d’un autre groupe ne peut pas être évalué en modifiant manuellement l’URL.
