# Recette — Portail Parents V1

## 1. Migration

1. Mettre à jour l’extension puis recharger une page WordPress afin de déclencher la migration automatique `maybeUpgrade()`.
2. Vérifier les nouvelles colonnes de `e2n_swimmers` :
   - `parent_message`
   - `parent_access_code_hash`
   - `parent_access_code_generation`
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
6. Vérifier que la page utilise le gabarit autonome Ecole2Nat' : aucun en-tête,
   pied de page, titre ou colonne du thème WordPress ne doit apparaître.

## 2 bis. Identité visuelle

1. Ouvrir **Ecole2Nat' → Réglages**, modifier le nom commun des portails et
   choisir une image dans la médiathèque WordPress.
2. Enregistrer puis recharger les réglages : le nom, le logo et son aperçu
   doivent être conservés.
3. Ouvrir successivement la saisie du code, une fiche enfant, une
   prévisualisation Coach et le portail Coach : le même nom et le même logo
   doivent apparaître.
4. Retirer le logo et enregistrer : le monogramme **E2N** doit reprendre sa
   place, sans modifier le nom configuré.
5. Forger l'enregistrement sans capacité `manage_options` ou avec un nonce
   invalide : les deux options ne doivent pas changer.

## 3. Génération d'un accès

1. Ouvrir **Ecole2Nat' → Nageurs**.
2. Cliquer sur **Accès parents** pour un nageur affecté à un groupe.
3. Saisir un message destiné aux parents et l'enregistrer.
4. Afficher le code créé automatiquement.
5. Vérifier son format `XXXX-XXXX`, sans caractère ambigu (`0`, `O`, `1`, `I`), puis le réafficher : il doit rester identique.
6. Imprimer le coupon et vérifier que le code et l'adresse de la page apparaissent.
7. Sur MySQL 8, vérifier que le lien **Accès parents** ouvre bien le nageur sans erreur « Nageur introuvable ».

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
8. Sous une compétence modifiée plusieurs fois, vérifier que l'historique est masqué par défaut.
9. Cliquer sur le pictogramme d'historique et vérifier les dates, statuts et noms des coachs, puis le replier.
10. Vérifier que les compétences sans historique restent compactes et sans contrôle vide.
11. Vérifier qu'aucun indicateur ou détail de santé ni aucune note interne Coach n'est exposé.
12. Vérifier l'impression A4 : les historiques repliables ne doivent pas être imprimés.
13. Vérifier le bouton **Changer de code**.
14. Vérifier que la saisie du code, la fiche enfant et la prévisualisation Coach
    partagent la même identité visuelle que le portail Coach.

## 5. Sécurité

1. Tester un code erroné : aucune information sur un nageur ne doit apparaître.
2. Faire cinq tentatives erronées : l'accès doit être bloqué temporairement.
3. Afficher, envoyer et imprimer le code : il doit rester identique.
4. Réinitialiser explicitement le code : l'ancien doit cesser de fonctionner.
4. Désactiver l'accès : le code actif doit cesser de fonctionner.
5. Vérifier que la base ne contient jamais le code en clair.
6. Vérifier que `e2n_parent_access_logs.ip_hash` ne contient pas l'adresse IP brute.
7. Depuis le portail Coach, ouvrir la prévisualisation d'un nageur et vérifier qu'elle porte la bannière Coach, n'utilise aucun code et ne modifie ni le compteur ni les journaux parents.
8. Vérifier que l'URL de prévisualisation Coach exige une session Coach ou administrateur active et un nonce valide.
9. Renvoyer un code depuis la fiche Coach : vérifier qu'il reste identique et que l'envoi est enregistré comme distribution email.

## 6. Responsive

Tester la fiche avec une largeur de téléphone :

- aucun débordement horizontal ;
- cartes sur une seule colonne ;
- boutons utilisables ;
- code facile à saisir.

Avec un administrateur connecté, répéter le contrôle entre 601 et 783 px et
vérifier que la barre d'administration ne masque pas l'en-tête Ecole2Nat'.

## 7. Fiche Extranat

1. Ouvrir les onglets Progression et Compétitions d'un nageur licencié.
2. Vérifier que `Fiche Extranat` apparaît près de son identité et ouvre sa
   fiche de performances FFN dans un nouvel onglet.
3. Vérifier qu'aucun lien n'est affiché lorsque la licence est absente.

## 8. Catégorie sans compétences et chronos

1. Ouvrir le portail Parents d'un nageur appartenant à une catégorie sans
   compétence associée.
2. Vérifier que le résumé, la légende et les blocs de progression ne sont pas
   affichés.
3. Avec des temps d'entraînement et de compétition existants, ouvrir « Rapport
   des chronos » et vérifier l'ordre des épreuves, les courbes, les dates et les
   chronos détaillés.
4. Vérifier qu'aucun commentaire interne, aucune étoile et aucun nom de coach
   n'apparaît dans ce rapport, y compris dans le HTML de la page.
5. Refaire le contrôle en accès familial, en prévisualisation Coach et en
   prévisualisation administrateur.
6. Vérifier que le rapport et les lignes d'épreuves sont toujours visibles,
   tandis que les graphiques sont masqués au chargement.
7. Contrôler le meilleur temps non disqualifié et sa date sur chaque ligne,
   puis tester l'ouverture individuelle et les commandes globales d'affichage
   et de masquage des graphiques sur ordinateur et smartphone.
