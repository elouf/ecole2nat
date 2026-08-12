# Test Portail Coach — correctif v0.10.2

1. Se connecter avec un compte Coach.
2. Vérifier la redirection vers « Espace coach ».
3. Vérifier que « Planning hebdomadaire » reste visible après le chargement complet de la page.
4. Vérifier que les groupes actifs s'affichent sous les jours correspondants.
5. Tester en largeur desktop puis mobile.
6. Ouvrir un groupe titulaire et un groupe non titulaire pour vérifier les droits d'édition/consultation.

Ce correctif remplace le conteneur HTML `<main>` interne du shortcode par un `<div>` neutre et force son affichage afin d'éviter les conflits CSS/JS avec le thème WordPress.
