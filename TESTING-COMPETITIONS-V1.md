# Recette — Compétitions V1

## Migration et synchronisation

1. Mettre à jour le plugin sans le désactiver et vérifier la version DB `0.14.1`.
2. Vérifier les tables `e2n_competitions`,
   `e2n_competition_target_categories`,
   `e2n_swimmer_competition_category_states`,
   `e2n_swimmer_competition_state_categories` et
   `e2n_competition_registrations`, ainsi que les tables de facturation
   `e2n_competition_billing`, `e2n_competition_invoices`,
   `e2n_competition_invoice_versions` et `e2n_invoice_sequences` avec leurs
   index uniques documentés.
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
   formulaire de réponse. Le cartouche gris doit afficher
   `En attente de l’ouverture des inscriptions`.
2. Pendant la période, répondre Oui puis Non et vérifier l'enregistrement de
   la participation éventuelle des parents comme officiels. Le bouton doit
   afficher `Enregistrer ma réponse` avant la première saisie, puis
   `Modifier ma réponse` tant que la réponse reste modifiable.
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
11. Ouvrir Facturation et vérifier que seuls les nageurs engagés sur Extranat
    sont proposés. Un nageur ayant répondu Oui sans engagement ne doit jamais
    apparaître, même avec une URL ou un formulaire forgé.
12. Ajouter et retirer repas et nuitées avec les boutons tactiles, saisir un
    montant libre positif et un commentaire individuel qui le justifie aux
    parents, puis enregistrer et recharger. Les quantités, le montant, les
    commentaires et les totaux doivent être conservés. Une valeur négative ou
    non numérique doit être normalisée à zéro côté serveur.
13. Générer plusieurs factures positives : la première de l'année doit porter
    `Fxx.1000`, les suivantes doivent respecter une séquence unique et continue.
    Les lignes à zéro ne doivent pas recevoir de numéro.
14. Modifier une quantité puis régénérer : le numéro doit rester identique, la
    version augmenter et l'ancienne version rester présente en base. Relancer
    sans aucune modification ne doit créer aucune version supplémentaire.
15. Ouvrir l'aperçu web, vérifier les coordonnées du club, le nageur, les
    lignes, dont le montant libre sous la désignation **Autre**, le total et les
    commentaires, puis imprimer ou enregistrer en PDF.
    Contrôler le rendu A4 et mobile.
16. Forger une sauvegarde avec nonce invalide, nageur non engagé ou liste de
    nageurs incomplète : aucune ligne ni aucun numéro ne doit être modifié.
17. Dans le portail Nageurs, vérifier qu'une facture générée ajoute un bouton
    Facture visible à côté des autres actions et ouvre son détail web, y compris
    après la date de la compétition. Contrôler la ligne **Autre**, le total, le
    commentaire justificatif et le rendu mobile.
18. Télécharger le RIB depuis une session Parents valide. Rejouer l'URL sans
    nonce, depuis un autre nageur puis sans cookie signé : le fichier ne doit
    jamais être servi.
19. Déclarer le paiement avec un commentaire et vérifier l'email reçu par la
    trésorière, le passage de la facture à `payment_declared`, la disparition du
    formulaire Parents et le verrouillage des quantités côté Coach. Simuler un
    échec de `wp_mail()` : la facture doit rester déclarable afin de réessayer.
20. Ouvrir la prévisualisation Parents comme Coach : la facture est lisible,
    mais le téléchargement du RIB et la déclaration du paiement sont absents.
21. Dans les réglages, ouvrir successivement les sélecteurs Logo des portails,
    Logo des factures et RIB. Vérifier que la médiathèque s'ouvre à chaque fois,
    que le type de fichier est filtré, que la prévisualisation ou le nom change
    immédiatement et que la valeur reste enregistrée après rechargement.
22. Depuis une facture du portail Nageurs, cliquer sur `Imprimer ou enregistrer
    en PDF` et vérifier que l'aperçu ne contient que la facture ouverte, sans
    en-tête du portail, autre compétition, RIB ni formulaire de paiement.

## Déroulement de la compétition

- Vérifier que la pastille verte de chaque nageur indique uniquement le nombre
  d'épreuves déjà chronométrées dans la compétition consultée, dans le suivi des
  réponses puis dans la liste des engagés après démarrage. Les temps d'entraînement
  et des autres compétitions ne doivent pas être comptés ; aucun badge à zéro.

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
22. Sélectionner au moins quatre nageurs, basculer en `Mode réduit` avant puis
    pendant la course et vérifier que chaque ligne ne conserve que le nom, le
    chrono et le bouton Stop, sans interrompre ni réinitialiser les chronos.
23. Revenir au `Mode complet` et vérifier que commentaires, disqualification,
    appréciation et statut de sauvegarde ont conservé leurs valeurs. Recharger
    ensuite la page : le mode choisi sur cet appareil doit être restauré.
24. En mode complet, vérifier que le groupe du nageur n'est plus répété et que
    le contrôle rouge de disqualification est identique à celui de la saisie
    individuelle. En mode réduit à 390, 768 et 1024 px, vérifier que le nom, le
    chrono et Stop restent centrés verticalement sur une même ligne.
25. Vérifier que Départ reste grisé avec seulement une épreuve ou seulement un
    nageur sélectionné, puis devient actif dès que les deux critères sont
    remplis. Retirer l'un des deux avant le départ doit le désactiver à nouveau.
26. Vérifier que `25PAP`, `25DOS`, `25BRASSE` et `25NL` sont proposés dans le
    chronomètre collectif et dans la saisie individuelle, puis qu'ils sont
    enregistrés comme les épreuves plus longues.
27. Dans le chronomètre collectif, supprimer un chrono isolé puis une série
    entière après leurs confirmations respectives. Vérifier que la première
    action préserve les autres chronos et que la seconde ne supprime ni les
    participants, ni leurs engagements, ni une autre série.
28. Basculer en mode réduit après les arrêts et supprimer un chrono avec la
    poubelle de sa ligne ; les autres chronos doivent rester présents.

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
   `e2n_competition_participants`, `e2n_competition_performances`,
   `e2n_competition_billing`, `e2n_competition_invoices` et
   `e2n_competition_invoice_versions`.
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
