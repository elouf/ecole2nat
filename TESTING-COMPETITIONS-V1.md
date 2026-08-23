# Recette — Compétitions V1

## Migration et synchronisation

1. Mettre à jour le plugin sans le désactiver et vérifier la version DB `0.12.8`.
2. Vérifier les tables `e2n_competitions`,
   `e2n_competition_target_categories`,
   `e2n_swimmer_competition_category_states`,
   `e2n_swimmer_competition_state_categories` et
   `e2n_competition_registrations`, ainsi que leurs index uniques documentés.
3. Importer un onglet `Compétitions` comportant Code compétition, Nom, Date
   début, Date fin, Lieu, Bassin, Début inscriptions, Fin inscriptions, Catégories de
   compétiteurs, Fiche technique, Programme, Covoiturage, liveFFN, Album photo,
   Informations et Statut.
4. Relancer le même fichier : aucune compétition en double et aucune réponse
   familiale existante ne doit être supprimée.
5. Modifier une date ou une catégorie puis resynchroniser : la compétition
   portant le même code doit être mise à jour.
6. Renseigner `U11;HANDI` dans la colonne `Compétition` des inscriptions puis
   cibler `U11` : le nageur doit être proposé, car une correspondance suffit.
7. Cibler `TOUS` et vérifier que tous les nageurs actifs de la saison sont
   proposés, y compris ceux dont la colonne `Compétition` est vide.
8. Vérifier que `TOUS;U11` est refusé et qu'un statut inconnu bloque l'analyse
   avec le numéro de ligne.
9. Synchroniser successivement `PASSCOMPET`, puis `U11`, puis `U13;HANDI` à
   des dates différentes. Vérifier que les trois états sont conservés et que
   chaque compétition utilise le dernier état connu à sa date de début.
10. Relancer deux fois un fichier inchangé : aucun nouvel état ne doit être
    créé. Vider ensuite la cellule et vérifier la création d'un état vide sans
    suppression de l'historique antérieur.
11. Importer successivement `25m` puis `50m` dans la colonne `Bassin` et vérifier
    leur conservation. Une autre valeur doit bloquer l'analyse avec une erreur.

## Portail Parents

1. Bien avant l'ouverture, vérifier que toutes les compétitions publiées et
   applicables au nageur sont visibles avec leurs informations, mais sans
   formulaire de réponse.
2. Pendant la période, répondre Oui puis Non et vérifier l'enregistrement de
   la participation éventuelle des parents comme officiels.
3. Pour une compétition sur deux jours, tester Les 2 jours, le premier jour et
   le second jour. Le choix est obligatoire uniquement lorsque le nageur répond
   Oui. Vérifier l'affichage des deux dates sur toutes les vues.
4. Après la clôture, vérifier que la réponse est affichée mais non modifiable.
5. Vérifier qu'une compétition d'une autre catégorie ou en brouillon n'apparaît pas.
6. Vérifier le lien PDF, l'affichage Annulée et la conservation des sauts de
   ligne du champ Informations dans les portails Parents et Coach.
7. Forger une réponse hors période, pour un autre nageur ou avec un nonce
   invalide : aucune donnée ne doit changer.
8. En prévisualisation Coach ou administrateur, vérifier qu'aucune réponse ne
   peut être enregistrée.
9. Vérifier que le briefing Parents est replié par défaut, que la fiche
   technique s'ouvre dans un nouvel onglet et que les deux actions restent
   lisibles et utilisables sur mobile.
10. Renseigner puis vider les colonnes Programme, Covoiturage, liveFFN et
    Album photo : leurs boutons
    doivent apparaître uniquement lorsqu'une URL valide est importée et
    s'ouvrir dans un nouvel onglet.
11. Vérifier le cartouche gris sans réponse, jaune après une réponse Oui,
    vert après validation de l'engagement Extranat par le coach et rouge après
    une réponse Non, sur ordinateur et mobile.
12. Laisser une compétition sans réponse dépasser sa date de clôture : le
    cartouche doit devenir rouge et afficher `Délai de réponse dépassé`. Une
    réponse enregistrée avant la clôture doit conserver son propre état.
13. Après validation Extranat par le coach, vérifier que le formulaire Parents
    disparaît, que le message relatif au forfait est affiché et qu'une requête
    POST forgée ne peut plus modifier la réponse.

## Portail Coach

1. Vérifier l'onglet Compétitions et les compteurs Oui, Non, sans réponse et à engager.
2. Ouvrir une compétition et saisir une réponse orale : sa source doit être Coach.
3. Vérifier l'affichage des parents officiels et des jours choisis par la famille.
4. Pour une réponse Oui, cocher Engagement Extranat et vérifier la date et le coach en base.
5. Passer la réponse à Non : l'engagement Extranat doit être retiré.
6. Forger les actions sans capacité Coach ou sans nonce : aucune écriture.
7. Tester la liste et la fiche à 390, 768 et 1024 px sans débordement horizontal.
8. Vérifier le tri alphabétique, puis le tri par avancement : Oui avec
   engagement Extranat, Oui sans engagement, Non, puis sans réponse.
9. Vérifier les fonds vert, orange et rouge correspondants et leur mise à jour
   immédiate après modification d'une réponse ou de l'engagement.
10. Vérifier que les boutons Fiche technique, Programme, Covoiturage, liveFFN
    et Album photo apparaissent uniquement lorsque leur URL est renseignée,
    s'ouvrent dans un nouvel onglet, utilisent tous le même style blanc bordé
    que dans le portail Parents et restent utilisables sur mobile.

