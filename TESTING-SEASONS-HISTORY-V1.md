# Recette — Saisons & historique pédagogique V1

## Migration depuis v0.8.2

1. Sauvegarder la base de données.
2. Installer la v0.9.0 puis charger une page WordPress : la migration est automatique.
3. Vérifier la présence des tables `e2n_season_skills` et `e2n_swimmer_group_memberships`.
4. Vérifier que `e2n_swimmer_skill_levels` contient une colonne `season_id` et un index unique `(swimmer_id, season_id, skill_id)`.
5. Ouvrir une évaluation existante et vérifier que les niveaux précédents sont toujours présents.

## Référentiel saisonnier

1. Synchroniser le classeur vers la saison courante.
2. Vérifier que les compétences du classeur apparaissent dans les évaluations de cette saison.
3. Créer une nouvelle saison et la définir comme courante.
4. Synchroniser un classeur N+1 dont une ancienne compétence a disparu et une nouvelle a été ajoutée.
5. Vérifier que l'ancienne compétence n'apparaît pas dans N+1, mais reste présente en consultant N.
6. Vérifier qu'aucune compétence historique n'est supprimée de la base.

## Affectations des nageurs

1. Synchroniser un nageur dans un groupe de N.
2. Synchroniser le même nageur dans un groupe de N+1.
3. Vérifier que sa fiche courante pointe sur le groupe N+1.
4. Vérifier qu'une ligne existe pour N et une autre pour N+1 dans `e2n_swimmer_group_memberships`.

## Évaluations

1. Évaluer une compétence en N et l'enregistrer comme `Acquis`.
2. Passer au groupe N+1 et enregistrer la même compétence comme `En cours`.
3. Revenir sur le groupe N : le niveau doit toujours être `Acquis`.
4. Revenir sur N+1 : le niveau doit être `En cours`.

## Portail parents

1. Ouvrir le parcours d'un nageur ayant au moins deux saisons.
2. Vérifier les onglets de saisons.
3. Vérifier que la saison courante est sélectionnée par défaut.
4. Passer à la saison précédente : groupe, catégorie, compétences et niveaux doivent correspondre à cette saison.
5. Vérifier que l'impression n'affiche pas la barre d'onglets.

## Non-régression

- Synchronisation Excel.
- Liste et édition des nageurs.
- Distribution des accès parents.
- Création/modification de séances.
- Purge totale Ecole2Nat'.
