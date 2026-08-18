# Checklist de stabilisation v0.8.0

Avant commit/tag :

- [ ] `composer install` puis `composer dump-autoload -o`
- [ ] lint PHP sans erreur
- [ ] `composer test` sans échec
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

## Construire le ZIP installable

Depuis la racine du plugin :

```bash
composer build
```

La commande réinstalle les dépendances verrouillées de production, optimise
l'autoload puis génère `build/ecole2nat-<version>.zip`. L'archive inclut le
code, les assets, les gabarits et `vendor/`, mais exclut Git, les tests et la
documentation de développement.

Vérifier dans l'archive la présence de :

- `ecole2nat/ecole2nat.php`
- `ecole2nat/src/`
- `ecole2nat/templates/`
- `ecole2nat/assets/`
- `ecole2nat/vendor/autoload.php`

Vérifier enfin que le `vendor/composer/platform_check.php` embarqué correspond
à la version PHP minimale annoncée par le plugin.

Commit conseillé :

```bash
git add .
git commit -m "release: stabilize Ecole2Nat v0.8.0"
git tag -a v0.8.0 -m "Ecole2Nat v0.8.0"
git push origin main
git push origin v0.8.0
```
