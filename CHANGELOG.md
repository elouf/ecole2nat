# Changelog

## Non publié — 0.18.4

- Rétablit la recherche locale de l’onglet Nageurs du portail Coach, sans dépendre de la configuration AJAX, et garantit que les cartes marquées `hidden` ne sont pas réaffichées par leur mise en page CSS.

- Corrige les listes Nageurs et Catégories du portail Coach ainsi que les accès Parents sur MySQL 8 en évitant l'alias SQL réservé `groups`.
- Exécute les migrations automatiques sur `init` afin que la création de la page Coach ne provoque plus d'erreur fatale pendant `plugins_loaded`.

- Force la résolution Composer sur PHP 8.1 afin d'empêcher une dépendance indirecte de rendre le paquet incompatible avec la version minimale annoncée.
- Ajoute `composer build` pour générer un ZIP WordPress propre et versionné, contenant les dépendances de production sans les fichiers de développement.
- Lit un onglet `Référentiel <catégorie>` par catégorie et déduit la catégorie du nom de l'onglet, sans codes domaine ou compétence.
- Ignore les lignes de l'onglet Inscriptions dont la valeur `Renouvellement` n'est pas explicitement `OUI` ou `NON`.
- Interprète correctement les cellules fusionnées des classeurs, notamment les domaines couvrant plusieurs compétences.
- Modernise le portail Coach avec une interface autonome, cohérente et indépendante de la mise en page du thème WordPress.
- Remplace l'en-tête historique par une navigation compacte et persistante, complétée par un menu utilisateur.
- Regroupe les actions secondaires de la fiche nageur dans un menu dédié afin de conserver la progression comme action principale.
- Ajoute un résumé visuel de la progression et regroupe les compétences par domaine dans des cartes plus lisibles.
- Réduit l'encombrement des notes internes en les affichant à la demande, tout en laissant ouvertes celles déjà renseignées.
- Corrige le positionnement des en-têtes autonomes sous la barre d'administration WordPress, y compris entre 601 et 782 px.
- Harmonise la saisie du code, la fiche Parents et sa prévisualisation Coach avec le langage visuel du portail Coach.
- Isole également le portail Parents de la mise en page du thème WordPress sans modifier ses contrôles d'accès.
- Ajoute aux réglages le nom et le logo communs aux en-têtes des portails Coach et Parents, avec sélection du logo depuis la médiathèque WordPress.

- Recentre le portail Coach sur une semaine type permanente et l'accès direct aux groupes, sans navigation datée.
- Retire du portail les séances planifiées, les remplacements datés et le pointage des présences.
- Autorise tous les comptes Coach à évaluer tous les groupes actifs ; les titulaires restent une information de la semaine type.
- Retire du back-office la liste, l'éditeur et l'impression des séances ainsi que la gestion des remplacements.
- Ajoute un journal transactionnel des changements de progression avec date et coach, affiché à la demande dans les portails Coach et Parents.
- Ajoute au portail Coach les onglets Nageurs, Catégories et Semaine type, avec recherche alphabétique et conservation du contexte de navigation.
- Permet au coach de prévisualiser la fiche Parents d'un nageur par un lien signé, sans utiliser son code ni comptabiliser un accès parent.
- Remplace les notes médicales stockées par un simple indicateur de santé, convertit les anciennes valeurs puis supprime définitivement leur contenu textuel.
- Affiche les indicateurs santé et droit à l'image dans les listes Coach et ajoute les actions Appeler et Envoyer un message sur la fiche nageur.
- Simplifie la liste alphabétique Coach en n'affichant que le groupe, sans répéter la catégorie déjà présente dans son nom.
- Ajoute l'espace intérieur nécessaire entre l'indicateur coloré d'autosauvegarde et le contenu d'une compétence.
- Permet à un coach de régénérer et d'envoyer un code Parents depuis la fiche nageur, après confirmation et sans afficher le code en clair.
- Ajoute un réglage administrateur pour personnaliser la signature commune de tous les emails de codes Parents.
- Utilise « Les coachs » comme signature par défaut des emails Parents.
- Ajoute `e2n_skill_level_history` et porte la version technique du schéma à `0.9.0`.
- Porte la version technique du schéma à `0.10.0` pour la migration de l'indicateur de santé.

- Ajoute la navigation par semaine dans le planning Coach et affiche la date de chaque journée.
- Ajoute la navigation entre les occurrences précédentes et suivantes depuis la page d'un groupe.
- Réserve les opérations terrain au jour courant : le passé reste consultable et le futur permet seulement de préparer la séance, sauf pour les administrateurs.

