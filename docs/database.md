# Modèle de données Ecole2Nat'

Toutes les tables utilisent le préfixe WordPress suivi de `e2n_`. Les noms ci-dessous omettent le préfixe WordPress, par exemple `e2n_seasons` correspond en pratique à `{$wpdb->prefix}e2n_seasons`.

Le schéma est créé et mis à niveau par `Ecole2Nat\Database\Installer` avec `dbDelta()`. La version installée est conservée dans l’option `e2n_db_version`. Les relations sont contrôlées par l’application : le schéma ne déclare pas de clés étrangères SQL.

## Référentiel pédagogique

- `e2n_categories` : catégories pédagogiques, avec ordre et statut actif.
- `e2n_skill_domains` : domaines rattachés à une catégorie.
- `e2n_skills` : compétences rattachées à un domaine.
- `e2n_exercises` : exercices rattachés à une compétence.
- `e2n_season_skills` : activation d’une compétence pour une saison donnée.

```text
Catégorie → Domaine → Compétence → Exercice
                           ↑
                    Compétence de saison
```

La contrainte unique `(season_id, skill_id)` de `e2n_season_skills` garantit une seule association par saison et compétence. Une compétence retirée d’un nouveau référentiel reste en base afin de préserver les historiques.

La durée n’appartient pas à l’exercice de bibliothèque. Elle est définie lors de son utilisation dans une séance.

## Organisation du club

- `e2n_seasons` : saisons sportives, avec statuts courant et actif.
- `e2n_groups` : groupes rattachés à une saison et une catégorie, avec créneau et statut individuel.
- `e2n_swimmers` : identité, inscription, coordonnées du responsable, informations terrain et groupe courant des nageurs.
- `e2n_swimmer_group_memberships` : historique de l’affectation d’un nageur à un groupe pour une saison.

La contrainte unique `(swimmer_id, season_id)` de `e2n_swimmer_group_memberships` garantit une seule affectation historique par nageur et par saison. `e2n_swimmers.group_id` représente l’affectation courante ; les consultations historiques utilisent les appartenances saisonnières.

Les principales colonnes spécifiques de `e2n_swimmers` sont :

- `medical_note` : information médicale interne ;
- `image_rights` : `1` pour Oui, `0` pour Non et `NULL` pour Non renseigné ;
- `parent_message` : message explicitement destiné aux familles ;
- `parent_access_code_hash` : empreinte HMAC du code, jamais le code en clair ;
- `parent_access_enabled` : état de l’accès ;
- `parent_access_created_at` : date de génération ou régénération ;
- `parent_access_last_used_at` et `parent_access_count` : dernière consultation réussie et compteur ;
- `parent_access_distributed_at`, `parent_access_distribution_method` et `parent_access_distributed_to` : suivi de la dernière distribution.

## Séances types

- `e2n_sessions` : informations générales d’une séance type rattachée à une catégorie.
- `e2n_session_parts` : parties ordonnées d’une séance.
- `e2n_session_exercises` : exercices ordonnés d’une partie, avec durée et consignes spécifiques.

```text
Séance → Partie → Exercice utilisé
```

La contrainte unique `(part_id, exercise_id)` empêche d’associer deux fois le même exercice à une partie. La durée totale d’une partie et de la séance est calculée depuis `e2n_session_exercises.duration`.

## Évaluations progressives

`e2n_swimmer_skill_levels` contient le niveau courant d’un nageur pour une compétence et une saison.

- `status` : `not_observed`, `in_progress` ou `acquired` ;
- `evaluated_at` : date de dernière évaluation ;
- `evaluated_by` : identifiant de l’utilisateur WordPress ;
- `notes` : remarque interne facultative du coach.

La contrainte unique `(swimmer_id, season_id, skill_id)` garantit un seul niveau courant par compétence et par saison. Les saisons précédentes restent consultables.

## Coachs, planning et terrain

- `e2n_group_coaches` : affectation des utilisateurs WordPress titulaires d’un groupe.
- `e2n_group_substitutions` : affectation temporaire d’un coach remplaçant à un groupe pour une date précise.
- `e2n_scheduled_sessions` : séance type prévue pour un groupe et une date.
- `e2n_attendance` : pointage d’un nageur pour un groupe et une date.

La contrainte unique `(group_id, user_id)` de `e2n_group_coaches` empêche une double affectation du même coach au même groupe.

La contrainte unique `(group_id, user_id, substitution_date)` de `e2n_group_substitutions` empêche de dupliquer un même remplacement. L’affectation permet de préparer la séance jusqu’à la date prévue. Les droits terrain ne sont actifs que lorsque `substitution_date` correspond à la date courante WordPress. Elle ne modifie pas les titulaires permanents. `created_by` conserve l’administrateur ayant enregistré le remplacement.

Dans `e2n_scheduled_sessions`, `status` vaut `planned` ou `completed`. `completed_at` et `completed_by` mémorisent la validation terrain. La contrainte unique `(group_id, session_date)` autorise une seule séance planifiée par groupe et par date. `coach_editable_copy` vaut `1` uniquement lorsqu'une séance a été créée ou dupliquée depuis ce créneau dans le portail Coach : ce marqueur lie le droit d'édition à la copie et à son affectation datée. Une affectation classique d'une séance de bibliothèque le remet à `0`.

Dans `e2n_attendance`, les statuts persistés sont `present` et `absent`. L’absence de ligne signifie « Non pointé ». La contrainte unique `(group_id, swimmer_id, session_date)` empêche plusieurs pointages du même nageur sur le même créneau daté. Les présences ne modifient jamais l’affectation du nageur à son groupe.

## Accès parents

`e2n_parent_access_logs` journalise les tentatives d’accès au portail :

- `swimmer_id` : renseigné uniquement lors d’un accès réussi ;
- `success` : réussite ou échec ;
- `ip_hash` : empreinte pseudonymisée de l’adresse IP, jamais l’adresse brute ;
- `attempted_at` : date de la tentative.

Les codes distribués en clair ne sont jamais enregistrés dans ces tables. Les lots temporaires d’email, coupons et CSV sont stockés dans des transients WordPress propres à l’administrateur.

## Synchronisation

`e2n_synchronization_logs` conserve l’historique des imports de classeurs :

- nom du fichier ;
- statut de l’opération ;
- synthèse et erreurs encodées en JSON ;
- utilisateur WordPress ayant lancé l’opération ;
- date d’exécution.

La synchronisation des données métier est transactionnelle. Le journal de succès ou d’erreur est écrit après le `COMMIT` ou le `ROLLBACK` correspondant.

## Suppression, purge et migrations

Les suppressions ordinaires sont contrôlées par les services applicatifs, qui bloquent les entités encore référencées et limitent les cascades aux agrégats explicitement possédés par leur parent.

La purge de maintenance vide toutes les tables portant le préfixe `e2n_`, mais conserve le schéma ainsi que les options techniques `e2n_version` et `e2n_db_version`.

Après une mise à jour du plugin, `Installer::maybeUpgrade()` compare `e2n_db_version` à `E2N_DB_VERSION`. Une différence relance `dbDelta()`, les migrations idempotentes d’historique saisonnier et la mise en place du rôle/page Coach. Il n’est pas nécessaire de désactiver puis réactiver le plugin.
