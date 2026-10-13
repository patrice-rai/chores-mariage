# Plan — Phase 6 : recette, paquet et procédure de mise en ligne

Écrit le 13 octobre 2026 avant le code, en mode autonome (tentative 1). Restriction de la tentative : recette et
paquet en local seulement ; aucun accès à Hostinger, à un serveur, à un DNS ni à un compte ; aucun téléphone réel.

## 1. Code de production (petit, ciblé)

1. `app/version.php` : `VERSION = '1.0.0'`, lue par le tableau d'administration, le paquet et le README.
2. `app/maintenance.php` : sauvegarde (`VACUUM INTO`, repli par copie après `wal_checkpoint` si SQLite < 3.27),
   liste des sauvegardes, restauration (contrôle `integrity_check` + table `migrations`, copie de l'actuelle avant
   remplacement, remplacement atomique par `rename`), migrations en attente.
3. `scripts/sauvegarde.php` (CLI, aussi lançable par une tâche planifiée hPanel), `scripts/restaurer.php` (CLI).
4. Installation sans SSH : `GET|POST /installer`, répond 404 dès qu'un lien existe (installation faite) ou si la
   clé d'administration est vide ; la clé se tape dans un champ (jamais dans l'adresse : journaux d'accès) ;
   CSRF + débit (10/min, 60/h, comme l'entrée) ; migrations, import si `donnees/` est présent, premier lien
   (affiché une fois, `no-store`), cookies d'accès et d'administration posés.
5. Mise à jour sans SSH : le tableau d'administration dit « Base à jour » ou « N mise(s) à jour en attente » avec
   un bouton `POST /admin/migrer` ; « Faire une sauvegarde maintenant » (`POST /admin/sauvegarde`) et la liste des
   sauvegardes (nom, taille ; téléchargement par le gestionnaire de fichiers de hPanel, jamais par le site).
6. `scripts/importer-etat.php` : cherche les faits dans `docs/` (dépôt) puis dans `donnees/` (paquet).
7. `scripts/config-dev.php` : options `--dossier`, `--base`, `--url`, `--env` pour l'essai du paquet seulement
   (configuration générée dans le conteneur d'essai, jamais affichée).

## 2. Paquet

- `scripts/paquet.php` (dans le conteneur) : `dist/chores-mariage-v1.0.0.zip` par `PharData` (aucune extension
  `zip` requise), dossier racine `chores-mariage-v1.0.0/` : `public/`, `app/`, `scripts/` (production seulement :
  `migrer`, `importer-etat`, `nouveau-lien`, `sauvegarde`, `restaurer`), `donnees/` (`etat.json`,
  `distribution.md`), `config.example.php`, `composer.json` sans `require-dev`, `README.md`, `docs/deploy/`,
  `VERSION`. Exclus : `stockage/`, configuration, `tests/`, `node_modules/`, `vendor/`, `docs/` hors `deploy/`,
  `.claude/`, `.git/`, `public/chemin-app.php`, outils de développement (`e2e-outil`, `config-dev`, lint,
  contrastes, captures, polices, icônes, vidéo de démo, paquet). Contrôle à la fin : aucun chemin interdit dans
  l'archive, sinon échec.
- `scripts/essai-paquet.mjs` : conteneur `chores-mariage-paquet` (image locale `php:8.3-apache` du projet) sur
  127.0.0.1:8096, arborescence Hostinger simulée (`/home/u000000000/domains/exemple.test/public_html/chores/` et
  `…/chores-app/`), `chemin-app.php` hors racine web, `BASE_PATH=/chores`, `ENV=production` ; installation par
  `/installer` (chemin sans SSH) puis scripts (chemin SSH : migrer, importer, nouveau lien), parcours de fumée et
  axe (`tests/paquet/fumee.spec.ts`, `playwright.paquet.config.ts`), sauvegarde puis restauration sur une base vide ;
  conteneur supprimé à la fin (`docker rm -f chores-mariage-paquet`, ciblé).

## 3. Recette

- `tests/e2e/recette.spec.ts` : toutes les pages (cousin et administration) avec des données de test (« Section A »,
  « Section B », vidéo « Démo », vidéo WebM fabriquée) ; axe en clair, en sombre et en mode répétition ; aucune
  violation CSP dans la console ; aucune requête hors origine ; balayage 400 % (320 × 211) et 200 % ; mouvement
  réduit ; focus jamais masqué au clavier ; cibles ≥ 24 px (2.5.8) et relevé des cibles < 44 px ; inventaire des
  crochets écrit dans `docs/recette/crochets.json` ; mots anglais et emoji absents du texte rendu.
- `tests/phpunit/RecetteTest.php` : en-têtes sur JSON, 404 et `/m/{id}` ; journal et débit sans IP après un
  parcours ; renouvellement du lien qui fait tomber accès et prénoms ; cookies `HttpOnly` `SameSite` `Secure` en
  production ; installation, mises à jour, sauvegarde et restauration.
- `scripts/lighthouse.mjs` (dépendance de développement `lighthouse@13.5.0`, même version que le projet voisin) :
  mobile, 3 passes, médiane, sur les sept pages du prompt, cookies de test passés en en-tête, résultats dans
  `docs/recette/lighthouse.json` ; budgets mesurés sur les réponses réelles.
- Liens YouTube : `HEAD` si le réseau répond, sinon « non vérifié ».
- `docs/RECETTE.md` : tous les résultats avec les chiffres, la liste manuelle WCAG 2.2.

## 4. Documentation

`docs/deploy/README.md`, `verification.md`, `sauvegardes.md`, `mise-a-jour.md`, `lien.md` ;
`docs/A-FAIRE-PAR-PATRICE.md` réordonné (mise en ligne, téléphones, lecteurs d'écran, contenu à saisir) ;
`README.md` ; version 1.0.0 (`app/version.php`, `package.json`, README) ; tag local `v1.0.0`.

## 5. Revues et clôture

`securite-reviewer` (installation, maintenance, paquet), revue équivalente à `/security-review` en contexte vierge
(le skill exige `origin/HEAD`, dépôt distant interdit), `a11y-reviewer` (page d'installation, tableau
d'administration), revue de code (scripts, paquet) ; corrections jusqu'à zéro bloquant ; `composer check`,
`pnpm test:js`, `pnpm test:e2e` verts ; captures 390 et 1 280 px dans `docs/captures/phase-06/` ; PROGRESS ;
commit `chore(release): recette, paquet Hostinger et procédure de mise en ligne, version 1.0.0`.
