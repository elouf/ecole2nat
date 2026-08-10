# Recette — Distribution globale des accès parents

## Filtres

1. Ouvrir **Ecole2Nat' → Accès parents**.
2. Vérifier que sans catégorie cochée, tous les nageurs actifs avec un groupe sont affichés.
3. Cocher une ou plusieurs catégories et appliquer les filtres.
4. Tester le filtre Groupe, Accès et Email.
5. Vérifier que Réinitialiser revient à la vue globale.

## Envoi global

1. Filtrer sur une catégorie de test.
2. Cliquer **Envoyer les accès non distribués**.
3. Vérifier le nombre d'emails envoyés et les éventuels nageurs sans email.
4. Vérifier dans WP Mail SMTP que les messages ont été remis au transport SMTP.
5. Vérifier qu'un lot de coupons apparaît avec exactement les mêmes codes que ceux envoyés par email.
6. Imprimer le lot : aucune régénération de code ne doit avoir lieu.

## Coupons globaux

1. Filtrer une ou plusieurs catégories.
2. Cliquer **Préparer tous les coupons affichés**.
3. Confirmer l'avertissement : cette action régénère les codes de tous les nageurs affichés.
4. Vérifier qu'un coupon est présent par nageur et que l'impression se fait en une seule opération.
5. Télécharger le CSV et contrôler nom, prénom, groupe, email, code et URL.

## Sélection manuelle

1. Utiliser la case d'en-tête pour sélectionner tous les résultats affichés.
2. Tester **Renvoyer aux sélectionnés** sur un petit échantillon.
3. Tester **Préparer les coupons sélectionnés**.

## Non-régression

- Un lien Gérer ouvre toujours l'accès parent individuel.
- Voir le parcours ouvre toujours la prévisualisation parent.
- Les anciens codes envoyés restent valides tant qu'aucune action de régénération n'est lancée.
- Après envoi email, imprimer le lot temporaire n'invalide pas les codes.
