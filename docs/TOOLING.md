# Outillage

Écrit en phase 2 (13 octobre 2026). Toutes les commandes se lancent depuis `P:\PROJECT\chores-mariage`.

## Docker

- `docker compose up -d --build` (ou `pnpm up`) : construit l'image `chores-mariage-web:dev` (`php:8.3-apache` épinglée par empreinte, `gd`, `opcache`, `rewrite`, `headers`, Composer) et démarre le conteneur `chores-mariage-web` sur http://127.0.0.1:8095 (ports interdits sur ce poste : 3000, 3100, 3102, 5432, 5433, 7001, 8090, 3307).
- Au démarrage (`docker/demarrer.sh`) : création de `stockage/medias` et `stockage/sauvegardes`, génération de la configuration locale par `scripts/config-dev.php` si elle manque (secrets aléatoires, `ENV = dev`, `BASE_PATH` vide, jamais affichés), migrations, puis Apache.
- La racine web est `public/` ; `app/`, `scripts/`, `stockage/` et la configuration restent hors racine web, comme chez Hostinger.
- `docker compose stop` arrête le site. Ne jamais lancer `docker compose down -v` ni `docker … prune` : la base locale vit dans le volume `chores-mariage_stockage`.

## Où vit la base

- `stockage/base.sqlite` (SQLite en mode WAL) dans le volume Docker nommé `chores-mariage_stockage`, monté sur `/var/www/app/stockage` : le montage d'un dossier Windows ne garantit pas les verrous dont SQLite a besoin. La base n'apparaît donc pas dans l'explorateur Windows.
- Les scripts lancés par `docker compose exec` (root) prennent l'identité du propriétaire de `stockage/` (`www-data`) avant d'ouvrir la base, pour que les fichiers `-wal` et `-shm` restent accessibles à Apache.
- Migrations : `app/migrations/NNN-nom.sql`, appliquées par `docker compose exec -T web php scripts/migrer.php` (idempotent ; purge aussi le journal de plus d'un an et les fenêtres de débit de plus de deux jours).

## Le lien secret

- `docker compose exec -T web php scripts/nouveau-lien.php` crée un lien neuf (32 octets aléatoires ; seule l'empreinte SHA-256 est gardée) et l'affiche une fois. L'ancien lien et tous les cookies d'accès tombent aussitôt.
- Dans le site : `/admin/lien` (« Nouveau lien »), réservé à Patrice.
- Administration : visiter une fois par appareil `/admin/cle/<CLE_ADMIN>` (la clé est dans la configuration locale ou de production, que l'agent ne lit jamais), après être entré par le lien.
- Les tests de bout en bout créent leurs propres liens (par `scripts/e2e-outil.php`, refusé hors `ENV = dev`) : relancer `nouveau-lien.php` après une série de tests.

## Tests et contrôles

| Commande | Ce qu'elle fait |
| --- | --- |
| `docker compose exec -T web composer check` | `composer install`, `php -l` sur tout le PHP, `scripts/lint-inline.php` (aucun `style=`, `<script>` inline, `on*=`, ressource externe ni emoji), `scripts/contraste.php` (paires de couleurs ≥ 4,5:1 texte, ≥ 3:1 contours), PHPUnit (`tests/phpunit/` : jeton, cookies, CSRF, débit, migrations, routeur, parcours en mémoire, en-têtes réels d'Apache). |
| `pnpm test:js` | `node --test "tests/js/*.test.mjs"` : le hook des garde-fous refuse bien les commandes interdites (Node 24 ne prend pas un dossier en argument). |
| `pnpm test:e2e` | Playwright (Chromium) contre http://127.0.0.1:8095, projets `mobile` 390 × 844 et `bureau` 1 280 × 900, axe (WCAG 2.2 A/AA), interception qui fait échouer tout test dont une requête sortirait de 127.0.0.1:8095. |
| `pnpm check` | Les trois à la suite. |
| `pnpm captures [phase-NN]` | Captures des écrans en 390 et 1 280 px dans `docs/captures/phase-NN/`. |

Navigateurs Playwright : ceux du poste (`PLAYWRIGHT_BROWSERS_PATH`), version 1.63.0.

## Garde-fous Claude Code

- `.claude/settings.json` : refus de `git push`, `gh pr|repo|auth`, `--no-verify`, `rm -rf`, `Remove-Item -Recurse`, `docker … prune`, `docker compose down -v`, lecture ou écriture de la configuration et de `stockage/`.
- `.claude/hooks/garde-fous.mjs` (PreToolUse) : mêmes refus, plus les conteneurs des autres projets ; testé par `pnpm test:js`.
- `.claude/hooks/check.mjs` (Stop) : `composer check` dans le conteneur avant de rendre la main.
- `.claude/rules/` (a11y, design, vie-privee, donnees), skills `/phase` et `/a11y-check`, sous-agents `a11y-reviewer` et `securite-reviewer`.

## Recette, paquet et mise en ligne (phase 6)

- `pnpm lighthouse` : Lighthouse 13.5.0 (dépendance de développement), mobile par défaut, 3 passes, médiane, sur les sept pages de la spec § 13, avec la base de test des e2e ; résultats dans `docs/recette/lighthouse.json`.
- `tests/e2e/recette.spec.ts` (dans `pnpm test:e2e`) : toutes les pages en clair et en sombre, structure, CSP, zoom 400 %, focus, cibles, contrastes sur le rendu, crochets, mots anglais, poids ; rapports dans `docs/recette/*.json` (projet `mobile`).
- `docker compose exec -T web php scripts/paquet.php` : `dist/chores-mariage-vX.Y.Z.zip` (ignoré par git).
- `pnpm essai:paquet` : le zip décompressé dans un conteneur jetable `chores-mariage-paquet` (https://127.0.0.1:8096, arborescence de Hostinger imitée, `BASE_PATH=/chores`, `ENV=production`), `tests/paquet/fumee.spec.ts`, puis `docker rm -f chores-mariage-paquet`.
- Scripts de production (dans le paquet) : `migrer.php`, `importer-etat.php`, `nouveau-lien.php`, `sauvegarde.php [--garder=N]`, `restaurer.php <fichier> --oui`.
