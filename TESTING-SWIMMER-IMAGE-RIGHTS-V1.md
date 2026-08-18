# Recette — Droit à l’image & info médicale v0.10.6

1. Ajouter dans l’onglet Inscriptions les colonnes `Droit à l'image` et `Info médicale`.
2. Tester les valeurs `OUI`, `NON` et une cellule vide pour le droit à l’image.
3. Mettre un texte différent dans l’ancienne colonne `Commentaire` et dans `Info médicale`.
4. Synchroniser : vérifier qu'une cellule `Info médicale` non vide produit `health_alert = 1`, sans stocker son texte, et que `Commentaire` est ignoré.
5. Dans le BO Nageurs, vérifier le champ Droit à l’image et sa modification manuelle.
6. Ouvrir le portail parent : vérifier l’affichage Oui / Non / Non renseigné.
7. Ouvrir un groupe dans le portail Coach : vérifier le pictogramme caméra à côté de l’éventuel pictogramme médical.
8. Ouvrir un nageur : vérifier le statut de droit à l’image et uniquement le pictogramme d'alerte santé.
9. Vérifier le hover et le focus clavier sur les cartes nageurs.
