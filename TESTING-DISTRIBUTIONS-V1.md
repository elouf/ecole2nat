# Recette — Distributions V1

## Migration

1. Mettre à jour le plugin sans le désactiver ni le réactiver.
2. Vérifier la création de `e2n_distributions`, `e2n_distribution_targets` et
   `e2n_distribution_deliveries`, puis la valeur `0.15.0` de
   `e2n_db_version`.
3. Recharger une seconde fois et vérifier l'absence d'erreur ou de perte.

## Back-office

1. Avec un administrateur, créer « Bonnet » avec une période valide.
2. Filtrer successivement par catégorie, créneau et nom, puis utiliser les
   actions de sélection sur les résultats et enregistrer.
3. Modifier la cible : une remise retirée de la cible doit être supprimée.
4. Vérifier qu'un coach sans `manage_options` ne peut pas ouvrir ni soumettre
   l'écran.

## Portail Coach

1. Ouvrir Distributions, sélectionner « Bonnet » et toucher un nageur.
2. Vérifier que les nageurs sont regroupés par catégorie et triés par nom puis
   prénom dans chaque groupe.
3. Décocher une pastille, vérifier que la catégorie disparaît, puis revenir sur
   cette distribution et vérifier que ce choix est conservé.
4. Vérifier immédiatement l'état distribué, la date et le nom du coach.
5. Toucher à nouveau : l'état redevient « Non distribué ».
6. Vérifier l'affichage et la manipulation sur smartphone et tablette.
7. Forger une requête avec nonce invalide ou nageur hors cible : elle doit être
   refusée et aucune donnée ne doit changer.

## Portail Nageurs et suppression

1. Après une remise, ouvrir le parcours public et sa prévisualisation Coach :
   l'objet, la date et le coach doivent apparaître.
2. Après annulation, l'objet ne doit plus apparaître.
3. Supprimer une distribution après confirmation et vérifier la disparition
   de la campagne, des cibles et des remises, sans supprimer les nageurs.
