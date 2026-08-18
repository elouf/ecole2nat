# Recette — Distribution des accès parents V1

## Pré-requis

- Une page WordPress publiée contient `[e2n_parent_report]`.
- WP Mail SMTP est configuré et un email de test fonctionne.
- Un groupe contient plusieurs nageurs actifs, dont au moins un avec une adresse email responsable valide.

## Signature

1. Ouvrir **Ecole2Nat' → Réglages** et remplacer la signature des emails Parents par une valeur de test sur plusieurs lignes.
2. Recharger la page et vérifier la persistance exacte de la signature.
3. Envoyer successivement un code depuis le BO et depuis une fiche Coach : les deux emails doivent se terminer par cette signature.
4. Forger l'enregistrement sans capacité `manage_options` ou avec un nonce invalide : l'option ne doit pas changer.

## Liens rapides

1. Ouvrir **Ecole2Nat' → Nageurs**.
2. Vérifier les actions `Modifier | Évaluer | Voir le parcours | Accès parents`.
3. Cliquer sur **Évaluer** : la fiche d'évaluation du bon nageur doit s'ouvrir.
4. Cliquer sur **Voir le parcours** : le frontend doit s'ouvrir dans un nouvel onglet sans demander de code et afficher la bannière de prévisualisation administrateur.
5. Depuis la fiche d'évaluation, vérifier le bouton **Voir le parcours parent**.

## Envoi individuel

1. Ouvrir **Accès parents** pour un nageur ayant un email responsable.
2. Cliquer sur **Générer et envoyer par email**.
3. Accepter la confirmation : un nouveau code est généré et invalide l'ancien.
4. Vérifier la réception de l'email et tester le code reçu sur le portail public.
5. Vérifier la signature personnalisée à la fin du message.
6. Vérifier l'affichage de la date et de l'adresse de dernière transmission dans le BO.

## Envoi groupé

1. Ouvrir **Ecole2Nat' → Accès parents**.
2. Choisir un groupe.
3. Cliquer sur **Envoyer les accès manquants par email**.
4. Vérifier le résumé envoyé/échec/sans email.
5. Vérifier que seuls les nageurs sans distribution valide ont reçu un nouveau code.
6. Sélectionner un ou plusieurs nageurs déjà distribués et cliquer sur **Renvoyer aux sélectionnés**.
7. Vérifier que l'ancien code ne fonctionne plus et que le nouveau fonctionne.

> `wp_mail()` confirme que WordPress a remis le message au transport SMTP. Il ne garantit pas à lui seul la lecture ou la livraison finale dans la boîte du destinataire. Utiliser les journaux WP Mail SMTP si un suivi de délivrabilité plus fin est nécessaire.

## Coupons et export

1. Sélectionner deux nageurs.
2. Cliquer sur **Préparer les coupons sélectionnés**.
3. Vérifier que les nouveaux codes apparaissent dans le lot temporaire.
4. Imprimer : seuls les coupons doivent apparaître.
5. Télécharger le CSV et vérifier les colonnes Nom, Prénom, Groupe, Email, Code, URL.
6. Tester les codes générés.
7. Cliquer sur **Effacer le lot temporaire**.

## Migration

Après la mise à jour et le chargement d’une page WordPress, vérifier dans `e2n_swimmers` la présence de :

- `parent_access_distributed_at`
- `parent_access_distribution_method`
- `parent_access_distributed_to`
