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

## Séances types

- `e2n_sessions` : informations générales d'une séance type.
- `e2n_session_parts` : parties ordonnées d'une séance.
- `e2n_session_exercises` : exercices ordonnés d'une partie, avec durée et consignes spécifiques.

Relation principale :

```text
Séance → Partie → Exercice utilisé
```

La durée totale d'une partie et de la séance est calculée à partir de `e2n_session_exercises.duration`.
