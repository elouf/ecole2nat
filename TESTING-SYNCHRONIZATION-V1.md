# Recette — Synchronisation du classeur club V1

## Installation

1. Remplacer le plugin par ce lot.
2. Exécuter `composer install` puis `composer dump-autoload -o`.
3. Recharger une page WordPress afin de déclencher la migration automatique `maybeUpgrade()`.
4. Vérifier la table `<prefix>_e2n_synchronization_logs`.

## Analyse

1. Ouvrir **Ecole2Nat' → Synchronisation**.
2. Déposer `Inscriptions 2026-2027(2).xlsx`.
3. Cliquer sur **Analyser uniquement**.
4. Vérifier les nombres de groupes, nageurs, lignes de référentiel et exercices.
5. Vérifier qu'aucune donnée n'a encore été créée.

## Synchronisation

1. Cliquer sur **Synchroniser maintenant**.
2. Vérifier la création des saisons, catégories, groupes, domaines, compétences, exercices et nageurs.
3. Vérifier qu'un nageur est associé au groupe construit avec `Catégorie + Créneau 1`.
4. Relancer exactement le même classeur : aucun doublon ne doit être créé.
5. Modifier un email ou téléphone dans Excel et relancer : le nageur doit être mis à jour.
6. Vérifier que les évaluations, séances et accès parents existants ne sont pas supprimés.
7. Vérifier l'historique en bas de la page.
8. Ajouter `Durée (min) = 45` dans l'onglet `Catégories` pour un groupe commençant à 17h15 : vérifier `end_time = 18:00:00` dans l'analyse puis après synchronisation.
9. Modifier la durée à 60 : l'analyse doit annoncer un groupe mis à jour et la synchronisation doit porter la fin à 18h15.
10. Vider ensuite la cellule, modifier manuellement l'heure de fin dans WordPress et resynchroniser : l'heure manuelle doit être conservée et le groupe annoncé inchangé.
11. Relancer le même classeur avec la durée renseignée : aucun changement supplémentaire ne doit être annoncé.

## Erreurs

- Supprimer temporairement un nom de catégorie dans l'onglet Référentiel : l'analyse doit être bloquée.
- Introduire deux fois le même nageur : l'analyse doit signaler un doublon.
- Mettre une extension autre que `.xlsx` : le fichier doit être refusé.
- Saisir une durée nulle, négative, non entière ou supérieure à 1440 : l'analyse doit être bloquée avec la ligne concernée.
- Renseigner une durée pour un nom de groupe sans heure reconnaissable : l'analyse doit expliquer que l'heure de début est nécessaire.
