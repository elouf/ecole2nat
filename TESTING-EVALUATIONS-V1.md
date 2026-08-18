# Recette de test — Évaluations V1

## Installation

1. Remplacer le plugin par le contenu de l’archive.
2. Exécuter `composer install` puis `composer dump-autoload -o`.
3. Recharger une page WordPress afin de déclencher la migration automatique `maybeUpgrade()` et la création de `e2n_swimmer_skill_levels` et `e2n_skill_level_history`.
4. Vérifier dans la base que les deux tables existent.

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
13. Vérifier que chaque changement effectif de statut ajoute un événement avec la date et l'utilisateur.
14. Réenregistrer un statut identique et vérifier qu'aucun doublon d'historique n'est créé.
15. Provoquer un échec d'ajout dans l'historique et vérifier que le niveau courant est annulé par le `ROLLBACK`.
