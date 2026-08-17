# Recette — Éditeur de séance Coach V1

## Préparation

- Disposer d'un administrateur, d'un coach titulaire, d'un coach remplaçant daté et d'un coach sans droit d'écriture sur le groupe.
- Disposer d'un groupe actif, d'une séance type contenant plusieurs parties et exercices, et de créneaux aujourd'hui et à une date future.
- Activer `WP_DEBUG` et la journalisation dans `debug.log`.

## Migration

1. Charger WordPress avec une base en version `0.8.2`, sans désactiver le plugin.
2. Vérifier que `e2n_scheduled_sessions.coach_editable_copy` existe avec `NOT NULL DEFAULT 0` et que `e2n_db_version` vaut `0.8.3`.
3. Vérifier que les affectations existantes valent `0` et que leurs données sont conservées.
4. Recharger une seconde fois : aucune erreur ni modification indésirable ne doit apparaître.
5. Vérifier aussi une installation sur base vide.

## Création et duplication

1. Comme titulaire, ouvrir un créneau sans séance et choisir « Créer la séance de ce créneau ».
2. Saisir un nom et des objectifs : la séance est créée, affectée au créneau et l'éditeur s'ouvre.
3. Sur un créneau avec séance type, choisir « Dupliquer et adapter ».
4. Vérifier que parties, exercices, ordre, durées et consignes sont copiés.
5. Modifier la copie et vérifier que la séance type source ne change jamais.
6. Réaffecter une séance de bibliothèque avec « Changer la séance » : le bouton « Modifier la séance » doit disparaître et `coach_editable_copy` revenir à `0`.

## Édition et autosauvegarde

1. Modifier rapidement plusieurs fois le nom, les objectifs, le titre d'une partie, une durée et une consigne.
2. Vérifier les retours « Enregistrement… », « Enregistré » ou l'erreur explicite.
3. Recharger : la dernière valeur saisie doit être persistée.
4. Ajouter, déplacer et supprimer une partie ; vérifier l'ordre déterministe après rechargement.
5. Ajouter, déplacer et retirer un exercice ; vérifier la durée totale affichée.
6. Vérifier qu'un exercice d'une autre catégorie et un doublon dans la même partie sont refusés côté serveur.
7. Vérifier que les exercices rattachés aux domaines et compétences actifs de la catégorie sont proposés, sans erreur SQL dans `debug.log`.

## Droits et sécurité

1. Vérifier l'ensemble du parcours comme administrateur et comme titulaire.
2. Comme remplaçant futur, vérifier que la préparation est autorisée avant la date prévue.
3. Après la date d'un remplacement, vérifier que l'éditeur n'est plus accessible au remplaçant.
4. Comme coach authentifié mais non autorisé, forger chaque action AJAX : réponse `403`, aucune écriture.
5. Rejouer une action avec nonce absent ou invalide : aucune écriture.
6. Modifier `group_id`, `session_id`, `part_id`, `item_id`, la date ou l'exercice dans la requête : aucune ressource hors contexte ne doit être modifiée.

## Ergonomie et non-régression

1. Tester sur téléphone et tablette : champs, sélecteurs et actions doivent rester utilisables au bord du bassin.
2. Tester la navigation clavier et le focus visible.
3. Vérifier que consultation de séance, présences, évaluations et statut réalisée/prévue restent inchangés.
4. Vérifier l'absence de warning ou d'information sensible dans `debug.log` et dans les réponses AJAX.
