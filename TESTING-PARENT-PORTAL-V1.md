# Recette — Portail Parents V1

## 1. Migration

1. Mettre à jour l’extension puis recharger une page WordPress afin de déclencher la migration automatique `maybeUpgrade()`.
2. Vérifier les nouvelles colonnes de `e2n_swimmers` :
   - `parent_message`
   - `parent_access_code_hash`
   - `parent_access_enabled`
   - `parent_access_created_at`
   - `parent_access_last_used_at`
   - `parent_access_count`
3. Vérifier la création de `e2n_parent_access_logs`.

## 2. Page publique

1. Créer une page WordPress nommée **Mon parcours de natation**.
2. Ajouter le shortcode `[e2n_parent_report]`.
3. Publier la page.
4. Vérifier que la page affiche seulement le formulaire de code.
5. Vérifier dans le code source de la page la présence de la directive robots `noindex`.

## 3. Génération d'un accès

1. Ouvrir **Ecole2Nat' → Nageurs**.
2. Cliquer sur **Accès parents** pour un nageur affecté à un groupe.
3. Saisir un message destiné aux parents et l'enregistrer.
4. Générer un code.
5. Vérifier qu'il contient exactement 8 caractères et aucun caractère ambigu (`0`, `O`, `1`, `I`).
6. Imprimer le coupon et vérifier que le code et l'adresse de la page apparaissent.

## 4. Consultation

1. Ouvrir la page publique dans une fenêtre privée.
2. Saisir le code.
3. Vérifier l'identité, le groupe et la catégorie.
4. Vérifier les domaines et toutes les compétences du référentiel de la catégorie.
5. Vérifier les libellés parents :
   - Non observé → À découvrir
   - En cours → En progression
   - Acquis → Acquis
6. Vérifier le message de l'entraîneur.
7. Vérifier la date de dernière mise à jour.
8. Vérifier l'impression A4.
9. Vérifier le bouton **Changer de code**.

## 5. Sécurité

1. Tester un code erroné : aucune information sur un nageur ne doit apparaître.
2. Faire cinq tentatives erronées : l'accès doit être bloqué temporairement.
3. Régénérer le code : l'ancien code doit cesser de fonctionner.
4. Désactiver l'accès : le code actif doit cesser de fonctionner.
5. Vérifier que la base ne contient jamais le code en clair.
6. Vérifier que `e2n_parent_access_logs.ip_hash` ne contient pas l'adresse IP brute.

## 6. Responsive

Tester la fiche avec une largeur de téléphone :

- aucun débordement horizontal ;
- cartes sur une seule colonne ;
- boutons utilisables ;
- code facile à saisir.
