# Guide des interfaces d’administration

## Statuts

Les statuts actifs/inactifs utilisent `Ecole2Nat\Admin\UI\Badge`. Aucun style de statut ne doit être écrit directement dans une page.

## Listes volumineuses

Les recherches, filtres, tris et paginations doivent être réalisés en SQL dans le repository. Une page ne doit pas charger toutes les lignes pour les filtrer en PHP.

## Suppressions

Les suppressions passent par `DeletionController` et `EntityDeletionService`.

- Une entité structurante encore utilisée n’est jamais supprimée automatiquement.
- L’interface explique la dépendance qui bloque la suppression.
- Les cascades sont limitées aux agrégats dont le cycle de vie est clairement détenu par leur parent : séance, parties et associations ; nageur, évaluations et accès parents.
- Une confirmation et un nonce sont obligatoires.

## Actions

L’ordre recommandé est : Modifier, actions métier, Activer/Désactiver, Supprimer. L’action Supprimer utilise la classe `e2n-delete-link`.
