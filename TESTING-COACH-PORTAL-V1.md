# Recette — Portail Coach V1

1. Mettre à jour le plugin vers 0.10.0 puis recharger une page WordPress pour déclencher `maybeUpgrade()` : les tables `e2n_group_coaches` et `e2n_scheduled_sessions` sont créées automatiquement, sans réactivation du plugin.
2. Vérifier qu'une page publiée « Espace coach » existe avec le shortcode `[e2n_coach_portal]`.
3. Dans Ecole2Nat' > Coachs, choisir deux utilisateurs WordPress : les définir comme Coach. Affecter seulement le premier comme titulaire d'un groupe actif.
4. Se connecter avec le coach titulaire : après connexion, vérifier la redirection vers Espace coach, le planning hebdomadaire daté et le badge Titulaire.
5. Parcourir les semaines précédente et suivante, revenir à la semaine courante et vérifier que chaque journée, séance planifiée et remplacement correspond à la date affichée.
6. Ouvrir son groupe, utiliser « séance précédente », « cette semaine » et « séance suivante », puis contrôler que la date et le retour au planning restent cohérents.
7. Sur une date future, affecter une séance et vérifier que présences, évaluations et statut réalisée restent en lecture seule.
8. Le jour courant, vérifier que toutes les opérations terrain deviennent disponibles.
9. Sur une date passée, vérifier que le groupe et la séance restent consultables mais que toute écriture est masquée et refusée côté serveur.
10. Ouvrir un nageur le jour courant : vérifier l'information médicale et modifier une compétence. Recharger et vérifier la persistance.
11. Se connecter avec le second coach non titulaire : vérifier qu'il voit le même groupe, la séance et les nageurs, mais que les compétences sont en lecture seule.
12. Tenter manuellement un POST d'évaluation sur ce groupe avec le coach non titulaire : l'écriture doit être refusée côté serveur.
13. Affecter le second coach comme titulaire depuis le BO, recharger son portail et vérifier que les droits suivent la date consultée.
14. Comme administrateur, vérifier que les dates passées, courantes et futures restent modifiables.
15. Tester sur téléphone/tablette : navigation datée, planning, liste nageurs, fiche et évaluation doivent rester utilisables sans débordement horizontal.
