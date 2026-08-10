# Recette — Purge totale Ecole2Nat' V1

## Préparation

Cette fonctionnalité est destructive. Effectuer le premier test uniquement sur un environnement local ou de recette.

1. Vérifier que plusieurs données existent : saison, catégorie, groupe, nageur, exercice, séance, évaluation.
2. Vérifier éventuellement qu'un code parent et un journal de synchronisation existent.
3. Aller dans **Ecole2Nat' → Maintenance**.

## Vérification de l'écran

- Le nombre total de lignes détectées doit être affiché.
- Le détail doit lister toutes les tables `*_e2n_*` présentes.
- Le bouton de purge ne doit fonctionner qu'avec :
  - la case de confirmation cochée ;
  - la phrase exacte `PURGER ECOLE2NAT`.

## Test de sécurité

1. Saisir une mauvaise phrase et valider.
2. Vérifier qu'aucune donnée n'est supprimée.
3. Revenir sur les listes et confirmer que les données sont toujours présentes.

## Test de purge

1. Saisir exactement `PURGER ECOLE2NAT`.
2. Cocher la confirmation.
3. Lancer la purge.
4. Vérifier le message de succès.
5. Vérifier dans les écrans Ecole2Nat' que toutes les listes sont vides.
6. Vérifier dans la base que toutes les tables `*_e2n_*` existent toujours mais contiennent 0 ligne.
7. Vérifier que les identifiants repartent de 1 lors des prochaines créations.
8. Vérifier que le plugin reste actif et utilisable.
9. Vérifier que les versions `e2n_version` et `e2n_db_version` restent présentes dans `wp_options`.

## Synchronisation après purge

1. Ouvrir **Synchronisation**.
2. Déposer un classeur valide.
3. Vérifier que les données peuvent être recréées normalement.
