# Checklist de stabilisation v0.8.0

Avant commit/tag :

- [ ] `composer install` puis `composer dump-autoload -o`
- [ ] lint PHP sans erreur
- [ ] activation sur une base vide
- [ ] mise à jour depuis la version précédente sans désactivation/réactivation
- [ ] synchronisation d’un classeur avec saison cible
- [ ] second passage du même classeur sans doublons
- [ ] création/modification d’un nageur et accès à son évaluation
- [ ] prévisualisation du parcours parent depuis le BO
- [ ] envoi individuel puis groupé via WP Mail SMTP
- [ ] vérification qu’un renvoi invalide l’ancien code
- [ ] export CSV et coupons
- [ ] purge totale sur l’environnement de test puis reconstruction par synchronisation
- [ ] sauvegarde de la base avant déploiement réel

Commit conseillé :

```bash
git add .
git commit -m "release: stabilize Ecole2Nat v0.8.0"
git tag -a v0.8.0 -m "Ecole2Nat v0.8.0"
git push origin main
git push origin v0.8.0
```
