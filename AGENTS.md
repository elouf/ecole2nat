# AGENTS.md — Ecole2Nat'

## Objet du dépôt

Ecole2Nat' est un plugin WordPress de gestion pédagogique pour école de
natation. Il couvre le référentiel pédagogique, les groupes et nageurs,
la bibliothèque d'exercices, les évaluations saisonnières, le portail parents,
la synchronisation Excel et le portail Coach centré sur les progressions.

Avant toute modification importante, lire :

- `README.md`
- `CHANGELOG.md`
- `docs/database.md`
- `docs/ADMIN_GUIDELINES.md`
- les fichiers `TESTING-*.md` correspondant au domaine modifié
- `RELEASE-CHECKLIST.md` pour une préparation de livraison

## Prérequis et Composer

- WordPress 6.0 ou supérieur
- PHP 8.1 ou supérieur
- Composer 2
- extensions PHP requises par PhpSpreadsheet

Installer les dépendances verrouillées avec :

```bash
composer install
composer dump-autoload -o
```

Le dépôt suit certains fichiers générés de `vendor/`, notamment le chargeur
Composer, mais ignore les sources des dépendances tierces.

Règles Composer :

- Ne jamais modifier manuellement `vendor/`.
- Toute modification suivie dans `vendor/` doit résulter d’une commande
  Composer.
- Utiliser `composer install` pour installer les versions définies dans
  `composer.lock`.
- Ne lancer `composer update`, avec une cible aussi précise que possible,
  que lorsqu’une mise à jour de dépendance est explicitement demandée.
- Toute modification intentionnelle des dépendances doit inclure les
  changements cohérents de `composer.json`, `composer.lock` et des fichiers
  Composer suivis.
- Après `composer dump-autoload -o`, vérifier le diff et ne pas inclure de
  modifications générées sans rapport avec le changement réalisé.
- Ne pas forcer l’ajout des sources de dépendances actuellement ignorées.

## Architecture

Le point d’entrée du plugin est `ecole2nat.php`.

Le code applicatif est chargé en PSR-4 sous l’espace de noms
`Ecole2Nat\` depuis `src/`.

Principaux domaines :

- `src/Application` : amorçage du plugin
- `src/Admin` : menus, pages et actions du back-office
- `src/Database` : schéma, migrations et purge
- `src/Category`, `src/Reference`, `src/Exercise` : référentiel pédagogique
- `src/Season`, `src/Group`, `src/Swimmer` : organisation du club
- `src/Evaluation` : évaluations saisonnières
- `src/ParentPortal` : accès et rapports parents
- `src/Coach` : semaine type, droits et évaluations terrain
- `src/Synchronization` : lecture et synchronisation des classeurs
- `src/Support` : configuration et utilitaires transverses

Le flux recommandé est :

```text
Page ou portail WordPress
    → service métier
    → repository
    → $wpdb