- Ajoute une commande `composer test` sans dépendance supplémentaire, avec couverture initiale des droits Coach, créneaux, durées et lecture Excel.
- Rend le repository des droits Coach injectable afin de tester les règles métier sans base WordPress.
- Ajoute l'édition complète d'un groupe depuis la liste du back-office : saison, catégorie, nom, jour, horaires et couleur.
- Lit la colonne facultative `Durée (min)` de l'onglet `Groupes` ou `Catégories` et calcule l'heure de fin du créneau.
- Met à jour l'heure de fin lors des synchronisations suivantes lorsque la durée est renseignée, tout en préservant la valeur existante si la cellule est vide.

- Affiche dans l'éditeur Coach la durée préparée et, lorsque les horaires du groupe sont complets, la durée du créneau.
- Recalcule immédiatement les totaux de la séance et de chaque partie pendant la saisie.
- Signale visuellement un dépassement sans empêcher l'enregistrement.

- Distingue les adaptations ponctuelles créées depuis un créneau des séances types réutilisables.
- Exclut ces adaptations des sélecteurs et de la bibliothèque générale sans supprimer leur historique.
- Ajoute l'action Coach « Conserver comme séance type ».
- Ajoute `is_library` à `e2n_sessions` et porte la version technique du schéma à `0.8.4` ; les séances existantes restent dans la bibliothèque.
- Autorise plusieurs utilisations du même exercice dans une partie, avec des durées et consignes distinctes.
- Retire de façon idempotente l'ancien index unique `part_exercise` et porte la version technique du schéma à `0.8.5`.

- Ajoute une recherche instantanée dans la bibliothèque d'exercices de l'éditeur Coach.
- Regroupe les exercices par domaine et compétence pour accélérer leur sélection sur mobile.
- Corrige le chevauchement des champs de l'éditeur Coach entre 700 et 820 px de largeur.

- Ajoute dans le portail Coach la création d'une séance pour un créneau et la duplication de la séance prévue.
- Permet d'éditer sur mobile le nom, les objectifs, les parties, les exercices, les durées et les consignes de cette copie avec autosauvegarde.
- Protège les séances types partagées : seule une copie créée depuis le créneau est modifiable, sous réserve des droits de préparation du groupe et de la date.
- Ajoute `coach_editable_copy` à `e2n_scheduled_sessions` et porte la version technique du schéma à `0.8.3`.
- Corrige la bibliothèque d'exercices vide dans l'éditeur Coach en supprimant un filtre sur une colonne inexistante.

- Corrige le planning Coach afin qu’il affiche tous les groupes des saisons actives, et non uniquement ceux d’une saison arbitrairement sélectionnée.
- Rétablit une section explicite pour les groupes dont le créneau reste impossible à déduire.
- Ajoute les remplacements Coach datés : un administrateur peut autoriser un coach à modifier un groupe uniquement le jour prévu.
- Autorise un remplaçant futur à affecter ou changer la séance en amont, sans ouvrir les droits terrain avant la date prévue.
- Applique cette autorisation temporaire aux séances, présences et évaluations, y compris aux enregistrements AJAX.
- Ajoute la table `e2n_group_substitutions` et porte la version technique du schéma à `0.8.2`.
- Incrémente la version technique du schéma à `0.8.1` afin de rejouer de façon idempotente les migrations sur les installations existantes.
- Complète la documentation du modèle de données et harmonise les recettes de migration automatique.
- Remet les versions de ce changelog dans l’ordre chronologique.

## 0.11.2 - 2026-08-12

- Enregistrement AJAX immédiat des présences dans le portail Coach.
- Enregistrement AJAX immédiat des évaluations individuelles et collectives.
- Autosave temporisé des notes de compétences.
- Suppression des boutons de validation des présences et évaluations.
- Indicateur visuel Enregistrement / Enregistré / erreur réseau.
- File d’attente JavaScript par donnée afin que la dernière sélection du coach soit conservée.

## 0.11.1 - 2026-08-12

- Remplacement des listes déroulantes de présence et d’évaluation par des boutons radio en ligne.
- Sélection d’une compétence d’évaluation collective via une liste cliquable regroupée par domaine.
- Hover/focus renforcé sur les lignes nageurs et les évaluations collectives.
- Adaptation mobile/tablette des contrôles tactiles.

## 0.11.0 - 2026-08-12

- Ajout de la **Séance terrain V1** dans le portail Coach.
- Pointage Présent / Absent / Non pointé par groupe et par date.
- Bouton « Tous présents » pour accélérer le pointage.
- Une séance planifiée peut être marquée comme **Réalisée** puis repassée en **Prévue**.
- La séance ouverte affiche également les nageurs du groupe et leur statut de présence.
- Ajout d’une évaluation collective rapide : une compétence, tous les nageurs du groupe.
- Les écritures terrain restent réservées aux coachs titulaires ; les autres coachs conservent la consultation.
- Nouvelle table `e2n_attendance` et enrichissement de `e2n_scheduled_sessions`.

