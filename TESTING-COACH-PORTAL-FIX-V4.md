# Recette — Portail Coach v0.10.4

1. Ouvrir l'Espace coach avec un compte `e2n_coach`.
2. Vérifier que les groupes dont le nom contient un jour et une heure (ex. `Dauphin Lundi 17h15`) apparaissent sous le bon jour, même si `weekday` et `start_time` sont NULL en base.
3. Ouvrir un groupe et vérifier que la date de prochaine séance est cohérente.
4. Relancer une synchronisation Excel : les groupes existants sans créneau structuré doivent recevoir automatiquement `weekday` et `start_time`.
5. Vérifier qu'un nom de groupe sans jour/heure reconnu apparaît dans « Groupes sans créneau reconnu » plutôt que de disparaître.