## Déroulement de la compétition

1. Conserver un nageur ayant répondu Oui sans engagement Extranat puis cliquer
   sur Démarrer : le démarrage doit être bloqué et son nom affiché.
2. Utiliser le lien de forçage : seuls les nageurs engagés Extranat doivent
   apparaître dans la liste terrain.
3. Ajouter manuellement le nageur non inscrit, puis vérifier qu'un identifiant
   forgé hors de la saison ne peut pas être ajouté.
4. Ouvrir un nageur, choisir une épreuve : les autres boutons disparaissent et
   les champs Chrono, Commentaire, Disqualification et l'échelle de 1 à 5
   apparaissent. Annuler puis recommencer et enregistrer. Après succès, la
   liste des participants doit réapparaître immédiatement ; en cas d'erreur,
   la fiche du nageur doit rester affichée.
5. Pour une nageuse, vérifier le libellé `Disqualifiée`. Pour un nageur,
   vérifier `Disqualifié`.
6. Ajouter une seconde épreuve, revenir sur la première et la modifier sans
   créer de doublon involontaire. Lors du choix de la seconde épreuve, vérifier
   que le chrono, le commentaire, la disqualification et les étoiles sont
   initialement vides.
7. Enregistrer des performances de 1 à 5 étoiles et vérifier les fonds rouge,
   orange, jaune, vert clair et vert soutenu, ainsi que la lisibilité des textes.
8. Ouvrir une performance existante, annuler une suppression puis la confirmer.
   Vérifier qu'elle seule disparaît et que le séparateur entre la liste et le
   formulaire reste visible tant qu'une performance existe.
9. Forger une performance pour un non-participant, une épreuve inconnue ou une
   note supérieure à 5 : aucune donnée ne doit être écrite. La note peut rester
   non renseignée.
10. Avec un administrateur, revenir au suivi des inscriptions puis redémarrer :
   les performances existantes doivent être conservées. Vérifier que cette
   action n'est pas proposée à un simple coach.
11. Avec une à dix compétitions en cours, vérifier leurs raccourcis épinglés
    sous le header sur ordinateur et mobile, avec défilement horizontal lorsque
    la largeur disponible ne suffit plus.
12. Clôturer une compétition avec le bouton rouge Stop : son raccourci doit
    disparaître mais ses nageurs et performances doivent rester consultables et
    modifiables.
13. Redémarrer la compétition avec le bouton Play : son raccourci doit revenir
    sans modifier la liste des participants ni les performances.
14. Dans « Chronométrer une série », choisir une épreuve et au moins deux
    participants. Vérifier que leurs cartes apparaissent, que Départ lance un
    chrono commun et que chaque bouton Stop fige puis enregistre immédiatement
    le temps propre au nageur sans arrêter les autres.
15. Pendant la course, saisir un commentaire, une disqualification et une note,
    puis arrêter le nageur : toutes les valeurs doivent être enregistrées. Les
    modifications postérieures à l'arrêt doivent être sauvegardées automatiquement.
16. Recharger la page pendant une série : l'épreuve, les participants, les temps
    déjà arrêtés et le chronomètre encore actif doivent être restaurés.
17. Simuler une erreur réseau lors d'un arrêt : le temps doit rester affiché,
    l'erreur doit être visible et le bouton doit permettre de réessayer.
18. Vérifier sur mobile/tablette la taille des boutons Départ et Stop, puis tester
    au clavier le choix de l'épreuve, des participants et des évaluations.
19. Forger l'appel AJAX avec nonce invalide, utilisateur non autorisé, nageur non
    participant, épreuve inconnue ou chrono mal formé : aucun enregistrement ne
    doit être créé.
20. Vérifier que le chronomètre global n'est plus affiché : seuls les chronos des
    nageurs sélectionnés évoluent et se figent individuellement.
21. Vérifier que la liste Coach affiche Date · Catégorie · Lieu · Bassin, que la
    fiche Coach affiche le bassin après le lieu et que la liste Parents fait de même.

## Administration

1. Ouvrir Ecole2Nat' → Compétitions et corriger les dates, le lieu, le bassin, les
   catégories, la fiche technique, le programme, le covoiturage, liveFFN,
   l'album photo, les informations et le statut.
2. Vérifier qu'une correction ne supprime aucune réponse ni engagement.
3. Depuis la liste, annuler la confirmation de suppression : aucune donnée ne
   doit changer.
4. Confirmer ensuite la suppression d'une compétition comportant des réponses
   et engagements. Vérifier la disparition de la compétition, de ses lignes
   dans `e2n_competition_target_categories`, `e2n_competition_registrations`,
   `e2n_competition_participants` et `e2n_competition_performances`.
5. Vérifier que les états de catégories de compétiteur des nageurs restent
   présents et qu'une URL sans nonce ou un compte sans `manage_options` ne
   peut rien supprimer.

## 9. Fiches Extranat

1. Avec un nageur possédant un numéro de licence, vérifier la présence du lien
   `Fiche Extranat` dans le suivi des engagements et dans sa fiche terrain.
2. Vérifier que le lien contient ce numéro dans le paramètre `idrch_id` et
   s'ouvre dans un nouvel onglet.
3. Refaire le contrôle avec un nageur sans licence : aucun lien ne doit être
   affiché.
