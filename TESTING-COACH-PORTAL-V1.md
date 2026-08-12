# Recette — Portail Coach V1

1. Mettre à jour le plugin vers 0.10.0 puis recharger une page WordPress pour déclencher `maybeUpgrade()` : les tables `e2n_group_coaches` et `e2n_scheduled_sessions` sont créées automatiquement, sans réactivation du plugin.
2. Vérifier qu'une page publiée « Espace coach » existe avec le shortcode `[e2n_coach_portal]`.
3. Dans Ecole2Nat' > Coachs, choisir deux utilisateurs WordPress : les définir comme Coach. Affecter seulement le premier comme titulaire d'un groupe actif.
4. Se connecter avec le coach titulaire : après connexion, vérifier la redirection vers Espace coach, le planning hebdomadaire et le badge Titulaire.
5. Ouvrir son groupe, affecter une séance au prochain créneau, ouvrir la séance et contrôler parties, exercices, durées et notes coach.
6. Ouvrir un nageur : vérifier l'information médicale et modifier une compétence. Recharger et vérifier la persistance.
7. Se connecter avec le second coach non titulaire : vérifier qu'il voit le même groupe, la séance et les nageurs, mais que les compétences sont en lecture seule.
8. Tenter manuellement un POST d'évaluation sur ce groupe avec le coach non titulaire : l'écriture doit être refusée côté serveur.
9. Affecter le second coach comme titulaire depuis le BO, recharger son portail et vérifier que l'édition devient disponible.
10. Tester sur téléphone/tablette : planning, liste nageurs, fiche et évaluation doivent rester utilisables sans débordement horizontal.
