# Recette — Portail Coach simplifié

## Accès administrateur

1. Depuis le back-office avec un administrateur, cliquer sur
   **Ecole2Nat' → Portail Coach** et vérifier l'ouverture du portail avec toutes
   les vues et tous les groupes actifs.
2. Vérifier que le bouton **Ouvrir le portail Coach** de l'écran Coach mène à la
   même page, sans ajouter le rôle Coach au compte administrateur.
3. Vérifier que ces deux accès ne sont pas proposés à un utilisateur sans la
   capacité `manage_options` et qu'une page Coach absente ne produit aucun lien cassé.

## Barre WordPress et menu utilisateur

1. Avec un compte Coach non administrateur, vérifier que la barre WordPress
   est masquée sur le portail et sur les autres pages publiques.
2. Ouvrir l'avatar, vérifier l'ordre Nom, Tableau de bord, Déconnexion, puis
   vérifier que Tableau de bord ouvre bien le BO autorisé au coach.
3. Avec un administrateur, vérifier que la barre WordPress reste visible.

## Présentation et navigation

1. Ouvrir le portail Coach sur ordinateur et vérifier que l'en-tête, le pied
   de page, le titre et les colonnes du thème WordPress ne sont pas affichés.
2. Vérifier que la barre d'administration WordPress reste disponible pour un
   administrateur connecté.
3. Faire défiler une page longue : la navigation Coach doit rester visible et
   son onglet actif doit être identifiable.
4. Ouvrir le menu utilisateur, vérifier le nom affiché et le lien de
   déconnexion.
5. Sur une fiche nageur, ouvrir **Actions** et vérifier les actions disponibles
   sans déclencher le renvoi d'un code Parents.
6. Vérifier le résumé de progression, le regroupement par domaine et
   l'ouverture à la demande d'une note vide. Une note existante doit rester
   visible à l'ouverture de la fiche.
7. Répéter le contrôle aux largeurs 390 px, 768 px et 1024 px : aucun défilement
   horizontal ne doit apparaître, les choix doivent rester tactiles et le menu
   d'actions doit rester entièrement accessible.
8. Avec la barre d'administration WordPress visible, contrôler les largeurs
   601, 768 et 783 px : elle ne doit masquer ni chevaucher l'en-tête Coach.
9. Modifier le nom et le logo des portails dans **Ecole2Nat' → Réglages** :
   ils doivent apparaître sur toutes les vues Coach. Retirer le logo doit
   restaurer le monogramme E2N.

## Migration

1. Charger WordPress avec une base en version `0.8.5`, sans désactiver le plugin.
2. Vérifier la création de `e2n_skill_level_history`, la migration de santé, la colonne `parent_access_code_generation` et la valeur `0.11.0` de l'option `e2n_db_version`.
3. Pour une ancienne note médicale non vide, vérifier `health_alert = 1` puis l'absence définitive de la colonne `medical_note`.
4. Vérifier les colonnes et index documentés dans `docs/database.md`.
5. Recharger une seconde fois : aucune erreur et aucune modification indésirable.
6. Vérifier aussi une installation sur base vide.
7. Depuis une base en `0.12.8`, charger le plugin sans le réactiver et vérifier
   la création de `e2n_training_performances`, de ses index métier et la valeur
   `0.13.0` de `e2n_db_version`, ainsi que la colonne et l'index `series_key`
   des deux tables de performances. Recharger une seconde fois sans erreur.

## Semaine type

1. Se connecter avec un compte Coach.
2. Vérifier que la page d'accueil affiche les groupes actifs classés par jour et heure, sans date ni navigation temporelle.
3. Vérifier les horaires, les titulaires habituels et les groupes sans créneau reconnu.
4. Cliquer sur un créneau : la liste des nageurs et l'évaluation collective sont directement accessibles.
5. Vérifier l'absence de séance prévue, présence, remplacement et action de préparation.
6. Sous la liste ou l'évaluation collective, choisir une épreuve et deux
   nageurs dans « Chronométrer une série ». Vérifier que Départ reste désactivé
   tant que l'un de ces choix manque, puis arrêter chaque nageur séparément.
7. Vérifier l'enregistrement immédiat des deux chronos dans
   `e2n_training_performances`, avec le groupe, la saison, le nageur et le coach.
8. Répéter la même épreuve pour le même nageur : les deux tentatives doivent
   être conservées. Tester aussi `25PAP`, `25DOS`, `25BRASSE` et `25NL`.
9. Ouvrir la fiche Coach du nageur : « Historique des chronos » doit présenter
   ensemble les entrées Entraînement et Compétition, dans l'ordre décroissant,
   avec leur contexte et leur auteur.
10. Forger l'appel AJAX avec un autre groupe, un nageur hors du groupe, un nonce
    invalide ou un utilisateur non autorisé : aucune ligne ne doit être écrite.
