# Changelog

## 0.4.0

### Portail parents

- ajout du shortcode public `[e2n_parent_report]` ;
- ajout d’un accès par code aléatoire unique de 8 caractères ;
- stockage exclusif de l’empreinte du code en base ;
- génération, régénération et désactivation depuis l’administration ;
- ajout d’un message de l’entraîneur visible par les familles ;
- affichage mobile-first des compétences regroupées par domaine ;
- vocabulaire parents : À découvrir, En progression et Acquis ;
- progression globale et progression par domaine ;
- impression d’un coupon d’accès et de la fiche parcours ;
- limitation des tentatives et blocage temporaire ;
- journalisation pseudonymisée des accès ;
- exclusion du portail de l’indexation des moteurs de recherche.

## 0.3.0

### Évaluations progressives

- évaluation progressive des nageurs sans campagne ni jalon obligatoire ;
- trois niveaux par compétence : Non observé, En cours et Acquis ;
- vue de synthèse par groupe ;
- éditeur détaillé des niveaux d’un nageur, organisé par domaine pédagogique ;
- notes facultatives par compétence ;
- date et auteur de la dernière mise à jour ;
- nouvelle table `e2n_swimmer_skill_levels`.

## 0.2.0

### Séances types

- ajout du calcul automatique de la durée totale ;
- ajout du renommage, du déplacement et de la suppression des parties ;
- ajout de la duplication complète d'une séance ;
- ajout d'une vue d'impression A4 ;
- correction de l'ancienne colonne de durée dans la liste ;
- masquage visuel des pages internes d'édition ;
- amélioration des contrôles de doublons lors des modifications.

### Documentation

- mise à jour du README ;
- ajout de la documentation du modèle de données.
