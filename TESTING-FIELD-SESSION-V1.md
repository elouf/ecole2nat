# Recette — Séance terrain V1 (v0.11.0)

## Préparation

- Utiliser une saison active avec au moins un groupe actif et plusieurs nageurs.
- Prévoir deux comptes Coach : un **titulaire** du groupe, un **non titulaire**.
- Avoir au moins une séance type active pour la catégorie du groupe.

## 1. Migration

1. Mettre à jour le plugin vers v0.11.0.
2. Ouvrir une page WordPress pour déclencher `maybeUpgrade()`.
3. Vérifier que la table `e2n_attendance` existe.
4. Vérifier que `e2n_scheduled_sessions` contient `status`, `completed_by` et `completed_at`.

## 2. Planning et séance datée

1. Se connecter avec le coach titulaire.
2. Ouvrir le créneau depuis le planning hebdomadaire.
3. Vérifier que la date du prochain créneau est affichée.
4. Affecter une séance type.
5. Revenir sur le créneau et vérifier le badge **Prévue**.
6. Cliquer sur **Marquer comme réalisée** et vérifier le badge **Réalisée**.
7. Repasser la séance en **Prévue** et vérifier que cela fonctionne.

## 3. Présences

1. Dans le créneau, pointer plusieurs nageurs Présent / Absent.
2. Enregistrer puis recharger la page : les états doivent être conservés.
3. Tester **Tous présents**, puis enregistrer.
4. Passer un nageur à **Non pointé** : son pointage doit disparaître sans modifier son appartenance au groupe.
5. Ouvrir la séance : la liste des nageurs doit afficher ✓ pour les présents et ✕ pour les absents.

## 4. Évaluation collective

1. Depuis le créneau, choisir une compétence dans **Évaluation collective rapide**.
2. Modifier plusieurs statuts sur le même écran.
3. Enregistrer.
4. Ouvrir ensuite les fiches individuelles correspondantes et vérifier que les niveaux sont identiques.
5. Vérifier qu'une note individuelle déjà existante n'est pas effacée par l'évaluation collective.

## 5. Droits Coach

1. Se connecter avec un coach non titulaire.
2. Vérifier qu'il peut consulter le groupe, la séance, les présences et les évaluations.
3. Vérifier que les contrôles de modification sont désactivés/absents.
4. Tenter une requête POST manuelle : l'écriture doit être refusée côté serveur.

## 6. Non-régression

- Fiche nageur : information médicale et droit à l'image visibles.
- Portail parents fonctionnel.
- Synchronisation Excel fonctionnelle.
- Back-office Évaluations fonctionnel.
- Aucun warning PHP dans `debug.log`.