11. Vérifier que le module est initialement remplacé par le bouton
    « Chronométrer une série », puis que ce bouton ouvre le module complet.
12. Après ouverture d'un groupe, vérifier que chaque nageur possédant des temps
    affiche la même pastille de total que dans la vue Catégories.
13. Pour un groupe dont la catégorie ne possède aucune compétence, vérifier que
    « 0 acquis · 0 en cours » n'est affiché pour aucun nageur, sans masquer les
    pastilles de chronos ni les pictogrammes de santé et de droit à l'image.

## Accès aux nageurs

1. Vérifier les onglets **Nageurs**, **Catégories**, **Semaine type** et **Déconnexion** sur ordinateur, tablette et téléphone.
2. Dans **Nageurs**, vérifier le tri alphabétique et rechercher successivement un nom, un prénom, un groupe et une catégorie, avec et sans accents.
3. Vérifier que le message vide apparaît lorsqu'aucun nageur ne correspond.
4. Dans **Catégories**, vérifier le regroupement par catégorie puis par groupe.
5. Dans les deux listes, vérifier les pictogrammes de droit à l'image et d'alerte santé ; dans **Nageurs**, vérifier que seul le groupe accompagne l'identité.
6. Dans **Catégories**, vérifier que la pastille placée avant l'alerte santé
   additionne les chronos d'entraînement et de compétition du nageur, respecte
   le singulier/pluriel dans son libellé accessible et disparaît lorsque le total
   est nul.
7. Ouvrir un nageur depuis chacun des trois accès et vérifier que le lien retour conserve le contexte d'origine.
8. Vérifier qu'un nageur présent dans plusieurs saisons actives n'apparaît qu'une fois, avec sa saison courante prioritaire.
9. Sur une fiche possédant un téléphone responsable, vérifier **Appeler** et **Envoyer un message** sur mobile ; sans téléphone, vérifier leur absence.
10. Cliquer sur **Afficher le code Parents** : vérifier le format `XXXX-XXXX`, la copie si le navigateur l'autorise et l'absence du code dans les journaux.
11. Avec un email responsable valide, cliquer sur **Renvoyer un code Parents**, annuler la confirmation et vérifier qu'aucune donnée ne change.
12. Confirmer ensuite : vérifier l'envoi, le retour avec adresse masquée, la conservation du même code et la mise à jour du journal de distribution.
12. Sans email valide, vérifier l'absence du bouton d'envoi ; l'affichage du code doit rester disponible.
13. Forger les actions d'affichage et d'envoi avec un nageur hors du groupe, sans nonce ou avec un utilisateur non Coach : aucun code ne doit être exposé ni envoyé.

## Prévisualisation Parents

1. Depuis une fiche d'évaluation, cliquer sur **Voir la fiche Parents**.
2. Vérifier l'ouverture dans un nouvel onglet et la bannière **Prévisualisation Coach**.
3. Vérifier que la fiche est accessible même si aucun code parent n'a été distribué.
4. Vérifier que cette consultation ne crée aucun journal de connexion parent et n'incrémente pas le compteur d'accès.
5. Vérifier l'absence de note interne et de détail de santé.
6. Copier l'URL, se déconnecter puis l'ouvrir : aucune fiche ne doit être exposée.
7. Modifier l'identifiant du nageur ou le nonce dans l'URL : aucune fiche ne doit être exposée.

## Évaluations et droits

1. Avec un coach non titulaire, ouvrir n'importe quel groupe actif et modifier une compétence individuelle.
2. Vérifier l'enregistrement immédiat, puis recharger et contrôler la persistance.
3. Modifier une compétence collectivement et vérifier chaque nageur concerné.
4. Vérifier qu'une transition ajoute une seule ligne dans `e2n_skill_level_history` avec ancien statut, nouveau statut, date et coach.
5. Cliquer à nouveau sur le statut courant : aucune ligne supplémentaire ne doit être créée.
6. Modifier uniquement une note : le niveau courant change, mais aucun événement de statut n'est ajouté.
7. Ouvrir « Historique » sous une compétence : vérifier la chronologie et le nom des coachs.
8. Avec un utilisateur authentifié sans rôle Coach, forger chaque requête AJAX : réponse `403`, aucune écriture.
9. Rejouer une action avec nonce absent ou invalide : aucune écriture.
10. Modifier `group_id`, `swimmer_id` ou `skill_id` : aucune ressource hors contexte ne doit être modifiée.

## Mobile et non-régression

1. Tester les onglets, la recherche, la semaine type, la liste des nageurs, l'évaluation individuelle et collective sur téléphone et tablette.
2. Vérifier que les choix restent utilisables en un toucher et sans débordement horizontal.
3. Vérifier la navigation clavier, le focus visible et les retours Enregistrement / Enregistré / erreur.
4. Pendant chacun de ces états, vérifier que l'indicateur coloré ne touche ni le nom de la compétence ni son historique.
5. Vérifier que seul le pictogramme d'alerte santé est visible côté Coach, sans aucun détail médical.
6. Vérifier l'absence de warning et de donnée sensible dans `debug.log` et les réponses AJAX.
7. Sur une fiche de compétences, vérifier que `Fiche Extranat` apparaît sous
   le droit à l'image uniquement lorsque le nageur possède une licence, puis
   ouvre la bonne fiche FFN dans un nouvel onglet.
