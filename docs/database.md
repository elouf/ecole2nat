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

La bibliothèque d'exercices est indépendante du suivi des progressions.

## Organisation du club

- `e2n_seasons` : saisons sportives, avec statuts courant et actif.
- `e2n_groups` : groupes rattachés à une saison et une catégorie, avec créneau et statut individuel.
- `e2n_swimmers` : identité, inscription, coordonnées du responsable, informations terrain et groupe courant des nageurs.
- `e2n_swimmer_group_memberships` : historique de l’affectation d’un nageur à un groupe pour une saison.

Le créneau d’un groupe est porté par `weekday`, `start_time` et `end_time`. La synchronisation peut calculer `end_time` depuis la colonne facultative `Durée (min)` du classeur. Sans valeur dans cette colonne, elle ne remplace pas une heure de fin existante.

La contrainte unique `(swimmer_id, season_id)` de `e2n_swimmer_group_memberships` garantit une seule affectation historique par nageur et par saison. `e2n_swimmers.group_id` représente l’affectation courante ; les consultations historiques utilisent les appartenances saisonnières.

Les principales colonnes spécifiques de `e2n_swimmers` sont :

- `health_alert` : indicateur booléen signalant qu'une information de santé doit être consultée dans la source externe du club ; aucun détail médical n'est stocké ;
- `image_rights` : `1` pour Oui, `0` pour Non et `NULL` pour Non renseigné ;
- `responsible_email` et `responsible_phone` : listes de contacts normalisées avec le séparateur ` / ` ; chaque email est validé séparément avant stockage et envoi ;
- `parent_message` : message explicitement destiné aux familles ;
- `parent_access_code_hash` : empreinte HMAC du code, jamais le code en clair ;
- `parent_access_code_generation` : génération du code permanent (`0` par défaut), incrémentée uniquement lors d'une réinitialisation explicite ;
- `parent_access_enabled` : état de l’accès ;
- `parent_access_created_at` : date de création ou de réinitialisation ;
- `parent_access_last_used_at` et `parent_access_count` : dernière consultation réussie et compteur ;
- `parent_access_distributed_at`, `parent_access_distribution_method` et `parent_access_distributed_to` : suivi de la dernière distribution.

## Anciennes données de séances

- `e2n_sessions` : informations générales d’une séance rattachée à une catégorie. `is_library=1` désigne une séance type réutilisable ; `is_library=0` une adaptation ponctuelle créée depuis un créneau Coach.
- `e2n_session_parts` : parties ordonnées d’une séance.
- `e2n_session_exercises` : exercices ordonnés d’une partie, avec durée et consignes spécifiques.

```text
Séance → Partie → Exercice utilisé
```

Un même exercice de bibliothèque peut être utilisé plusieurs fois dans une partie. Chaque ligne de `e2n_session_exercises` reste une utilisation indépendante, avec sa position, sa durée et sa consigne Coach. La durée totale d’une partie et de la séance est calculée depuis `e2n_session_exercises.duration`.

Ces tables sont conservées pour ne pas supprimer les données des installations existantes, mais elles ne sont plus exposées dans le portail Coach ni dans le back-office depuis la version 0.17.6.

## Évaluations progressives

`e2n_swimmer_skill_levels` contient le niveau courant d’un nageur pour une compétence et une saison.

- `status` : `not_observed`, `in_progress` ou `acquired` ;
- `evaluated_at` : date de dernière évaluation ;
- `evaluated_by` : identifiant de l’utilisateur WordPress ;
- `notes` : remarque interne facultative du coach.

La contrainte unique `(swimmer_id, season_id, skill_id)` garantit un seul niveau courant par compétence et par saison. Les saisons précédentes restent consultables.

`e2n_skill_level_history` est le journal immuable des changements de statut :

- `previous_status` et `status` décrivent la transition ;
- `changed_at` utilise la date et l'heure WordPress ;
- `changed_by` référence l'utilisateur WordPress responsable.

Une ligne est ajoutée uniquement lorsque le statut change. La mise à jour du niveau courant et l'ajout au journal appartiennent à la même transaction. Les modifications de notes seules ne créent pas d'événement.
La migration crée la table sans fabriquer d'événements rétroactifs : l'historique commence avec les changements réalisés après la mise à jour.

## Coachs et anciennes données datées

- `e2n_group_coaches` : affectation des utilisateurs WordPress titulaires d’un groupe.
- `e2n_group_substitutions` : affectation temporaire d’un coach remplaçant à un groupe pour une date précise.
- `e2n_scheduled_sessions` : séance type prévue pour un groupe et une date.
- `e2n_attendance` : pointage d’un nageur pour un groupe et une date.

La contrainte unique `(group_id, user_id)` de `e2n_group_coaches` empêche une double affectation du même coach au même groupe. Ces affectations indiquent les titulaires habituels affichés dans la semaine type ; elles ne limitent pas les droits d'évaluation. Tous les comptes Coach peuvent évaluer tous les groupes actifs.

Les tables de remplacements, séances planifiées et présences sont conservées pour éviter toute suppression automatique de données, mais ne sont plus alimentées ni exposées depuis la version 0.17.6. Leurs contraintes historiques restent présentes dans le schéma pour préserver les installations existantes.

## Accès parents

`e2n_parent_access_logs` journalise les tentatives d’accès au portail :

