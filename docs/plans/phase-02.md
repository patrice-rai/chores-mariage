# Plan — phase 2 : socle et accès

Écrit le 13 octobre 2026, avant tout code, en mode autonome (la charte d'autonomie remplace la validation du plan par Patrice). Sources : `docs/prompts/phase-00.md`, `docs/prompts/phase-02.md`, `docs/spec/01-spec.md` § 1, 2, 3, 6, 10, 11, 14.

## Ordre de travail

1. **Garde-fous** : `CLAUDE.md` (≤ 100 lignes, depuis le contrat), `.claude/rules/{a11y,design,vie-privee,donnees}.md`, `.claude/settings.json` (refus + hooks), `.claude/hooks/garde-fous.mjs` (PreToolUse : `config.php`, `stockage/`, commandes interdites), `.claude/hooks/check.mjs` (Stop : `composer check` dans le conteneur, une seule relance grâce à `stop_hook_active`), `.claude/skills/phase/SKILL.md` (copie), `.claude/skills/a11y-check/SKILL.md`, `.claude/agents/{a11y-reviewer,securite-reviewer}.md`, `docs/A-FAIRE-PAR-PATRICE.md`.
2. **Docker et outils** : `Dockerfile` (image `php:8.3-apache` épinglée par empreinte, déjà présente sur le poste ; `gd`, `opcache`, `rewrite`, `headers`, Composer copié de l'image officielle), `docker/php.ini`, `docker/vhost.conf` (`DocumentRoot /var/www/app/public`, `AllowOverride All`), `compose.yml` (projet `chores-mariage`, service `web`, `127.0.0.1:8095:80`, projet monté, `stockage/` dans un volume nommé parce que SQLite en WAL n'est pas fiable sur un montage Windows ; au démarrage : `stockage/` créé, `config.php` généré s'il manque, migrations appliquées), `composer.json` (PHPUnit seul), `package.json` + `pnpm-workspace.yaml` (Playwright et axe, mêmes versions que le projet voisin, déjà en cache), `.editorconfig`, `README.md`.
3. **Noyau** : `public/index.php`, `app/bootstrap.php`, `app/routeur.php`, `app/bd.php`, `app/http.php` (réponses, redirections, cookies, rendu), `app/migrations/001-socle.sql` avec la semence des neuf cousins (prénoms de `CLAUDE.md`, ordre alphabétique, `est_admin = 1` pour Patrice, `dans_le_groupe_whatsapp = 0` pour Jessica et Stéphane d'après `etat.json`, rien d'autre), `scripts/migrer.php`, `config.example.php`, `scripts/config-dev.php`, `app/vues/gabarit.php`.
4. **Accès** : `app/acces.php` (jeton, cookies signés, CSRF, administration), `app/debit.php`, `app/journal.php`, contrôleurs `app/controleurs/{entree,admin}.php`, vues `entree`, `lien`, `moi`, `erreur`, `admin/tableau`, `admin/lien` ; `scripts/nouveau-lien.php`.
5. **Sécurité** : `app/securite.php` (en-têtes sur chaque réponse PHP), `public/.htaccess` (réécriture, refus, en-têtes des fichiers statiques), `public/robots.txt`.
6. **Style provisoire** : `public/css/base.css` (jetons clairs de la maquette, polices système, boutons 48 / 72 px, focus or doublé d'un filet sombre pour tenir 3:1).
7. **Outils de contrôle** : `scripts/lint.php` (`php -l`), `scripts/lint-inline.php` (aucun `style=`, `<script>` inline ni `on*=` dans `app/vues/` et `public/`), `scripts/contraste.php` (paires de couleurs utilisées, ≥ 4,5:1 texte, ≥ 3:1 contours).
8. **Tests** : PHPUnit (`tests/phpunit/`) et Playwright (`tests/e2e/`), outil de test `scripts/e2e-outil.php` (refusé hors `ENV = dev` : nouveau lien de test, remise à zéro du débit, clé d'administration lue par l'application, jamais affichée dans la sortie des tests), `tests/js/garde-fous.test.mjs` (preuve que le hook refuse les commandes interdites).
9. **Revues** : `securite-reviewer` (accès, cookies, en-têtes, débit, journal), `a11y-reviewer` (entrée, lien, Ma page, admin), `/security-review` ; corrections jusqu'à zéro finding bloquant.
10. **Fin** : captures 390 / 1 280 (`pnpm captures`), `docs/TOOLING.md`, `docs/PROGRESS.md`, commit local.

## Choix techniques

- **Routes** : celles de la spec § 2 limitées à la phase 2 ; niveaux d'accès par route : `public` (`/e/{jeton}`, `/lien`, `/admin/cle/{cle}`), `acces` (`/`, `POST /prenom`, `POST /changer`), `prenom` (`/moi`), `admin` (`/admin`, `/admin/lien`). Les routes admin répondent 404 à qui n'a pas le cookie d'accès valide et le cookie `cm_admin`.
- **Cookies** : `cm_acces = version.HMAC(secret, "acces:" + version)` ; `cm_prenom = id.HMAC(secret, "prenom:" + id)` ; `cm_admin = HMAC(secret, "admin:" + SHA-256(CLE_ADMIN))` (changer la clé invalide les appareils) ; `cm_csrf` aléatoire + champ caché `HMAC(secret, "csrf:" + jeton)` ; `HttpOnly`, `SameSite=Lax`, `Secure` hors `ENV = dev`, `Path = BASE_PATH` (ou `/`), 400 jours (CSRF : session).
- **Débit** : table `debit (cle, fenetre, compteur)`, `cle = SHA-256(action|IP|sel du jour)`, sel du jour = HMAC du secret et de la date UTC ; fenêtres minute (`AAAA-MM-JJTHH:MM`) et heure (`AAAA-MM-JJTHH`) ; purge des fenêtres de plus de deux jours.
- **Dates** : UTC en base (`gmdate('Y-m-d\TH:i:s\Z')`), affichage Europe/Paris.
- **`BASE_PATH`** : vide en développement (le site est à la racine de http://127.0.0.1:8095, `DocumentRoot` sur `public/` comme le demande le prompt) ; testé par PHPUnit avec `/chores`.
- **Lien affiché** : `URL_PUBLIQUE` facultatif dans `config.php` ; à défaut, l'hôte de la requête (admin) ou `http://127.0.0.1:8095` (script, en développement seulement).

## Ce qui reste hors de la phase

Jetons complets et thème sombre (phase 3), données des chansons (phase 4), répétitions, vidéos, PWA (phase 5), paquet de déploiement (phase 6).