8. Dans « Catégories », vérifier que toutes les catégories sont cochées par
   défaut, qu'une catégorie décochée disparaît immédiatement et reste masquée
   après rechargement et reconnexion du même coach.
9. Vérifier que le choix d'un coach ne modifie pas celui d'un autre compte et
   que les liens `Fiche Extranat` ne sont présents que pour les licenciés.
10. Ouvrir « Chronométrer une série » : seuls les nageurs appartenant aux
    catégories cochées doivent être proposés, sans doublon. Cocher et décocher
    plusieurs catégories doit mettre à jour cette liste immédiatement.
11. Enregistrer deux nageurs issus de groupes différents et vérifier que chaque
    chrono conserve le groupe réel du nageur et apparaît dans son historique.
12. Supprimer un seul chrono après confirmation : les autres nageurs de la
    série doivent rester enregistrés. Recharger et contrôler sa disparition.
13. Supprimer ensuite une série entière : tous ses chronos, y compris ceux de
    groupes différents, doivent disparaître sans toucher aux autres séries.
14. Forger une suppression avec un identifiant, une clé de série, un groupe ou
    un nonce incorrect : aucune performance ne doit être supprimée.
15. Terminer entièrement une série, quitter la page puis revenir : le module
    doit être vierge. Recharger en revanche pendant une course active doit
    toujours restaurer l'épreuve, les nageurs et les chronos en cours.
16. En mode réduit, vérifier qu'une poubelle apparaît sur chaque ligne après
    l'enregistrement du chrono, reste utilisable sur smartphone et supprime
    uniquement le chrono correspondant après confirmation. La ligne doit
    disparaître et le nageur doit être décoché dans la liste des participants.
17. Vérifier que « Nouvelle série » est présenté comme un bouton blanc bordé de
    bleu et reste lisible et tactile sur téléphone.
18. Avec un nageur possédant des chronos variés, ouvrir « Rapport des chronos »
    et vérifier le regroupement dans l'ordre PAP, DOS, BRASSE, NL puis 4N, avec
    les distances croissantes et les libellés `100 4N`, `200 4N`, `400 4N`.
19. Pour chaque épreuve, contrôler que la courbe place les jours en abscisse et
    les chronos en ordonnée, du temps le plus faible au plus élevé. Survoler les
    points puis ouvrir le détail pour retrouver date, contexte, coach, note,
    disqualification et commentaire.
20. Vérifier une épreuve avec un seul temps, plusieurs temps le même jour et des
    temps répartis sur plusieurs jours, sur ordinateur et smartphone.
21. Survoler puis focaliser au clavier un point : la date et le chrono doivent
    apparaître. Cliquer ou toucher un point doit maintenir cette information ;
    cliquer ailleurs dans le graphique doit la masquer.
22. Pour chaque épreuve, vérifier que la borne basse de l'axe vertical est
    exactement le meilleur chrono du nageur et la borne haute exactement son
    moins bon chrono, sans marge ajoutée. Avec un seul chrono, le point doit
    rester centré sans erreur.
23. Ouvrir la fiche Coach d'un nageur appartenant à une catégorie sans
    compétence associée : aucun résumé « Progression » ni bloc de compétences
    vide ne doit être affiché, tandis que le rapport des chronos reste
    disponible si le nageur possède des temps.
24. Vérifier que le rapport des chronos et toutes ses épreuves sont visibles au
    chargement, mais que les graphiques sont masqués. Chaque épreuve doit
    afficher son meilleur temps non disqualifié et sa date.
25. Ouvrir puis masquer un graphique avec son bouton, puis utiliser « Afficher
    tous les graphiques » et « Masquer tous les graphiques ». Vérifier les
    libellés, les états `aria-expanded` et le comportement sur smartphone.
26. Dans le détail d'une épreuve, supprimer un temps après confirmation : il
    doit disparaître du rapport et de la table d'entraînement ou de compétition
    correspondante. Une performance de compétition supprimée ne doit conserver
    aucune relation résiduelle avec cette compétition.
27. Annuler puis confirmer « Purger les chronos » dans le menu Actions. Après
    confirmation, toutes les performances d'entraînement et de compétition du
    nageur doivent être supprimées, sans modifier les participants, réponses ou
    engagements des compétitions.
28. Forger les deux actions avec un nonce invalide, un autre nageur, un groupe
    non autorisé, une source inconnue ou un identifiant inexistant : aucune
    performance ne doit être supprimée.