- `swimmer_id` : renseigné uniquement lors d’un accès réussi ;
- `success` : réussite ou échec ;
- `ip_hash` : empreinte pseudonymisée de l’adresse IP, jamais l’adresse brute ;
- `attempted_at` : date de la tentative.

Le code est calculé à partir du prénom normalisé et de la date de naissance ; il
n'est jamais enregistré dans ces tables. Les anciennes colonnes
`parent_access_*` de `e2n_swimmers` sont conservées pour assurer une mise à jour
non destructive, mais leurs valeurs ne participent plus à l'authentification.
La recherche porte uniquement sur les nageurs actifs nés à la date extraite du
code. Si plusieurs nageurs correspondent exactement, l'accès est refusé.

## Compétitions

- `e2n_competitions` contient les compétitions d'une saison, leur code stable,
  leurs dates, leur lieu, leur longueur de bassin `pool_length` (`25m`, `50m`
  ou `NULL`), leur période d'inscription, leur fiche technique, les liens
  facultatifs `program_url`, `carpool_url`, `liveffn_url` et
  `photo_album_url`, et leur statut.
- `e2n_competition_target_categories` conserve les catégories de compétiteur
  ciblées par chaque compétition sous leur libellé et une clé normalisée.
- `e2n_swimmer_competition_category_states` historise les changements de
  catégories de compétiteur par nageur avec leur date d'effet, égale à la date
  de synchronisation.
- `e2n_swimmer_competition_state_categories` contient les zéro, une ou
  plusieurs catégories composant chaque état.
- `e2n_competition_registrations` conserve la réponse `yes` ou `no`, sa source
  (`parent` ou `coach`), le booléen nullable `parents_official`, le choix
  `attendance_days` (`both`, `first_day`, `second_day` ou `NULL`) et le suivi
  séparé de l'engagement Extranat.
- `e2n_competition_participants` fige les nageurs effectivement suivis au
  démarrage de la compétition. Les engagements Extranat sont ajoutés
  automatiquement ; `added_manually` distingue les ajouts décidés sur place.
- `e2n_competition_performances` conserve plusieurs épreuves par participant :
  code d'épreuve, chrono libre, commentaire, disqualification, appréciation
  du temps de 1 à 5 et auteurs de création ou modification. `series_key` est
  nullable : il relie uniquement les performances issues d'un même départ
  collectif ; une saisie individuelle conserve `NULL`.
- `e2n_training_performances` conserve chaque chrono réalisé depuis un groupe
  de la semaine type : groupe et saison au moment de la saisie, nageur, code
  d'épreuve, chrono, commentaire, disqualification, appréciation et auteurs.
  Plusieurs lignes peuvent porter la même épreuve pour préserver toutes les
  tentatives. Les index `(swimmer_id, created_at)` et `(group_id, created_at)`
  permettent de relire l'historique par nageur ou par groupe. Leur
  `series_key` commun permet de supprimer atomiquement toutes les performances
  enregistrées lors d'un même départ, y compris lorsque plusieurs groupes
  étaient mélangés depuis la vue Catégories.

La suppression d'un chrono collectif cible son identifiant et son contexte.
La suppression d'une série cible son `series_key` et ne supprime aucune autre
donnée métier. Les clés de série sont validées côté serveur et indexées dans
les deux tables de performances.

L'historique Coach des chronos réunit au rendu les performances d'entraînement
et de compétition sans fusionner leurs cycles de vie. La date `created_at`
représente le moment réel de la saisie ; aucune séance planifiée ou datée n'est
créée. La suppression d'un nageur supprime ses chronos d'entraînement, tandis
qu'un groupe ou une saison encore référencé par ces chronos ne peut pas être
supprimé.

`started_at` marque le passage en mode terrain. Une compétition est en cours
tant que `closed_at` reste nul, puis clôturée lorsque cette date est renseignée.
La reprise efface uniquement `closed_at` et `closed_by` : participants et
performances sont conservés et restent modifiables même pendant la clôture.

Les contraintes uniques `(season_id, code)` et
`(competition_id, swimmer_id)` garantissent respectivement une compétition
par code et saison, puis une seule réponse courante par nageur. L'absence de
ligne d'inscription représente l'état Non renseigné. Une resynchronisation
met à jour la compétition et ses catégories sans supprimer les réponses.

La contrainte `(swimmer_id, effective_from)` garantit au plus un état quotidien
par nageur. Une synchronisation identique ne crée rien ; une modification crée
un état à la date courante, y compris lorsque la liste devient vide. Pour une
compétition, le dernier état dont `effective_from` est antérieur ou égal à sa
date de début est utilisé. Une correspondance de catégorie suffit ;
`target_all=1` ignore ce filtre et cible tous les nageurs actifs appartenant à
la saison de la compétition.

La suppression d'une compétition constitue une cascade applicative
transactionnelle : ses catégories ciblées, participants, performances,
réponses et engagements sont supprimés avec elle. L'historique des catégories de
compétiteur des nageurs est conservé, car son cycle de vie appartient au
nageur et non à la compétition.

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

Après une mise à jour du plugin, `Installer::maybeUpgrade()` compare `e2n_db_version` à `E2N_DB_VERSION`. Une différence relance `dbDelta()`, les migrations idempotentes d’historique saisonnier et de l'indicateur de santé, puis la mise en place du rôle/page Coach. Il n’est pas nécessaire de désactiver puis réactiver le plugin.
