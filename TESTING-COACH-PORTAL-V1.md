# Recette — Portail Coach simplifié

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
2. Vérifier la création de `e2n_skill_level_history`, la migration de santé et la valeur `0.10.0` de l'option `e2n_db_version`.
3. Pour une ancienne note médicale non vide, vérifier `health_alert = 1` puis l'absence définitive de la colonne `medical_note`.
4. Vérifier les colonnes et index documentés dans `docs/database.md`.
5. Recharger une seconde fois : aucune erreur et aucune modification indésirable.
6. Vérifier aussi une installation sur base vide.

## Semaine type

1. Se connecter avec un compte Coach.
2. Vérifier que la page d'accueil affiche les groupes actifs classés par jour et heure, sans date ni navigation temporelle.
3. Vérifier les horaires, les titulaires habituels et les groupes sans créneau reconnu.
4. Cliquer sur un créneau : la liste des nageurs et l'évaluation collective sont directement accessibles.
5. Vérifier l'absence de séance prévue, présence, remplacement et action de préparation.

## Accès aux nageurs

1. Vérifier les onglets **Nageurs**, **Catégories**, **Semaine type** et **Déconnexion** sur ordinateur, tablette et téléphone.
2. Dans **Nageurs**, vérifier le tri alphabétique et rechercher successivement un nom, un prénom, un groupe et une catégorie, avec et sans accents.
3. Vérifier que le message vide apparaît lorsqu'aucun nageur ne correspond.
4. Dans **Catégories**, vérifier le regroupement par catégorie puis par groupe.
5. Dans les deux listes, vérifier les pictogrammes de droit à l'image et d'alerte santé ; dans **Nageurs**, vérifier que seul le groupe accompagne l'identité.
6. Ouvrir un nageur depuis chacun des trois accès et vérifier que le lien retour conserve le contexte d'origine.
7. Vérifier qu'un nageur présent dans plusieurs saisons actives n'apparaît qu'une fois, avec sa saison courante prioritaire.
8. Sur une fiche possédant un téléphone responsable, vérifier **Appeler** et **Envoyer un message** sur mobile ; sans téléphone, vérifier leur absence.
9. Avec un email responsable valide, cliquer sur **Renvoyer un nouveau code Parents**, annuler la confirmation et vérifier qu'aucune donnée ne change.
10. Confirmer ensuite : vérifier l'envoi, le retour avec adresse masquée, l'invalidation de l'ancien code et la mise à jour du journal de distribution.
11. Sans email valide, vérifier l'absence du bouton et la présence du message explicatif.
12. Forger l'action avec un nageur hors du groupe, sans nonce ou avec un utilisateur non Coach : aucun code ne doit être généré ni envoyé.

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
