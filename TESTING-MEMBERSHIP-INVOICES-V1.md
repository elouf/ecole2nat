# Recette — Factures d’adhésion V1

1. Recharger WordPress et vérifier la création des tables
   `e2n_membership_payments` et `e2n_membership_invoices` sans réactivation.
2. Synchroniser une saison avec un nageur `Payé ? = OUI`, un total positif,
   une adresse responsable valide et `Facture envoyée` vide.
3. Ouvrir **Ecole2Nat' → Factures d’adhésion**, choisir la saison puis le filtre
   **À envoyer** : seul ce nageur doit être sélectionnable.
4. Préparer la facture, vérifier son numéro, le total unique, la mention PAYÉ,
   l’identité du club et l’impression A4.
5. Envoyer la facture et vérifier l’objet explicite, l’expéditeur/réponse à la
   trésorière et le PDF joint. Vérifier que chaque email responsable valide
   reçoit le message.
6. Recharger la page : l’envoi doit être daté et ne plus être sélectionnable.
   Resynchroniser avec `Facture envoyée` vide : l’envoi WordPress doit rester
   acquis.
7. Vérifier qu’une ligne Excel `Facture envoyée = OUI` apparaît déjà envoyée,
   même sans facture WordPress locale. Resynchroniser après avoir vidé puis
   supprimé la colonne : cette facture doit toujours apparaître envoyée.
8. Vérifier les refus : Payé différent de OUI, total nul, email absent, nonce
   invalide et utilisateur sans `manage_options`.
9. Tester un envoi multiple et contrôler que l’échec d’un destinataire ne
   bloque pas les autres factures.
