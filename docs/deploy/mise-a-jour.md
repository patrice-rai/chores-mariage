# Mettre en ligne une version suivante (sans perdre `stockage/`)

Une version suivante remplace le **code** (`app/`, `scripts/`, `public/`, `donnees/`, `docs/deploy/`) et ne touche
jamais à `chores-app/stockage/` (base, vidéos, sauvegardes), à `chores-app/config.php` ni à
`public_html/chores/chemin-app.php`, qui ne sont pas dans le paquet.

## 1. Sur ton PC

```
docker compose exec -T web php scripts/paquet.php
```

Le numéro de version est dans `app/version.php` (et dans le nom du zip). Lis `docs/PROGRESS.md` : une version qui
ajoute une migration le dit.

## 2. Chez Hostinger

1. **Sauvegarde** : `/admin` › « Base et sauvegardes » › « Faire une sauvegarde maintenant » (ou `php scripts/sauvegarde.php` en SSH), puis
   télécharge-la (`docs/deploy/sauvegardes.md` § 2).
2. Envoie le zip dans `/home/[compte]/domains/[domaine]/` (jamais dans `public_html/` : il s’y téléchargerait) et
   extrais-le : dossier `chores-mariage-vX.Y.Z/`. Repli 5 bis (tout sous `public_html/`) : supprime le zip dès
   l’extraction.
3. Dans `chores-app/`, supprime `app/`, `scripts/`, `donnees/` et `docs/`, puis déplace à leur place ceux de
   `chores-mariage-vX.Y.Z/` (ainsi que `config.example.php`, `composer.json`, `README.md`, `VERSION`). **Ne supprime
   ni `stockage/` ni `config.php`.**
4. Dans `public_html/chores/`, supprime tout **sauf `chemin-app.php`**, puis déplace dedans le contenu de
   `chores-mariage-vX.Y.Z/public/` (fichier caché `.htaccess` compris). Si tu avais ajouté les deux lignes de
   redirection HTTPS au `.htaccess` (`docs/deploy/README.md` § 3), remets-les.
5. Supprime le dossier `chores-mariage-vX.Y.Z/` restant et le zip.
6. Mises à jour de la base, s'il y en a :
   - SSH : `cd ~/domains/[domaine]/chores-app && php scripts/migrer.php` ;
   - sans SSH : `/admin/base` (« Base et sauvegardes ») › « Mettre la base à jour » (le bouton n'apparaît que s'il y a
     quelque chose à faire).
7. Si la version apporte de nouveaux faits du groupe : `php scripts/importer-etat.php` (idempotent ; il n'écrase
   jamais ce que tu as changé dans l'administration).
8. Vérifie : `/admin/base` affiche « Version du site : X.Y.Z. » et « Base : à jour. » ; puis `docs/deploy/verification.md`
   §§ 2 à 4.

Les téléphones des cousins voient l'encart « Nouvelle version : recharge » à leur prochaine visite (le service
worker change de version tout seul) ; le lien et les prénoms restent valables.

## 3. Revenir en arrière

Remets le code de la version précédente (même procédure avec l'ancien zip). Si la nouvelle version avait appliqué
une migration, restaure aussi la sauvegarde faite à l'étape 1 (`docs/deploy/sauvegardes.md` § 3) : un ancien code
ne connaît pas les nouvelles tables.
