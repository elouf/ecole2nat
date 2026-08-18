# Recette — Gestion des groupes V1

## Création

1. Ouvrir **Ecole2Nat’ → Groupes** avec un administrateur.
2. Créer un groupe avec saison, catégorie, jour, heures de début et de fin, nom et couleur.
3. Vérifier son affichage dans la liste et dans le planning Coach.

## Modification

1. Cliquer sur « Modifier » depuis la liste des groupes.
2. Vérifier que tous les champs existants sont préremplis.
3. Modifier successivement la saison, la catégorie, le nom, le jour, les deux horaires et la couleur, puis enregistrer.
4. Vérifier le message de succès, la persistance des valeurs et la nouvelle durée de créneau dans l’éditeur Coach.
5. Cliquer sur « Annuler » : aucune donnée ne doit changer.
6. Vérifier qu’une saison ou catégorie inactive déjà associée reste sélectionnable pendant l’édition.

## Validations et sécurité

1. Vérifier qu’un nom dupliqué dans la même saison est refusé, sans se confondre avec le groupe en cours d’édition.
2. Vérifier le refus d’un jour invalide et d’une heure de fin antérieure ou égale au début.
3. Rejouer l’action sans nonce ou avec un nonce invalide : aucune donnée ne doit changer.
4. Vérifier qu’un utilisateur sans `manage_options` ne peut ni ouvrir ni soumettre l’écran.
5. Vérifier l’absence de warning et de donnée sensible dans `debug.log`.

## Responsive et non-régression

1. Tester le formulaire et la liste à 320, 768 et 1280 px.
2. Vérifier les actions Activer/Désactiver et Supprimer.
3. Vérifier que l’édition n’altère ni les nageurs, ni les anciennes données datées conservées, ni les titulaires du groupe.
