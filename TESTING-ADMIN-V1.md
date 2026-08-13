# Recette de test — Administration V1

## Préparation

1. Mettre à jour le plugin et exécuter `composer dump-autoload -o`.
2. Recharger l’administration WordPress.
3. Vérifier que `assets/css/admin.css` est chargé sur les pages Ecole2Nat'.

## Badges de statut

- Vérifier le badge vert **Actif** dans les listes des catégories, groupes, nageurs et séances.
- Désactiver un élément et vérifier le badge rouge clair **Inactif**.

## Tableau des nageurs

- Rechercher par nom, prénom, licence et responsable.
- Filtrer par groupe, catégorie, saison, statut et affectation.
- Combiner plusieurs filtres.
- Trier les colonnes Nom, Prénom, Naissance, Groupe et Inscription.
- Tester les tailles de page 25, 50 et 100.
- Vérifier la pagination avec suffisamment de nageurs.
- Vérifier qu'après activation/désactivation ou suppression, la liste reste exploitable.

## Suppressions bloquées

- Catégorie liée à un domaine, groupe ou séance : suppression refusée.
- Domaine contenant une compétence : suppression refusée.
- Compétence liée à un exercice ou une évaluation : suppression refusée.
- Exercice utilisé dans une séance : suppression refusée.
- Saison courante ou contenant des groupes : suppression refusée.
- Groupe contenant des nageurs : suppression refusée.
- Groupe lié à un coach titulaire ou à un remplacement daté : suppression refusée.

## Suppressions autorisées

- Supprimer une catégorie vide.
- Supprimer un domaine vide.
- Supprimer une compétence inutilisée.
- Supprimer un exercice inutilisé.
- Supprimer une saison non courante et vide.
- Supprimer un groupe vide.
- Supprimer une séance et vérifier la disparition de ses parties et associations d'exercices.
- Supprimer un nageur de test et vérifier la disparition de ses évaluations et journaux d'accès parents.

## Non-régression

- Créer et modifier un nageur.
- Ouvrir un accès parents existant.
- Créer et modifier une séance.
- Ajouter un exercice à une séance.
- Mettre à jour une évaluation.
