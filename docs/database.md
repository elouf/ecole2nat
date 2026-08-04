# Modèle de données Ecole2Nat'

## Référentiel pédagogique

- `e2n_categories` : catégories pédagogiques.
- `e2n_skill_domains` : domaines rattachés à une catégorie.
- `e2n_skills` : compétences rattachées à un domaine.
- `e2n_exercises` : exercices rattachés à une compétence.

Relation principale :

```text
Catégorie → Domaine → Compétence → Exercice
```

La durée n'appartient pas à l'exercice de la bibliothèque. Elle est définie lors de son utilisation dans une séance.

## Organisation du club

- `e2n_seasons` : saisons sportives.
- `e2n_groups` : groupes rattachés à une saison et une catégorie.
- `e2n_swimmers` : nageurs, éventuellement affectés à un groupe.

Les colonnes du portail parents ajoutées à `e2n_swimmers` sont :

- `parent_message` : message explicitement destiné aux familles ;
- `parent_access_code_hash` : empreinte HMAC du code, jamais le code en clair ;
- `parent_access_enabled` : état de l'accès ;
- `parent_access_created_at` : date de génération ou régénération ;
- `parent_access_last_used_at` : dernière consultation réussie ;
- `parent_access_count` : nombre de consultations réussies.

## Séances types

- `e2n_sessions` : informations générales d'une séance type.
- `e2n_session_parts` : parties ordonnées d'une séance.
- `e2n_session_exercises` : exercices ordonnés d'une partie, avec durée et consignes spécifiques.

Relation principale :

```text
Séance → Partie → Exercice utilisé
```

La durée totale d'une partie et de la séance est calculée à partir de `e2n_session_exercises.duration`.

## Évaluations progressives

`e2n_swimmer_skill_levels` contient l'état pédagogique courant d'un nageur pour une compétence.

Relations :

- `swimmer_id` → `e2n_swimmers.id` ;
- `skill_id` → `e2n_skills.id` ;
- `evaluated_by` → utilisateur WordPress.

Colonnes principales :

- `status` : `not_observed`, `in_progress` ou `acquired` ;
- `evaluated_at` : date de dernière évaluation ;
- `notes` : remarque interne facultative du coach.

La contrainte unique `(swimmer_id, skill_id)` garantit qu'un nageur ne possède qu'un niveau courant par compétence. La V1 ne conserve pas l'historique des anciennes valeurs.

## Journal des accès parents

`e2n_parent_access_logs` journalise les tentatives d'accès au portail :

- `swimmer_id` : renseigné uniquement lors d'un accès réussi ;
- `success` : réussite ou échec ;
- `ip_hash` : empreinte pseudonymisée de l'adresse IP, jamais l'adresse brute ;
- `attempted_at` : date de la tentative.
