# Recette — Autosave terrain V1

## Pré-requis

- Un compte Coach titulaire d’un groupe actif.
- Plusieurs nageurs dans le groupe.
- Au moins une compétence active dans le référentiel de la saison.

## Présences

1. Ouvrir un groupe depuis le portail Coach.
2. Cliquer sur Présent pour un nageur.
3. Vérifier que le statut « Enregistrement… » puis « Enregistré » apparaît sans rechargement de page.
4. Recharger la page et vérifier que la présence est conservée.
5. Tester Absent puis Non pointé.
6. Tester « Tous présents » et vérifier qu’une seule action suffit.

## Évaluation collective

1. Ouvrir une compétence depuis « Évaluation collective rapide ».
2. Modifier plusieurs nageurs successivement.
3. Vérifier qu’aucun bouton de validation n’est présent.
4. Recharger la page et vérifier que toutes les valeurs sont conservées.

## Fiche nageur

1. Ouvrir la fiche d’un nageur.
2. Modifier le statut de plusieurs compétences.
3. Modifier une note et attendre environ une seconde sans taper.
4. Vérifier le retour « Enregistré ».
5. Recharger et vérifier statuts + note.

## Droits et panne réseau

1. Avec un coach non titulaire, vérifier que les choix restent désactivés.
2. Simuler une panne réseau depuis les DevTools puis tenter une modification.
3. Vérifier l’affichage « Non enregistré — réessayer ».
4. Rétablir le réseau et refaire la sélection.