## 0.10.6 - 2026-08-11

- Ajout du champ `Droit à l’image` aux nageurs (Oui / Non / Non renseigné).
- Synchronisation Excel : lecture de la colonne `Droit à l'image` (OUI/NON).
- Synchronisation Excel : les notes médicales proviennent désormais uniquement de la colonne `Info médicale`; la colonne `Commentaire` est ignorée.
- Portail parents : affichage du droit à l’image.
- Portail Coach : indicateur de droit à l’image dans la liste du groupe et sur la fiche nageur.
- Ajout du hover/focus sur les cartes nageurs du portail Coach.

## 0.10.5 - 2026-08-11

- Correction de l’ouverture d’un groupe depuis le planning Coach (alias SQL).
- Ajout d’un hover et d’un focus clavier sur les créneaux du planning.

## 0.10.4 - 2026-08-11

- Corrige le planning Coach lorsque les groupes synchronisés n'ont pas de `weekday`/`start_time` structurés.
- Déduit automatiquement le jour et l'heure depuis les noms de groupes (ex. `Dauphin Lundi 17h15`).
- La synchronisation Excel persiste désormais ces créneaux et répare les groupes existants incomplets.
- Les groupes dont le créneau reste indéterminable sont affichés explicitement au lieu de disparaître du planning.

## 0.10.3

- Corrige un conflit avec les thèmes qui appliquent `visibility: hidden` ou `opacity: 0` aux contenus animés du portail Coach.

## 0.10.2

- Remplace le conteneur `<main>` interne du shortcode Coach par un `<div>` neutre pour éviter les conflits avec les thèmes WordPress.
- Force l’affichage du conteneur du portail après le chargement de la page.

## 0.10.1

- Correction du rendu du planning du portail coach.
- Chargement du CSS directement par le shortcode pour éviter les problèmes d’ID de page.
- Repli sur la saison active la plus récente si aucune saison courante n’est définie.
- Message explicite lorsqu’aucun groupe actif n’est disponible.

## 0.10.0

- Ajout du rôle Coach Ecole2Nat et du portail frontend mobile/tablette.
- Consultation de tous les groupes actifs par tous les coachs.
- Association Coach ↔ Groupe pour réserver les écritures aux titulaires.
- Planning hebdomadaire, affectation d'une séance au prochain créneau, vue séance et nageurs prévus.
- Fiche nageur avec informations médicales et évaluation express sécurisée.

## 0.9.2

- Ajuste à 10 px le padding vertical des titres de postboxes du back-office.
- Ajoute un tri interactif commun aux tableaux statiques du back-office Ecole2Nat'.
- Conserve le tri SQL/paginé existant de la liste des nageurs.
- Le tri est accessible au clavier et gère texte, nombres et dates françaises.

## 0.9.1

- Corrige la mise en page responsive de la page Groupes.
- Empêche les champs de formulaire de dépasser de leur postbox.
- Uniformise l'espacement des titres H2 dans les postboxes Ecole2Nat'.
- Ajoute un statut actif/inactif aux saisons.
- Une saison inactive rend automatiquement ses groupes indisponibles sans écraser leur statut individuel.
- Réactiver une saison restaure automatiquement la disponibilité des groupes qui étaient individuellement actifs.

## 0.9.0 — Saisons & historique pédagogique

- Historisation des évaluations par saison.
- Référentiel actif associé à chaque saison via `season_skills`.
- Historisation de l’affectation des nageurs aux groupes.
- Synchronisation Excel : le référentiel importé est associé à la saison cible.
- Portail parents : navigation entre les saisons disponibles.
- Migration automatique des évaluations et affectations existantes.

## 0.8.2 - Distribution globale des accès parents

- Vue Accès parents globale avec filtres multi-catégories, groupe, statut d'accès et présence d'email.
- Envoi en masse des accès non distribués sur tous les résultats filtrés.
- Préparation et impression de tous les coupons correspondant aux filtres.
- Les codes envoyés par email sont conservés dans le lot temporaire pour permettre une impression immédiate sans régénération.
- Ajout des colonnes Catégorie et Groupe dans la vue de distribution.
- Conservation des filtres après les actions de masse.

## 0.8.1 - Correctif envoi des accès parents

- Corrige le formatage `sprintf()` du corps d’email des accès parents.
- Évite l’interprétation des placeholders positionnels `%1$s`, `%2$s` et `%3$s` comme variables PHP.

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
