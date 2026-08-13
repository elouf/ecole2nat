# Recette — Remplacements Coach datés V1

## Prérequis

- Un groupe actif appartenant à une saison active.
- Un coach titulaire de ce groupe.
- Un second utilisateur possédant le rôle Coach, non titulaire du groupe.
- Une séance type active et plusieurs nageurs dans le groupe.

## Migration

1. Mettre à jour le plugin puis charger une page WordPress pour déclencher `maybeUpgrade()`.
2. Vérifier la création de la table `e2n_group_substitutions`.
3. Vérifier que l’option `e2n_db_version` vaut `0.8.2`.
4. Recharger une seconde page et vérifier l’absence d’erreur ou de doublon.

## Administration

1. Ouvrir **Ecole2Nat' → Coachs**.
2. Dans **Remplacements datés**, choisir le coach non titulaire, le groupe et la date du jour.
3. Enregistrer puis vérifier que le remplacement apparaît dans la liste.
4. Enregistrer de nouveau la même combinaison et vérifier qu’aucun doublon n’est créé.
5. Ajouter un remplacement pour une date future et vérifier qu’il apparaît dans la liste.
6. Vérifier qu’une date passée est refusée côté serveur.
7. Supprimer un remplacement et vérifier sa disparition.

## Portail Coach — date du jour

1. Se connecter avec le remplaçant et ouvrir le groupe à la date du jour.
2. Vérifier le badge **Remplaçant · édition autorisée aujourd’hui**.
3. Affecter ou changer la séance prévue.
4. Enregistrer une présence et utiliser **Tous présents** ; recharger et vérifier la persistance.
5. Modifier une évaluation individuelle, une note et une évaluation collective ; recharger et vérifier la persistance.
6. Marquer la séance comme réalisée puis la repasser en prévue.

## Limitation temporelle et sécurité

1. Avec le même coach, ouvrir le groupe pour une date future où un remplacement est enregistré : vérifier la puce **Remplaçant prévu · préparation autorisée**.
2. Affecter ou changer la séance de cette date future et vérifier la persistance après rechargement.
3. Vérifier que les présences, évaluations, notes et la validation de la séance restent en consultation jusqu’au jour prévu.
4. Ouvrir une date sans remplacement : l’interface doit rester en consultation.
5. Forger une requête AJAX de présence ou d’évaluation avec une date future ou non autorisée : le serveur doit répondre `403`.
6. Forger un POST classique de validation de séance avec une date future ou non autorisée : le serveur doit refuser l’écriture.
7. Retirer le rôle Coach au remplaçant : ses titularités et remplacements doivent être supprimés.

## Non-régression

- Un titulaire conserve l’écriture sur son groupe quelle que soit la date consultée.
- Un administrateur conserve l’écriture sur tous les groupes.
- Un coach non titulaire et non remplaçant conserve la consultation seule.
- Les informations médicales ne sont pas ajoutées à de nouvelles interfaces.
- Aucun warning PHP ou JavaScript ne doit apparaître.