```

Placer les règles métier dans les services et les requêtes SQL dans les
repositories. Éviter d’ajouter de la logique métier aux classes de rendu.

## Règles métier invariantes

### Saisons et groupes

- Une seule saison est courante.
- La saison courante doit rester active.
- Une saison inactive rend ses groupes indisponibles sans modifier leur
  statut individuel.
- Un groupe appartient à une saison et à une catégorie.
- Les listes opérationnelles utilisent uniquement les groupes actifs
  appartenant à une saison active.

### Nageurs et historique

- `e2n_swimmers.group_id` représente le groupe courant.
- L’historique saisonnier appartient à
  `e2n_swimmer_group_memberships`.
- Le droit à l’image conserve trois états : Oui, Non, Non renseigné.
- Une cellule Excel `Info médicale` non vide alimente uniquement le booléen
  `health_alert` ; son contenu textuel ne doit jamais être stocké.
- L’ancienne colonne `Commentaire` ne doit pas alimenter cet indicateur.
- Le portail Coach peut afficher l'existence d'une information de santé, mais
  aucun détail médical ne doit être stocké ou exposé.

### Référentiel et évaluations

- La hiérarchie est Catégorie → Domaine → Compétence → Exercice.
- L’activation saisonnière des compétences est stockée dans
  `e2n_season_skills`.
- Ne pas supprimer une compétence historique uniquement parce qu’elle
  disparaît du référentiel d’une nouvelle saison.
- Les statuts d’évaluation autorisés sont exclusivement :
  `not_observed`, `in_progress` et `acquired`.
- Il existe un niveau courant par nageur, saison et compétence.
- Une évaluation collective ne doit pas effacer une note individuelle.
- Vérifier côté serveur l’appartenance du nageur au groupe et à la saison.

### Exercices et anciennes séances

- La bibliothèque d'exercices reste une ressource pédagogique indépendante.
- Les anciennes données de séances sont conservées mais ne sont plus exposées
  dans les interfaces Coach ou administrateur.
- Ne pas réintroduire de planification datée sans validation explicite du
  besoin et de ses conséquences sur l'usage terrain.

### Coachs

- Les administrateurs peuvent consulter et modifier tous les groupes.
- Les coachs peuvent consulter et évaluer tous les groupes actifs.
- Les titulaires habituels sont une information de la semaine type et ne
  limitent pas les droits d'évaluation.
- Les contrôles de droits doivent être appliqués côté serveur, y compris
  pour AJAX ; masquer un contrôle dans l’interface ne suffit pas.
- Le portail Coach ne gère ni présences, ni remplacements datés, ni séances
  planifiées.
- Les accès Nageurs, Catégories et Semaine type ouvrent la même fiche
  d'évaluation et doivent conserver leur contexte de retour.
- La prévisualisation Parents depuis le portail Coach exige une session
  autorisée et un nonce ; elle n'utilise pas le code familial et ne doit pas
  compter comme une consultation parent.
- Le renvoi d'un code Parents depuis le portail Coach exige une confirmation,
  un nonce et la vérification serveur du groupe et du nageur ; le nouveau code
  ne doit jamais être affiché au coach.
- Tous les emails de codes Parents utilisent l'option
  `e2n_parent_email_signature`, modifiable uniquement par un administrateur.
- Chaque changement effectif de statut crée un événement historique avec
  date et utilisateur dans la même transaction que le niveau courant.
- Réenregistrer un statut identique ne crée pas d'événement.

### Portail parents

- Ne jamais stocker ni journaliser un code parent en clair.
- Toute régénération invalide immédiatement l’ancien code.
- Les prévisualisations administrateur doivent rester protégées par
  capacité et nonce.
- Ne jamais exposer l’adresse IP brute dans les journaux.
- Conserver la limitation des tentatives et les cookies signés.
- Le portail public doit rester marqué `noindex`.
- La chronologie d'une compétence est repliée par défaut et peut afficher le
  nom du coach, mais jamais une note interne ou un détail de santé.

### Synchronisation

- L’analyse seule ne modifie aucune donnée.
- La synchronisation doit rester idempotente et transactionnelle.
- La saison cible est choisie dans WordPress.
- Un nageur est identifié par licence, ou à défaut par
  nom + prénom + date de naissance.
- Ne pas supprimer les séances, évaluations, historiques ou accès parents
  lors d’une synchronisation.
- Journaliser chaque succès ou échec.

## Base de données et migrations

Toutes les tables passent par `Ecole2Nat\Support\Config::table()`.

Ne pas coder directement un préfixe WordPress.

Toute modification de schéma doit :

1. être ajoutée à `Database\Installer::createTables()` avec une syntaxe
   compatible `dbDelta()` ;
2. être idempotente ;
3. incrémenter `E2N_DB_VERSION` dans `ecole2nat.php` ;
4. préserver une mise à jour sans désactivation/réactivation ;
5. mettre à jour `docs/database.md` ;
6. ajouter une entrée à `CHANGELOG.md` ;
7. ajouter ou mettre à jour une recette `TESTING-*.md`.

Ne pas confondre :

- `E2N_VERSION` : version fonctionnelle du plugin ;
- `E2N_DB_VERSION` : version technique du schéma.

Le schéma ne possède pas de clés étrangères SQL. Toute nouvelle relation
doit donc être protégée par les validations applicatives et les règles de
suppression appropriées.

## Administration WordPress

- Les écrans administratifs exigent `manage_options`.
- Toute action modifiant l’état doit utiliser un nonce.
- Nettoyer les entrées et échapper les données au rendu.
- Utiliser les API WordPress lorsqu’elles existent.
- Suivre `docs/ADMIN_GUIDELINES.md`.
- Utiliser `Badge` pour les statuts.
- Les suppressions passent par `DeletionController` et
  `EntityDeletionService`.
- Les filtres, recherches, tris et paginations de grandes listes doivent
  être réalisés en SQL, pas après chargement complet en PHP.

## SQL et transactions

- Préparer toutes les requêtes contenant des valeurs externes avec
  `$wpdb->prepare()`.
- Contrôler explicitement les retours `false` de `$wpdb`.
- Utiliser une transaction pour les opérations écrivant plusieurs tables.
- Effectuer un `ROLLBACK` dès qu’une étape transactionnelle échoue.
- Ne jamais interpoler un identifiant SQL provenant d’une requête
  utilisateur.
- Préserver les contraintes uniques documentées dans `docs/database.md`.

## Frontend et AJAX

- Vérifier nonce, authentification, capacité et titularité avant toute
  écriture AJAX.
- Retourner des erreurs explicites sans divulguer d’informations sensibles.
- Maintenir le fonctionnement mobile et clavier.
- Pour l’autosave, préserver la garantie que la dernière sélection
  utilisateur est celle persistée.
- Ne pas dépendre d’un élément HTML structurel susceptible d’être masqué
  ou manipulé par un thème WordPress.
- Le portail Coach est conçu mobile/tablette en priorité pour une
  utilisation au bord du bassin.
- Minimiser le nombre d’actions nécessaires pour les opérations fréquentes.
- Préférer l’enregistrement immédiat aux boutons de validation pour les
  actions terrain fréquentes, avec retour visuel de succès ou d’échec.

## Suppressions et purge

- Refuser la suppression d’une entité structurante encore utilisée.
- Limiter les cascades aux agrégats dont le parent possède clairement le
  cycle de vie.
- Une purge globale doit conserver les tables, le plugin actif et les
  options `e2n_version` et `e2n_db_version`.
- Ne jamais lancer une purge pendant un test local sans demande explicite
  et sauvegarde préalable.

## Documentation et changelog

Toute modification fonctionnelle doit mettre à jour :

- `README.md` si le comportement utilisateur ou l’installation change ;
- `docs/database.md` si le schéma ou une relation change ;
- `CHANGELOG.md` sous `Non publié` ;
- au moins une recette `TESTING-*.md` adaptée.

Le changelog reste en ordre décroissant et ne comporte qu’un seul titre
principal.

Ne pas documenter une désactivation/réactivation comme méthode normale de
migration : `Installer::maybeUpgrade()` est le mécanisme attendu.

## Vérifications minimales

Avant de remettre une modification PHP :

```bash
find src -name '*.php' -print0 | xargs -0 -n1 php -l
php -l ecole2nat.php
php -l uninstall.php
git diff --check
```

Exécuter ensuite les recettes manuelles directement liées aux domaines
modifiés.

Pour une modification de schéma, vérifier au minimum :

- installation sur une base vide ;
- mise à jour depuis la version précédente sans réactivation ;
- présence des tables, colonnes et index attendus ;
- conservation des données existantes ;
- second chargement sans erreur ni modification indésirable.

Pour une modification du portail Coach ou Parents, vérifier aussi :

- administrateur ;
- utilisateur autorisé ;
- utilisateur authentifié mais non autorisé ;
- requête forgée ou nonce invalide ;
- affichage mobile ;
- absence de warning dans `debug.log`.

## Discipline de modification

- Préserver les changements locaux non liés.
- Ne pas modifier le comportement métier sans demande explicite.
- Éviter les refactorisations étendues dans un correctif ciblé.
- Ne pas ajouter de dépendance Composer sans justification.
- Ne pas modifier les données réelles ni lancer une purge automatiquement.
- Ne pas créer de commit, tag ou publication sans demande explicite.
- Signaler toute impossibilité de réaliser un test WordPress réel.
- Lors d’un bug, rechercher et corriger la cause racine avant d’ajouter
  un contournement CSS, JavaScript, PHP ou SQL.
- Avant toute modification importante de l’architecture, du modèle de
  données ou d’une règle métier existante, expliquer le changement
  envisagé et ses conséquences avant de l’implémenter.
