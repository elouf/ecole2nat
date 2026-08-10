# Changelog

## 0.8.0 - Accès parents et distribution

- liens directs **Évaluer**, **Voir le parcours** et **Accès parents** depuis la liste des nageurs ;
- prévisualisation administrateur sécurisée du parcours parent ;
- lien **Voir le parcours parent** depuis la fiche d’évaluation ;
- page de distribution des accès parents par groupe ;
- génération et envoi individuel ou groupé par email ;
- suivi de la dernière transmission ;
- génération de coupons en masse et export CSV temporaire ;
- les renvois régénèrent explicitement le code et invalident l’ancien ;
- mise à niveau automatique du schéma de base de données lors d’une mise à jour du plugin.

## 0.7.1 - Saison cible de synchronisation

- la synchronisation choisit explicitement une saison cible dans WordPress ;
- la colonne `Saison` n’est plus requise dans l’onglet Groupes du classeur.

## 0.7.0 - Maintenance

- écran Maintenance et purge totale forcée des données Ecole2Nat’ ;
- détection dynamique de toutes les tables `e2n_` ;
- double confirmation avant toute purge destructive ;
- nettoyage des transients et fichiers temporaires de synchronisation ;
- conservation du plugin, du schéma et des options techniques ;
- champ téléphone responsable porté à 100 caractères.

## 0.6.0 - Synchronisation du classeur club

- synchronisation d’un classeur `.xlsx` avec analyse préalable ;
- onglets Inscriptions, Groupes/Catégories et Référentiel ;
- association des nageurs par Catégorie + Créneau ;
- synchronisation idempotente et journal des opérations.

## 0.5.0 - Administration et qualité des données

- badges de statut, recherche, filtres, tris et pagination ;
- suppressions protégées et transactionnelles avec contrôle des dépendances.

## 0.4.0 - Portail parents

- shortcode `[e2n_parent_report]` et codes aléatoires de 8 caractères ;
- stockage exclusif de l’empreinte du code ;
- progression, impression, limitation des tentatives et journalisation pseudonymisée.

## 0.3.0 - Évaluations progressives

- niveaux Non observé, En cours et Acquis ;
- synthèse par groupe et éditeur détaillé par nageur ;
- table `e2n_swimmer_skill_levels`.

## 0.2.0 - Séances types

- parties, exercices, durées et consignes propres à la séance ;
- réorganisation, duplication, impression A4 et durée totale automatique.
