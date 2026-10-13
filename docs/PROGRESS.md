# Journal d'avancement

## Préparation (13 octobre 2026) — étapes 2 et 3 du prompt de démarrage

### Fait

- `docs/spec/00-lecture-maquette.md` : lecture de la maquette Claude Design (écrans, jetons clair et sombre relevés tels quels, composants, états, hypothèses de démonstration, contrastes mesurés, écarts avec le brief, manques pour coder).
- `docs/spec/01-spec.md` : spécification complète (écrans et routes, modèle SQLite, lecteur, accès par lien secret + prénom, téléversement et HEVC, PWA, hébergement Hostinger, sécurité et vie privée, accessibilité WCAG 2.2 AA, données initiales importées d'`etat.json` et de `distribution.md`, tests).
- `docs/prompts/phase-00.md` (contrat de mission) et `docs/prompts/phase-02.md` à `phase-06.md` (un prompt par phase : socle et accès ; jetons, composants et lecteur ; données, chansons, sections, placements et suivi ; répétitions, vidéos, Jour J, PWA ; recette, paquet et mise en ligne).
- `docs/kit/autopilot/autopilot.mjs` et `charte-autonomie.md` (pilote automatique, phases 2 à 6, port 8095), `docs/kit/phase-skill/SKILL.md` (skill `/phase`), `docs/kit/entree-orchestrateur.json` (entrée pour `P:\PROJECT\orchestrateur\projets.json` ; au moment de l'écriture, l'entrée `chores-mariage` y figure déjà, identique).
- Pile retenue : celle de `CLAUDE.md` (PHP 8.3 sans framework + SQLite + HTML/CSS/JS natifs, Docker `php:8.3-apache` sur 8095, Hostinger mutualisé). Aucune autre pile proposée.

### Hypothèses prises (à confirmer ou à défaire par Patrice)

1. La note « on vise 2 min 30 » pour Yaaro Yaarodi, les sections, les BPM 96/128/72/112, l'ordre de passage 1-2-3-4-5, « qui lance la musique », les vidéos, les commentaires, la check-list de cinq lignes et les présents de la répétition passée sont des données de démonstration de la maquette : rien de tout cela n'est importé. Le site démarre sans section, sans placement, sans ordre de passage, avec la check-list réduite aux trois mots du brief (Tenue, Enceinte, Musiques coupées).
2. Tempos : 140 BPM pour Celebration of Varisu (brief et songbpm) ; les autres tempos de `docs/recherches/` sont importés avec la mention « à confirmer » (Yaaro Yaarodi 131, Thalapathy Kacheri ≈ 142, Innisai ≈ 97, Kanimaa ≈ 145) ; Patrice peut les régler en admin avec le bouton « Taper le tempo ».
3. Accès : un seul lien secret + prénom en cookie signé, comme `CLAUDE.md` ; l'administration de Patrice est protégée par une clé unique dans `config.php` (cookie posé une fois par appareil), parce que n'importe quel cousin peut choisir le prénom « Patrice ». Ce n'est ni un compte ni un mot de passe par personne.
4. Les vidéos de référence lues dans le lecteur sont des fichiers MP4 H.264 déposés par Patrice (convertis avec ffmpeg sur son PC) ; les tutoriels YouTube restent des liens sortants. Sans vidéo déposée, la page d'une chanson affiche « Pas encore de vidéo de référence ».
5. Le cache hors ligne se fait par vidéo entière (bouton « Garder sur mon téléphone »), pas par section comme le suggère la maquette : plus simple, et compatible avec les quotas Safari.
6. Hébergement : sous-dossier `/chores` du domaine de Patrice par défaut, `BASE_PATH` configurable ; `app/` et `stockage/` placés au-dessus de `public_html` avec un repli documenté si Hostinger ne le permet pas ; taille maximale d'upload 200 Mo et espace 5 000 Mo tant que les limites du compte ne sont pas connues.
7. Corrections visuelles par rapport à la maquette : en sombre, le grenat #a4284c ne sert plus de couleur de texte (2,6:1) ; l'orange « Choré à créer » est assombri pour atteindre 4,5:1 sur les cartes ; aucun texte sous 14 px ; « BPM », « Stop » et un titre de tutoriel anglais sont remplacés par des mots français.
8. Le sondage de dates reste externe (lien dans les réglages, Framadate conseillé) ; vide au départ avec un encart de repli.
9. Innisai : film laissé « [à confirmer] » (Varalaru d'après le brief, Godfather d'après le titre d'un lien), durée inconnue.

### À demander à Patrice avant la phase 2

1. La date (et le lieu) du mariage, et la durée accordée aux danses.
2. Sous-domaine (`chores.[domaine]`) ou sous-dossier (`[domaine]/chores`) chez Hostinger ; le domaine lui-même ; si le gestionnaire de fichiers permet d'écrire au-dessus de `public_html` ; si le SSH est disponible.
3. Taille maximale de téléversement du compte Hostinger (`upload_max_filesize`, `post_max_size` dans hPanel) et espace disque disponible pour les vidéos.
4. Jessica et Stéphane : comment leur transmettre le lien (ils ne sont pas dans le groupe WhatsApp) ; confirmer qu'ils sont bien d'accord pour figurer sur le site.
5. Confirmer la pile par défaut (`CLAUDE.md`) et le pilote automatique sur Opus, ou avancer phase par phase.
6. Faut-il que les créateurs de chorés (autres que Patrice) puissent poser les sections et placements eux-mêmes ? (par défaut : Patrice seul).
7. Le lien du sondage de dates à mettre dans les réglages (ou rien).
8. Quelle vidéo de référence pour chaque chanson (fichier à convertir), et la bonne version d'Innisai (film).

### Non vérifié

- Les limites PHP et SQLite du compte Hostinger (extensions `pdo_sqlite`, `gd`, écriture hors `public_html`) : aucun accès réseau ni compte utilisé pendant la préparation.
- Les tempos autres que Varisu.

## 2026-10-13 — Étape 1 : lecture de la maquette

- Maquette importée par le MCP claude_design (projet 7e21d055-cbed-4ce9-93fe-7f004a503149) et copiée à l'identique dans `docs/maquette/` : `Planches.dc.html`, `support.js` et `Chorés du mariage.dc.html` (le prototype que les planches importent écran par écran).
- Écarts relevés entre la maquette, le brief et les faits : `docs/spec/00-lecture-maquette.md`.
- Étapes 2 et 3 (spécification et plan en phases) lancées par Patrice le 13 octobre : voir « Préparation ».

## Phase 2 — Socle et accès (13 octobre 2026, mode autonome, tentative 1)

Tag `avant-phase-2` posé avant toute modification. Plan écrit avant le code : `docs/plans/phase-02.md`. Docker répond (moteur 24.0.6).

### Fait

1. **Garde-fous** : `CLAUDE.md` réécrit depuis le contrat (54 lignes) ; `.claude/rules/{a11y,design,vie-privee,donnees}.md` ; `.claude/settings.json` (refus de `git push`, `gh pr|repo|auth`, `--no-verify`, `rm -rf`, `Remove-Item -Recurse`, `docker … prune`, `docker compose down -v`, `docker volume rm`, lecture et écriture de la configuration et de `stockage/`) ; hook `PreToolUse` `.claude/hooks/garde-fous.mjs` (mêmes refus + conteneurs des autres projets + `stockage/` et bases SQLite) ; hook `Stop` `.claude/hooks/check.mjs` (`composer check` dans le conteneur, une seule relance par arrêt) ; skills `/phase` (copie de `docs/kit/phase-skill/SKILL.md`) et `/a11y-check` ; sous-agents `a11y-reviewer` et `securite-reviewer` ; `docs/A-FAIRE-PAR-PATRICE.md` créé avec les questions de la préparation et celles de la phase.
2. **Docker et outils** : `Dockerfile` (`php:8.3-apache` et `composer:2` épinglés par empreinte, déjà présents sur le poste ; `gd`, `opcache`, `rewrite`, `headers`), `docker/php.ini` (256M), `docker/vhost.conf` (racine `public/`, `ServerTokens Prod`, journal d'accès sans le lien ni la clé), `docker/demarrer.sh`, `compose.yml` (projet `chores-mariage`, `127.0.0.1:8095:80`, `stockage/` en volume nommé), `.dockerignore`, `composer.json` (PHPUnit seul), `package.json` + `pnpm-workspace.yaml` (`minimumReleaseAge 10080`, `strictDepBuilds`, `trustPolicy no-downgrade`), `.editorconfig`, `.gitattributes`, `README.md`, `docs/TOOLING.md`.
3. **Noyau** : `public/index.php` (avec `chemin-app.php` facultatif), `app/bootstrap.php`, `app/configuration.php`, `app/routeur.php` (404, 405 avec `Allow`, 404 sous `/admin`), `app/bd.php` (PDO SQLite, `foreign_keys`, WAL, `busy_timeout`, migrations transactionnelles), `app/http.php` (contexte, réponse, rendu, cookies), `app/migrations/001-socle.sql` (cousins, réglages, journal en ajout seul par déclencheur, débit, migrations ; les neuf cousins dans l'ordre alphabétique, Patrice administrateur, Jessica et Stéphane hors du groupe WhatsApp, rien d'autre), `scripts/migrer.php`, `config.example.php`, `scripts/config-dev.php`, vues `gabarit`, `entree`, `lien`, `moi`, `erreur`, `admin/tableau`, `admin/lien`.
4. **Accès** : `scripts/nouveau-lien.php` ; routes `GET /e/{jeton}`, `GET /lien`, `GET /`, `POST /prenom`, `POST /changer`, `GET /moi`, `GET /admin/cle/{cle}`, `GET /admin`, `GET|POST /admin/lien` ; cookies `cm_acces` (version du lien), `cm_prenom` (lié à la version : renouveler le lien fait rechoisir le prénom, spec § 6), `cm_admin` (lié à la clé), `cm_csrf` ; CSRF par double soumission signée + contrôle `Sec-Fetch-Site` / `Origin` ; journal `entree`, `prenom`, `admin_lien`, `admin_modif`, `refus_jeton`, `refus_debit`, sans IP.
5. **Sécurité** : `app/securite.php` (CSP sans `unsafe-inline`, `nosniff`, `no-referrer`, `Permissions-Policy`, COOP, CORP, `X-Frame-Options`, `X-Robots-Tag`, HSTS et `upgrade-insecure-requests` en production), `Cache-Control: no-store` par défaut, `public/.htaccess` (réécriture, `-Indexes`, refus des fichiers cachés, bases, PHP hors `index.php`, `chemin-app.php` ; en-têtes et cache long des fichiers statiques), `public/robots.txt` ; débit : entrée 10/min et 60/h, prénom 20/h, clés SHA-256 salées par jour (IPv6 par /64), 429 en français avec `Retry-After`.
6. **Style provisoire** : `public/css/base.css` (jetons clairs de la maquette, polices système, prénoms en boutons de 72 px sur trois colonnes, boutons de 48 px, focus or doublé d'un filet sombre).
7. **Contrôles** : `scripts/lint.php`, `scripts/lint-inline.php` (aucun `style=`, script inline, `on*=`, `javascript:`, ressource externe ni emoji), `scripts/contraste.php` (17 paires lues dans les variables CSS).
8. **Tests** : PHPUnit (65 tests : jeton bon / faux / révoqué, cookies valides / altérés / d'un autre usage, CSRF absent / faux / autre origine / `Origin: null`, débit (11ᵉ essai refusé, clé sans IP, IPv6), migrations idempotentes, journal en ajout seul, routeur et `BASE_PATH=/chores`, parcours complet en mémoire, formulaire « Nouveau lien » renvoyé, en-têtes réels d'Apache sur 200 / 302 / 303 / 404 / 405 / 429 / statiques) ; Node (`tests/js/garde-fous.test.mjs`, 4 tests) ; Playwright (`mobile` 390 × 844 et `bureau` 1 280 × 900, axe WCAG 2.2 A/AA sur `/lien`, `/`, `/moi`, `/admin`, `/admin/lien`, interception qui fait échouer toute requête hors 127.0.0.1:8095, 8 scénarios par projet) ; captures 390 et 1 280 dans `docs/captures/phase-02/` (entrée, lien manquant, Ma page, page introuvable, tableau et nouveau lien de l'administration).
9. **Preuve des garde-fous** : pendant la phase, le hook a refusé réellement `git push --dry-run` (« Refusé par .claude/hooks/garde-fous.mjs : git push (interdit : aucun dépôt distant) »), un heredoc contenant `rm -rf`, une commande citant `stockage/` et une commande citant la configuration secrète ; `pnpm test:js` le prouve aussi de façon reproductible.

### Critères d'acceptation

- `docker compose up -d` puis `php scripts/migrer.php` et `php scripts/nouveau-lien.php` dans le conteneur donnent un lien qui ouvre l'entrée : vérifié (le conteneur migre au démarrage ; `migrer.php` relancé répond « Base à jour : aucune migration à appliquer » ; le lien produit par `nouveau-lien.php`, suivi par curl sans être affiché, répond 303 vers `/`, et `/` montre les neuf boutons prénom).
- Sans cookie, `/moi` redirige vers `/lien` : vérifié (PHPUnit et e2e).
- `composer check` vert, `pnpm test:e2e` vert dans les deux projets, `pnpm test:js` vert : voir « Vérification finale ».
- Aucun style ni script inline (`lint-inline`), aucune requête externe (interception e2e), configuration secrète absente du dépôt (ignorée, absente de `git status`).

### Décisions prises sans validation

1. **`BASE_PATH` vide en développement** (site à la racine de http://127.0.0.1:8095, `DocumentRoot` sur `public/` comme le demande le prompt) ; `/chores` reste le défaut de `config.example.php` et est couvert par PHPUnit. Revenir : mettre `BASE_PATH` à `/chores` dans la configuration locale et ajouter un `Alias` dans `docker/vhost.conf`.
2. **`stockage/` dans un volume Docker nommé** (`chores-mariage_stockage`) et non dans le dossier Windows : SQLite en WAL a besoin de verrous que le montage 9p de Docker Desktop ne garantit pas. Revenir : retirer la ligne `stockage:` de `compose.yml`.
3. **Scripts en root → identité du propriétaire de `stockage/`** (`prendre_identite_du_stockage()`) : lancés par `docker compose exec`, ils créaient des fichiers `-wal`/`-shm` appartenant à root et Apache attendait ses verrous (requêtes de 14 s). Sans effet chez l'hébergeur. Revenir : retirer l'appel en tête des scripts et lancer `docker compose exec -u www-data`.
4. **`Origin: null` accepté** : avec `Referrer-Policy: no-referrer` (imposée par la spec), Chromium envoie `Origin: null` sur nos propres formulaires. Le refus repose sur `Sec-Fetch-Site` (posé par le navigateur) et sur le jeton CSRF signé, exigé dans tous les cas. Revenir : passer à `Referrer-Policy: same-origin` (écart à la spec, à faire valider par Patrice) et refuser `null`.
5. **Focus** : contour or 3 px de la maquette doublé d'un filet sombre (boutons), d'un fond or souligné (liens) ou d'un contour sombre (champs, tableaux défilants), parce que l'or seul fait 2,6:1 sur le jasmin (WCAG 1.4.11 demande 3:1). Revenir : `:focus-visible` dans `public/css/base.css`.
6. **Administration : cookie d'accès + `cm_admin` exigés, prénom non exigé** ; toute route `/admin` répond 404 sans eux (y compris une méthode refusée). Revenir : niveau `admin` dans `app/acces.php`.
7. **Lien affiché une seule fois** (réponse directe au `POST /admin/lien`, `no-store`) : la base ne garde que l'empreinte, comme le veut la spec ; le tableau dit « pour le redonner, crée-en un nouveau ». Un formulaire renvoyé (champ `version` périmé) ne recrée pas de lien (409), et l'appareil de Patrice reçoit le cookie d'accès du nouveau lien.
8. **Cookie prénom lié à la version du lien** (spec § 6 « renouveler le lien efface tout ») ; la spec § 6 est mise à jour en conséquence (`id.HMAC(secret, id:version)`).
9. **`URL_PUBLIQUE`** ajouté à `config.example.php` (facultatif) pour écrire le lien complet ; à défaut, l'hôte de la requête (administration) ou `http://127.0.0.1:8095` (script en développement), sinon « https://[domaine] ».
10. **Pied de page « Un souci ? Écris à Patrice [contact] »** gardé tel que le prompt l'écrit, en attendant le moyen de contact (A-FAIRE n° 9).
11. **Texte de Ma page en phase 2** : « Ta page se remplit bientôt : tes chorés, les répétitions et les vidéos des cousins arrivent ici. » (texte d'interface, aucune donnée). Revenir : `app/vues/moi.php`.
12. **Outil de test `scripts/e2e-outil.php`** (refusé hors `ENV = dev`) : remet le débit à zéro et crée un lien pour chaque test ; sa sortie n'est jamais affichée ; les traces Playwright sont coupées (`trace: 'off'`) pour ne pas garder de lien. Il sera exclu du paquet en phase 6.
13. **`node --test "tests/js/*.test.mjs"`** au lieu de `node --test tests/js` : Node 24 ne prend pas un dossier en argument (`pnpm test:js`). Délais : 120 s par test Playwright, 30 s pour les requêtes HTTP de PHPUnit, parce que les écritures SQLite dans la VM Docker prennent parfois plusieurs secondes quand le poste est chargé (un autre projet construisait des images en même temps).
14. **`/security-review`** ne peut pas tourner : le skill exige une branche distante `origin/HEAD`, et créer un dépôt distant est interdit. Remplacé par une revue équivalente en contexte vierge (même cahier des charges) ; à relancer par Patrice s'il ajoute un jour un dépôt distant.
15. **Dépendances** : `phpunit/phpunit ^11.5` (Composer, outillage de test seulement, BSD-3, mainteneur Sebastian Bergmann), `@playwright/test 1.63.0` et `@axe-core/playwright 4.13.0` (pnpm, Apache-2.0 et MPL-2.0, mêmes versions que le projet voisin, déjà dans le magasin pnpm et publiées depuis plus de 7 jours). Aucune dépendance côté client.

### Non fait, non vérifié

- Thème sombre, polices Yeseva One et Nunito Sans, composants : phase 3 (la feuille provisoire n'a que le thème clair et des polices système).
- Coupure possible d'un prénom long (« Catherine ») au zoom 200 % avec l'espacement de texte 1.4.12 : à mesurer en phase 3 avec la vraie police.
- Les 403 produits par Apache pour les fichiers refusés n'ont pas les en-têtes de sécurité (`Header setifempty` crée des doublons sur les pages PHP) ; ces réponses n'ont aucun contenu.
- Comportement chez Hostinger (journaux d'accès, adresse IP vue derrière un éventuel proxy, écriture hors `public_html`) : non vérifiable sans compte, voir A-FAIRE n° 13 à 15.
- Lecteurs d'écran réels (VoiceOver, TalkBack) : A-FAIRE n° 12.

### Revues

- **securite-reviewer** (contexte vierge) : 0 bloquant ; 4 importants (journal d'accès Apache avec le lien, formulaire « Nouveau lien » renvoyé, `no-cache` au lieu de `no-store`, sous-dossier partageant l'origine du domaine) et 9 mineurs. Corrigés : journal d'accès masqué, garde de version (409), `no-store`, 405 → 404 sous `/admin`, chemin `//`, IPv6 /64, refus journalisés une fois par minute, purge du journal au fil de l'eau, cookie prénom lié à la version, traces Playwright coupées, `.well-known/acme-challenge` permis. Documentés pour Patrice : sous-domaine conseillé, journaux d'accès de l'hébergeur, IP derrière proxy. Laissés : `Origin: null` (décision 4), en-têtes des 403 d'Apache.
- **Revue type `/security-review`** (contexte vierge) : « Aucune vulnérabilité retenue. Findings bloquants : 0 ».
- **a11y-reviewer** (contexte vierge) : 0 bloquant ; 4 importants (focus or seul sur champs et tableaux, liens isolés de 24 px, message « Formulaire expiré » trompeur, « [contact] ») et 6 mineurs. Corrigés : contour sombre, liens isolés en boutons de 48 px, message renvoyant au bouton, titre « Nouveau lien créé », soulignement au focus des liens, légende du journal hors du tableau, tableau des prénoms sans zone défilante, jeton `--t-bouton`. Laissés : « [contact] » (décision 10), mesure des prénoms au zoom (phase 3).
- **Contre-revue des corrections** (contexte vierge, dernière revue) : « Findings bloquants : 0 ». Les huit corrections sont jugées justes ; ses sept points mineurs ou importants ont été corrigés : rechargement de « Nouveau lien » qui menait à un 404 (l'appareil de Patrice reçoit désormais le nouveau cookie d'accès, le rechargement aboutit au 409), décodage d'URL qui contournait le masque du journal (supprimé), purges protégées par `try/catch`, IPv4 écrite en IPv6, texte et style de la page 409 (avertissement orange, `h1` = titre), spec § 6 mise à jour, conseil de changer la clé d'administration (A-FAIRE n° 14).

### Vérification finale (13 octobre 2026)

- `docker compose exec -T web composer check` : « Lint : 38 fichier(s) PHP, 0 erreur(s) », « Lint inline : 9 fichier(s), 0 défaut(s) », « Contrastes : 17 paire(s) mesurée(s), 0 échec(s) », « OK (65 tests, 424 assertions) ».
- `pnpm test:js` : 4 tests, 4 réussis.
- `pnpm test:e2e` : 16 réussis (8 en `mobile`, 8 en `bureau`), 2 ignorés (les captures, lancées à part par `pnpm captures` : 2 réussis).
- Captures à jour dans `docs/captures/phase-02/` (12 images, 390 et 1 280 px).

Phase 2 terminée le 2026-10-13 (socle et accès)

## Phase 3 — Jetons, composants et lecteur (13 octobre 2026, mode autonome, tentative 1)

Tag `avant-phase-3` posé avant toute modification. Plan écrit avant le code : `docs/plans/phase-03.md`. Docker répond.

### Fait

1. **Jetons** (`public/css/jetons.css`, seule feuille qui contient des couleurs) : clair sur `:root, [data-theme="clair"]`, sombre sur `[data-theme="sombre"]` et, sans choix, sous `@media (prefers-color-scheme: dark)` (copie exacte, vérifiée) ; tailles, hauteurs 44 à 72, rayons 6 à 24, espacements 4 à 40 en `rem` ; `tabular-nums` global ; focus 3 px. Jetons ajoutés aux noms de la spec : `--orange-texte`, `--sur-orange`, `--accent`, `--lien`, `--grenat-inverse`, `--voile`, `--sur-voile`, `--decompte`, `--focus-filet`. **Orange « Choré à créer » assombri : `#a04a08`** (5,08:1 sur `--surface`, 4,65:1 sur `--surface-2`, 5,81:1 sur le fond ; `#b4530a` reste le contour).
2. **Contrastes** : `app/jetons.php` (paires autorisées, lecture des jetons, formule WCAG, voile composé sur blanc) ; `scripts/contraste.php` réécrit (fichier en argument pour les tests, échoue sur un jeton manquant, une paire sous le seuil ou des blocs sombres différents) : **70 paires mesurées, 0 échec** ; `/gabarits` affiche les mêmes mesures.
3. **Lint** : `scripts/lint-inline.php` refuse aussi toute couleur hors `jetons.css` (hexadécimal, `rgb()`, `hsl()`… dans CSS, JS et vues) ; racine en argument pour les tests. Le critère « `grep` des hexadécimaux hors `jetons.css` vide » est donc contrôlé à chaque `composer check`.
4. **Bascule de thème** : `POST /theme` (CSRF, cookie `cm_theme` clair ou sombre, retour limité aux chemins internes), `data-theme` rendu par le serveur (sans éclair), deux boutons « Mode sombre » dont un seul visible selon le thème effectif (aussi sans JavaScript), `aria-pressed` + état écrit ; `public/js/site.js` bascule sans rechargement et replace le focus.
5. **Polices** : `@fontsource/yeseva-one@5.3.0` et `@fontsource-variable/nunito-sans@5.3.0` (dépendances de développement, OFL-1.1, publiées en juillet 2026) ; `scripts/polices.mjs` copie 4 woff2 (latin, latin-ext, version dans le nom) dans `public/polices/` et les licences dans `LICENCES.md` (lisible en texte brut). Tailles : Yeseva latin 17 492 o, latin-ext 12 920 o ; Nunito Sans variable latin 31 076 o, latin-ext 27 908 o ; **total 89 396 o (87,3 Ko ≤ 120)** ; préchargement des deux fichiers latin.
6. **Composants** (`app/vues/composants/*.php` appelés par `Chores\composant()`, `public/css/composants.css`, BEM français) : barre haute, bascule de thème, bouton prénom, carte « Prochaine répétition » (réponses, personne n'a répondu, date pas fixée avec lien de sondage ou encart de repli), bouton de présence, carte de chanson (progression par classes `p-N`, à créer, décidée, en attente, sans sections), encarts (pointillé, orange « Qui s'en occupe ? », attente), fil « Ce qui bouge », lecteur, ligne de section (trois niveaux, « ✓ » et « en cours »), mini-scène, scène de placements (grille 20 × 20 par classes `col-N lig-N`, points 58/68 px abrégés à trois lettres, quadrillage et flèches en SVG `aria-hidden`, liste des positions en mots, select + ← →, « Me surligner », ↑ ↓ ← →), grille « Qui maîtrise quoi » (`<table>`), lien tutoriel (https seulement), carte vidéo, commentaire horodaté, bannière hors ligne, frise Jour J, ligne de check-list, décompte, champs (texte, select, fichier, case, zone, erreur reliée), confirmation `role="status"`, encart « Nouvelle version ». `base.css` réécrit sans couleur ; `gabarits.css` pour la page de gabarits.
7. **Lecteur** : `public/js/lecteur-calc.js` (fonctions pures), `decompte.js`, `lecteur.js` (22,5 Ko ≤ 25) : vitesses + `preservesPitch`, miroir par classe (vidéo seule), boucle sur `timeupdate` + `requestVideoFrameCallback` + `ended`, décompte 5-6-7-8 à `max(380, 60000 / bpm / vitesse)` avec `aria-live="polite"`, Arrêter / Pause, « Touche Lecture à nouveau » sur refus de lecture automatique, sections par lien `?section=N` (sans JS) ou en place (avec JS, adresse mise à jour), commentaires horodatés qui positionnent le lecteur et la section, raccourcis Espace M B 1 2 3 N P Échap (actifs si le focus est dans le lecteur, partout en mode répétition ; désactivables, mémorisés), vitesse et miroir mémorisés par chanson, voile de chargement annoncé. Sans JavaScript : contrôles natifs.
8. **Mode répétition** `GET /chanson/demo/repeter?section=N` : toujours sombre, section et comptes en surimpression, statut « Miroir · 0,75 × · Boucle », Lecture et Section suivante 72 px, vitesses et commandes 60 px, Plein écran (`requestFullscreen`, état écrit, absent si non disponible), Quitter ; `wakeLock` pendant la lecture ; décompte 260 px ; au bureau, vidéo centrée (1 100 px au plus).
9. **Gabarits** `GET /gabarits` (+ `POST /gabarits/essai`, `GET /gabarits/demo.webm`) : palette des deux thèmes et contrastes mesurés, typographie, espacements, rayons, cibles, chaque composant dans ses états, lecteur « Démo » (Section A/B/C), lecteur sans vidéo ni section, états figés (miroir + boucle, décompte, chargement, gardée), décompte avec « Rejouer », scène (huit points : les neuf cousins sauf Nadine), lien vers le mode répétition. Lien « Voir les gabarits » sur `/admin`.
10. **Vidéo de démonstration** : `scripts/video-demo.mjs` (Chromium de Playwright, `canvas` + `MediaRecorder`, 20 s de bandes colorées et numéro de seconde, durée réécrite dans l'en-tête WebM, dépôt dans le volume par `docker compose exec -u www-data`) ; jamais versionnée ; lancée par la préparation globale des tests si elle manque. Servie par `servir_fichier()` (`app/medias.php` : 200 / 206 / 416, `Accept-Ranges`, envoi par morceaux), réutilisable pour `/m/{id}` en phase 5.
11. **Tests** : PHPUnit 94 tests (`InterfaceTest` : thème, retour, gabarits dev/production, méthodes refusées, mode répétition, plages HTTP, formats, liens https, hôte local, fins de chaîne ; `OutilsTest` : contrastes (vrais jetons, faux jeton à 4,1:1, orange d'origine, blocs sombres différents), lint inline (`style=`, script inline, `on*=`, couleur en dur), budgets, licences) ; Node 15 tests (`tests/js/lecteur-calc.test.mjs` : 140 tpm à 0,5 × = 857 ms, plancher 380, tempo inconnu, boucle, formats) ; Playwright 54 tests (27 par projet).
12. **Documentation** : `docs/DESIGN.md`, `docs/A-FAIRE-PAR-PATRICE.md` (n° 16 à 21).

### Critères d'acceptation

- `/gabarits` montre tous les composants et le lecteur dans les deux thèmes : vérifié (e2e axe en clair et en sombre, captures).
- Aucune couleur hors jetons : vérifié par `lint-inline.php` (règle « couleur hors jetons », 0 défaut).
- `scripts/contraste.php` vert avec les valeurs corrigées : 70 paires, 0 échec.
- Polices ≤ 120 Ko : 87,3 Ko (PHPUnit).
- `composer check`, `node --test` et `pnpm test:e2e` verts : voir « Vérification finale ».
- Revue `a11y-reviewer` à zéro bloquant : voir « Revues ».

### Décisions prises sans validation

1. **Nunito Sans variable (axe `wght`)** au lieu de trois graisses statiques : 59 Ko pour latin + latin-ext contre 83 Ko, toutes les graisses. Revenir : `@fontsource/nunito-sans` et trois `@font-face` dans `jetons.css`.
2. **Orange texte `#a04a08`** : plus petit assombrissement qui passe 4,5:1 sur `--surface`, `--surface-2` et le fond. Revenir : `--orange-texte` dans `jetons.css` (contraste.php dira si ça passe).
3. **Jetons `--accent`, `--lien`, `--grenat-inverse`** en plus de ceux de la spec : rendent explicites les corrections de la spec § 9 (en sombre, contours et pleins des boutons choisis en blanc cassé, liens en or éclairci). Revenir : `jetons.css` et `composants.css`.
4. **Budget CSS mesuré compressé** : 40 Ko compressés (≈ 11 Ko aujourd'hui), garde-fou à 60 Ko non compressés (≈ 53 Ko) ; `.htaccess` compresse les feuilles et scripts (jamais les pages : jeton CSRF, attaque BREACH ; `no-gzip`, `no-brotli`, `zlib.output_compression` coupé). Revenir : `OutilsTest::testBudgets` (et réécrire les feuilles de façon plus compacte).
5. **Modules JavaScript en `Cache-Control: no-cache`** (spec § 10 : `immutable`) : les modules s'importent sans empreinte dans l'adresse ; revalidation par 304. Revenir : `.htaccess`, avec des noms de fichiers versionnés.
6. **Bascule de thème** : libellé stable « Mode sombre » + état écrit + `aria-pressed`, au lieu du libellé qui change de la maquette ; deux boutons rendus et un seul visible, pour que le rendu sans JavaScript et sans choix enregistré suive le système. Revenir : `bascule-theme.php`.
7. **Points de scène abrégés à trois lettres partout** (prénom entier dans la liste textuelle et le `title`), mini-scène en points de 36 px. Quadrillage en SVG (la maquette utilisait un dégradé, interdit).
8. **« Démo » sans tempo** : décompte à 120 avec « tempo à régler » (règle des tempos inconnus) ; positions de scène géométriques ; aucune de ces valeurs n'est importée.
9. **Niveau de route « gabarits »** : libre si `ENV = dev` et hôte local (127.0.0.1, localhost, ::1), sinon cookie d'administration, contrôlé avant le CSRF, méthode refusée → 404. `/chanson/{slug}/repeter` sert `demo` seulement et porte ce niveau ; à passer au niveau `prenom` en phase 4 (commentaire dans `app/routes.php`).
10. **Raccourcis clavier** actifs seulement si le focus est dans le lecteur ou sa liste de sections (partout en mode répétition), et seulement s'il y a une vidéo : sur une page qui défile, Espace garde son rôle. Revenir : `ecouterClavier()` dans `lecteur.js`.
11. **Annonce à chaque temps du décompte** gardée (spec § 4), malgré la suggestion de la revue d'une annonce unique.
12. **Pas de pied « Un souci ? » en mode répétition** (plein écran) : WCAG 3.2.6 n'exige que le même emplacement quand l'aide est présente. Revenir : `'pied' => false` dans `repeter()`.
13. **Mode répétition sur mobile** : écran à hauteur `max(15rem, 45vh)` (et non tout l'espace restant) pour que le décompte de 260 px tienne toujours dans l'écran, même sur un petit téléphone ou avec le texte agrandi.
14. **Lien d'évitement** hors écran sans focus (`left: -100vw`) au lieu d'une translation, pour qu'il ne recouvre jamais le premier bouton de la barre haute.
15. **`ici()`** ne recopie que `section` et `surligner` dans le champ « retour » des formulaires.

### Non fait, non vérifié

- Téléphones réels (Safari iPhone : lecture après le décompte, plein écran, `wakeLock` ; Android) et lecteurs d'écran réels : A-FAIRE n° 17 et 18.
- Firefox et Safari de bureau : seuls Chromium (Playwright) a servi.
- Compression et en-têtes chez Hostinger (LiteSpeed, Brotli) : A-FAIRE n° 20.
- « Ma place » et la scène de `/gabarits` ne suivent pas la section choisie en JavaScript (rendues par le serveur pour `?section=`) : à traiter en phase 4 avec les vraies pages.
- Les 403 produits par Apache n'ont toujours pas les en-têtes de sécurité (connu depuis la phase 2, sans contenu).
- Jeton CSRF non masqué à chaque réponse (atténuation BREACH supplémentaire proposée par la revue) : la compression des pages est coupée à la place.
- La durée écrite dans la vidéo de démonstration vient de l'horloge (≈ 20,1 s), pas des images.
- Lighthouse : non lancé (phase 6).
- `If-Range` et `ETag` pour les vidéos : phase 5 (`/m/{id}`, avec `Cache-Control: private, max-age=31536000, immutable`).

### Revues

- **Première série** (contexte vierge) : `securite-reviewer` 0 bloquant, 5 mineurs ; revue de code (équivalent `/code-review`) 0 bloquant, 5 importants, 9 mineurs ; `a11y-reviewer` 2 bloquants, 3 importants, 7 mineurs. Corrigés : niveau « gabarits » contrôlé avant le CSRF et 404 sur méthode refusée, `ici()` restreint, compression des pages coupée, licence lisible, liens https seulement, `+` dans le retour ; saut horodaté sans rechargement avec section et reprise, boucle au bout de la vidéo, raccourcis sans vidéo, verrou d'écran, `NotAllowedError`, adresse mise à jour, texte du tempo, segment WebM vérifié, faux positifs du lint, test d'animation déterministe, code mort ; contraste du fil au focus, décompte jamais coupé, navigation de la scène, focus des liens, zones `role="status"` toujours rendues, raccourcis limités au lecteur, annonces, Section suivante en `<button>`, 8 px entre cibles, survol des niveaux.
- **Deuxième série** : `a11y-reviewer` 2 bloquants (décompte coupé sur petits téléphones ; flèches de la scène mal placées par `:first-of-type`), 1 important, 7 mineurs ; tous corrigés (écran à hauteur connue, classes `--avant/--apres`, Plein écran avec état, commandes désactivées lisibles, Section suivante sans vidéo, coupure des mots trop longs, `--grenat-inverse`, chargement annoncé, plein écran de toute la page) ; `securite-reviewer` (contre-revue) 0 bloquant, 4 mineurs corrigés (`no-brotli`, hôte local exigé, fins de chaîne strictes `D`, demande de verrou unique).
- **Troisième série** : `a11y-reviewer` 1 bloquant (pastille d'infos sur la phrase « Pas encore de vidéo »), 3 importants, 4 mineurs ; tous corrigés (infos absentes sans vidéo, badge masqué en répétition, vitesse choisie écrite « ✓ » en plein accent, navigation de la scène qui passe à la ligne, colonnes `auto-fit`, écran de bureau borné, liens du résumé d'erreurs à 44 px, noms de régions uniques).
- **Quatrième série** : `a11y-reviewer` 1 bloquant (la phrase « Pas encore de vidéo » restait coupée avec le texte à 200 % : l'écran gardait une hauteur 16/9 fixe ; le test faisait défiler l'intérieur de l'écran), 1 important, 4 mineurs ; tous corrigés (aplat dans le flux et écran qui grandit, répétition sans vidéo en colonne avec une illustration dédiée et un test `elementFromPoint`, résumé des raccourcis avec son triangle, `inert` sur le lecteur seul, flèches de déplacement en `auto-fit`, case à cocher 1,75rem en `--accent`).
- **Dernière revue** (`a11y-reviewer`, contexte vierge) : « **Findings bloquants : 0** ». axe : 0 violation sur `/gabarits` (sections 1 à 3) et `/chanson/demo/repeter`, 390 et 1 280 px, clair et sombre ; clavier : 88 arrêts sur `/gabarits`, 11 en répétition, focus toujours visible, aucun piège ; aucun défilement horizontal à 320 px (texte 100 et 200 %), 640 × 450 et 320 × 225 ; décompte jamais coupé ; rien sous 14 px. Ses 1 important et 4 mineurs ont été corrigés ensuite : ligne « temps / durée · section » sortie de l'écran (sous la vidéo, elle ne recouvre plus le badge Miroir), texte des voiles borné à `6vw`, commandes désactivées dans l'illustration sans vidéo, lien d'évitement réduit à 1 px hors focus, commentaire, marge basse et coupure du nom de section en répétition. Tests relancés après ces corrections : tous verts (voir ci-dessous).
- **Revue de code** : faite par un sous-agent général en contexte vierge (même cahier des charges que `/code-review`) : 0 bloquant ; ses findings sont corrigés (voir première série). `/security-review` n'est pas demandé en phase 3.

### Vérification finale (13 octobre 2026)

- `docker compose exec -T web composer check` : « Lint : 70 fichier(s) PHP, 0 erreur(s) », « Lint inline : 41 fichier(s), 0 défaut(s) », « Contrastes : 70 paire(s) mesurée(s), 0 échec(s) », « OK (94 tests, 563 assertions) ».
- `pnpm test:js` : 15 tests, 15 réussis.
- `pnpm test:e2e` : 54 réussis (27 en `mobile`, 27 en `bureau`), 2 ignorés (les captures, lancées à part par `pnpm captures phase-03` : 2 réussis).
- Captures à jour dans `docs/captures/phase-03/` (50 images, 390 et 1 280 px : lien manquant, entrée, Ma page, gabarits en clair et en sombre par blocs, lecteur avec décompte, mode répétition avec et sans décompte, administration).
- Poids : polices 87,3 Ko ; CSS des pages 54 Ko non compressés (≈ 11 Ko compressés) ; `lecteur.js` 22,5 Ko ; JS total 29,9 Ko.

Phase 3 terminée le 2026-10-13 (jetons, composants, lecteur)

## Phase 4 — Données, chansons, sections, placements et suivi (13 octobre 2026, mode autonome, tentative 1)

Tag `avant-phase-4` posé avant toute modification. Plan écrit avant le code : `docs/plans/phase-04.md`. Docker répond.

### Fait

1. **Migration** `app/migrations/002-donnees.sql` (aucun `DROP`) : `videos`, `chansons`, `liens`, `distributions`, `sections`, `placements`, `niveaux`, `repetitions`, `presences`, `commentaires`, `checklist` ; ensembles fermés et bornes en `CHECK`, clés uniques, cascades des sections vers niveaux et placements, déclencheurs de dernier recours contre le chevauchement de deux sections, nom de fichier vidéo contraint (32 hexadécimaux + extension).
2. **Import** `scripts/importer-etat.php` → `app/import.php` : faits d'`etat.json` (cousins et contacts, 8 chansons et statuts, danseurs et « en attente », liens partagés avec titre, partageur et date tirés des notes et de `whatsapp/analyse`, 7 répétitions, 8 réponses WhatsApp, 11 questions ouvertes pour `/admin`), contrôle croisé des danseurs avec le tableau de `distribution.md` (contradiction → rien n'est gardé), durées, films et tempos de `recherches/` (140 vérifié ; 131, 142, 97, 145 « à confirmer »), 15 tutoriels de `recherches/` (lignes « tutoriel », durée, « en miroir » pour Mahir Shanz et Kala Samaya), check-list du brief semée une fois. Idempotent sans écraser l'administration. Aucune section, aucun placement, aucune vidéo, aucun ordre de passage. Lancé sur la base locale : « 8 chanson(s), 23 lien(s), 7 répétition(s), 8 réponse(s), 3 ligne(s) de check-list » puis, relancé, « 0 … ».
3. **Ma page** `GET /moi` : Salut, carte « Prochaine répétition » en lecture (prochaine décidée, sinon l'option du sondage la plus votée, encart de repli sans sondage, « Voir les répétitions »), « Tes chorés » (à créer avec « qui s'en occupe », décidée, en attente, progression), « Proposées, pas tranchées », « Ce qui bouge » (niveaux « je maîtrise »), navigation, cas « aucune choré ».
4. **Page d'une chanson** `GET /chanson/{slug}` : en-tête avec crochets (« [film à confirmer] », « [durée inconnue] », « 140 tpm » / « tempo à confirmer (145 tpm ?) » / « tempo à régler »), « Qui s'en occupe ? » (`POST /chanson/{slug}/createur`, danseurs seulement), encart « en attente », lecteur branché sur `/m/{id}`, sections et niveaux (`POST …/section/{id}/niveau`, redirection ou JSON ; formulaires pour les danseurs et les « en attente » seulement), « Ma place » par section (suit la section choisie en JavaScript : évènement `lecteur:section`), « Qui maîtrise quoi », tutoriels et références, « Vidéos des cousins » vide ; deux colonnes au bureau. `GET /chanson/{slug}/repeter` (niveau prénom, vraies sections) ; `GET /api/etat?chanson=` (sections, mes niveaux, version du lien).
5. **Placements** `GET /placements` (première de mes chorés à plusieurs) et `/placements/{slug}` : chips des chansons à plusieurs, scène et flèches, « non placés », « Me surligner », ↑ ↓ ← → (`POST /placements/{section}/moi`, pas de 5, borné, journalisé, refusé si « placements fixés »), toutes les sections côte à côte au bureau, `?scene=1` (points 68 px, Quitter → Ma page).
6. **Répétitions** en lecture (`/repetitions`) et pages « Bientôt » (`/videos`, `/jour-j`).
7. **Vidéo de référence** (`app/televersement.php`) : extension + type annoncé + signature (`ftyp`, EBML), taille ≤ `TAILLE_MAX_UPLOAD_MO`, espace ≤ `ESPACE_MAX_MO`, codec deviné dans les 4 premiers et 4 derniers Mo, HEVC refusé en toutes lettres, nom aléatoire, ancienne référence effacée ; `GET /m/{id}` (cookie d'accès, type de la base, plages 206, `private, max-age=31536000, immutable`) ; 413 en toutes lettres quand l'envoi dépasse `post_max_size`.
8. **Administration** : tableau (navigation, lien, espace, questions ouvertes, entrées, dernières actions), réglages (date et lieu, durée accordée, sondage https, texte d'entrée, contact du pied de page, placements fixés), chansons (liste, ajout), fiche (tempo + « Taper le tempo », danseurs, liens https, vidéo de référence), sections (ajout, modification, monter, descendre, suppression confirmée, « Depuis le lecteur », erreurs en toutes lettres sans ressaisie), placements (x/y, flèches, copie), journal (200 lignes). `public/js/admin.js`, `public/js/chanson.js`, `public/css/pages.css`.
9. **Tests** : PHPUnit (`ImportTest`, `ChansonsTest`, `AdminDonneesTest`, `MediasTest` ajoutés : import deux fois et contenu exact, sources vérifiées, validations des sections, niveaux bornés, `/m/{id}` sans cookie et inconnu, 206, admin 404 sans clé, refus HEVC sur fixture de quelques octets) ; Playwright `tests/e2e/donnees.spec.ts` (parcours Jessica, Nadine, Patrice, sections A et B en administration puis « Je maîtrise », grille et progression, niveaux sans JavaScript, placements vus par un autre cousin, vidéo « Démo » déposée et mode répétition, administration et axe dans les deux thèmes, 320 px à 200 %, rien sous 14 px) sur une **base de test séparée** (`stockage/test.sqlite`, effacée à la fin).
10. **Documentation** : `docs/ADMIN.md`, `docs/A-FAIRE-PAR-PATRICE.md` (n° 22 à 33). Captures 390 et 1 280 px dans `docs/captures/phase-04/`.

### Décisions prises sans validation

1. **Import respectueux de l’administration** : une chanson, un lien, une répétition ou une réponse déjà en base n’est jamais réécrit, et un fait déjà importé puis retiré par Patrice n’est jamais remis (registre `import_cles` des réglages, clés « chanson, danseur + statut, lien, répétition, réponse ») ; seuls les contacts WhatsApp et les questions ouvertes sont remis à jour, et un changement de statut d’un danseur dans les sources (« en attente » → « danse ») est appliqué ; la check-list n’est semée qu’une fois. Le contrôle croisé avec `distribution.md` marche dans les deux sens et arrête tout si le fichier n’est pas lisible. Revenir : `app/import.php` (registre `$nouveau`).
2. **Faits de `recherches/` tenus dans `app/import.php`** (durées, films, tempos, tutoriels) avec leur source, plutôt que relus dans le Markdown (tableaux trop irréguliers pour être lus sans risque) ; `ImportTest` vérifie que chaque URL, durée et mention « miroir » figure dans le document. Le lien « démo » de Gabriel Entertainment (sans durée ni type « tutoriel ») et les vidéos officielles ne sont pas importés. Revenir : constantes `TUTORIELS`, `FAITS_CHANSONS`.
3. **« Inisai » importé « Innisai »** (titre YouTube cité par la note d'`etat.json`, orthographe de la spec) ; film et durée laissés vides. Tempo ≈ 97 importé « à confirmer », comme le demande le prompt, bien qu'il porte sur la version Varalaru, elle-même à confirmer.
4. **Titres des liens partagés** écrits en français à partir des notes d'`etat.json` et du tableau de `whatsapp/analyse` (« Reprise dansée d'Ahinth Vijay ft. The Officials »…), partageur donné par son prénom (correspondances d'`etat.json`).
5. **Réponses importées** : « presents » d'une répétition décidée → `oui` ; d'une option du sondage → `peut_etre` (vote dispo) ; « incertains » → `peut_etre`. Affichées « Avaient dit venir », « Disponibles d'après le sondage ».
6. **Prochaine répétition** : à égalité de votes, la date la plus proche.
7. **Niveaux réservés aux danseurs et aux « en attente »** de la choré (403 sinon) ; un cousin qui visite une autre choré voit les sections et la grille sans formulaire. « Qui s'en occupe » : seuls les danseurs choisissent, parmi les danseurs. Revenir : `ma_place_dans()` dans `app/controleurs/cousins.php`.
8. **Placements pour les chansons à plusieurs danseurs seulement** (un solo n'a ni page de placements ni « Ma place ») ; un danseur non placé ne peut pas bouger son point (409, « Patrice s'en charge ») ; en administration, une flèche sur un non placé le pose au centre puis le déplace.
9. **Démonstration déplacée** de `/chanson/demo/repeter` vers `/gabarits/repeter` (même niveau « gabarits ») : `/chanson/{slug}/repeter` sert désormais les vraies chansons au niveau prénom. Revenir : `app/routes.php`.
10. **Base de test séparée pour Playwright** : `scripts/e2e-outil.php debut|preparer|fin` ; le site sert `stockage/test.sqlite` seulement si `ENV = dev` **et** si le témoin `stockage/e2e.actif` existe (`chemin_base_active()`), base recréée à chaque test (migrations + import), effacée avec ses vidéos au démontage global (`tests/e2e/fin-globale.ts`). Pendant les tests, le site local ne montre donc pas la base de Patrice. Revenir : `app/bd.php`, `playwright.config.ts`.
11. **Vidéo de référence en WebM acceptée** (essais avec la vidéo « Démo ») en plus du MP4 ; MOV refusé ; codec « non reconnu » accepté avec un avertissement à Patrice. Revenir : `admin_reference()`.
12. **Contact du pied de page** dans les réglages (`contact_patrice`), « [contact] » tant qu'il est vide (A-FAIRE n° 9) ; **texte d'accueil** de l'entrée remplaçable dans les réglages.
13. **Danseurs par menu** (« ne danse pas / danse / en attente ») au lieu de cases à cocher : trois états par cousin. **Ajout d'une chanson** par son titre (statut « proposée ») ; pas de suppression de chanson (statut « écartée » à la place).
14. **`overflow-wrap: break-word` sur `body`** : un mot long ne fait plus déborder la page à 320 px avec le texte à 200 %.
15. **`reglage()` renvoie `null`** pour une valeur vide en base (avant : chaîne vide).
16. **Placements d'un cousin retiré** : enregistrer la fiche d'une chanson efface les points des cousins qui n'y dansent plus et remet « qui s'en occupe » à vide s'il ne danse plus ; l'affichage et « Copier la section précédente » ne prennent que les danseurs actuels ; la copie depuis une section vide est refusée en toutes lettres (elle effacerait sans rien apporter).
17. **Formulaire des placements en administration** : un « Enregistrer les placements » en tête du formulaire (premier bouton : la touche Entrée enregistre, elle ne déplace personne), en plus de celui du bas.
18. **Refus des écritures des cousins** (300 par heure) : message « Trop de changements d'un coup. Réessaie un peu plus tard. », `Retry-After` jusqu'à l'heure suivante, refus journalisé.
19. **Titres anglais des chaînes YouTube** (« Dance Marine », « Learn How To Dance ») gardés tels quels, sans `lang="en"` : ce sont des noms propres ; A-FAIRE n° 34.
20. **Carte « Prochaine répétition »** sans lien de sondage mais avec une option WhatsApp en tête : « Le sondage est dans le groupe WhatsApp ; sinon, dis tes dispos à Patrice. » (Jessica et Stéphane ne sont pas dans le groupe) ; même phrase sur `/repetitions`.

### Non fait, non vérifié

- Boutons « Je viens / Je ne peux pas », vidéos des cousins, Jour J, écran d'administration des répétitions et de la check-list : phase 5 (pages « Bientôt » d'ici là).
- Téléphones réels, lecteurs d'écran réels (annonce du statut des niveaux, « Ma place » qui change de section) : A-FAIRE n° 17 et 18.
- Vraie vidéo de référence MP4 (H.264 ou HEVC d'iPhone) : seules des fixtures de quelques octets et la vidéo « Démo » en WebM ont servi ; la devinette du codec n'a pas été essayée sur de vrais fichiers.
- Dépassement réel de `post_max_size` à travers Apache (page 413) : vérifié en PHPUnit seulement.
- Zoom navigateur à 400 % : seul le test 320 px avec le texte à 200 % a été lancé.
- Firefox et Safari : seuls Chromium (Playwright) a servi.

### Revues

- **Première série** (contexte vierge) : `securite-reviewer` 0 bloquant, 6 mineurs (point déplaçable par un cousin retiré, texte anglais si la réponse n'est pas du JSON, 429 JSON incomplet, contact visible sans le lien, quota au remplacement, transaction sans retour arrière) ; `a11y-reviewer` 0 bloquant, 2 importants (Entrée qui déplace le premier danseur, erreur des niveaux invisible ou en anglais), 11 mineurs ; revue de code (équivalent `/code-review`, sous-agent général) 0 bloquant, 3 importants (réimport qui remet ce que Patrice a retiré, Entrée dans les placements, points des cousins retirés), 8 mineurs. Tous corrigés (voir décisions 1, 16 à 20), sauf `lang="en"` sur les titres (décision 19).
- **Contre-revue** (contexte vierge, dernière revue) : « **Findings bloquants : 0** ». Les six groupes de corrections sont jugés justes. Son important (zone `role="status"` des niveaux retirée de l'arbre quand elle est vide) et ses 7 mineurs ont été corrigés ensuite : zone toujours rendue et annonce répétable, copie refusée depuis une section vide ou avant la première, durée des liens bornée à 10 heures et minutes sous 60 avec des heures, statut dans la clé d'import et mise à jour du statut, contrôle croisé dans les deux sens, `Retry-After` jusqu'à l'heure suivante, phrase WhatsApp selon la source, paragraphe vide de la mini-scène, « Ce qui bouge » limité aux danseurs actuels. Tests relancés après ces corrections : tous verts (ci-dessous).
- `/security-review` n'est pas demandé en phase 4.

### Vérification finale (13 octobre 2026)

- `docker compose exec -T web composer check` : « Lint : 92 fichier(s) PHP, 0 erreur(s) », « Lint inline : 55 fichier(s), 0 défaut(s) », « Contrastes : 70 paire(s) mesurée(s), 0 échec(s) », « OK (138 tests, 936 assertions) ».
- `pnpm test:js` : 15 tests, 15 réussis.
- `pnpm test:e2e` : 74 réussis (37 en `mobile`, 37 en `bureau`), 2 ignorés (les captures, lancées à part par `pnpm captures` : 2 réussis). Base de test effacée et témoin retiré à la fin (`e2e-outil.php fin`).
- Import sur la base locale : relancé après les corrections, « 0 chanson(s), 0 lien(s), 0 répétition(s), 0 réponse(s), 0 ligne(s) de check-list ».
- Captures à jour dans `docs/captures/phase-04/` (46 images, 390 et 1 280 px : Ma page de Jessica, Nadine et Patrice, en clair et en sombre, avec progression ; chanson sans sections, à créer, en attente, Kanimaa avec sections, vidéo, niveaux, grille et « Ma place » en clair et en sombre ; placements et mode scène ; mode répétition avec décompte ; répétitions ; page « Bientôt » ; administration : tableau, chansons, fiche, sections, placements, réglages, journal).

Phase 4 terminée le 2026-10-13 (données, chansons, sections, placements, suivi)

## Phase 5 — Répétitions, vidéos des cousins, Jour J, PWA (13 octobre 2026, mode autonome, tentative 1)

Tag `avant-phase-5` posé avant toute modification. Plan écrit avant le code : `docs/plans/phase-05.md`. Docker répond.

### Fait

1. **Migration** `app/migrations/003-groupe.sql` (aucun `DROP`) : table `constats` (présents et absents constatés par Patrice, distincts des réponses), index de l'album, des commentaires et du fil.
2. **Répétitions** : `GET /repetitions` (à venir : décidées, proposées, annulées ; « Je viens / Je ne peux pas » sur les décidées et proposées à venir ; « Ta réponse : … » ; qui vient, qui ne peut pas, dispos du sondage WhatsApp, sans réponse ; « Personne n'a encore répondu. Sois la première ou le premier. » ; passées : présents en vert, absents, pas noté, ou les réponses si Patrice n'a rien noté ; compte rendu), `POST /repetitions/{id}/presence` (idempotent, journal seulement si la réponse change, 409 en toutes lettres sur une répétition annulée ou passée, remplace un « peut-être » importé, débit « écriture »), carte de Ma page avec les deux boutons (répétition décidée et option du sondage), confirmation en page. Admin `/admin/repetitions` (liste, ajout, date en double refusée) et `/admin/repetitions/{id}` (champs, compte rendu, un menu « pas noté / présent / absent » par cousin avec sa réponse rappelée).
3. **Vidéos des cousins** : `GET /videos` (album par chanson, état vide), `GET /videos/ajouter` (chanson, titre, note, « Filmée en miroir », fichier `accept`, `MAX_FILE_SIZE`, limites, conseil « une section ou deux », rappel iPhone repliable), `POST /videos` (extension + MIME + signature, `UPLOAD_ERR_INI_SIZE` / `FORM_SIZE` / `PARTIAL` en toutes lettres, quota, HEVC accepté avec avertissement à l'auteur et « format iPhone » pour tous, débit réservé seulement pour un envoi valide et accepté par toutes les limites, nom aléatoire, journal ; JSON pour le script), `GET /videos/{id}` (lecteur simple : contrôles natifs, `#t=` sans JavaScript ; vitesses et miroir avec JavaScript ; miroir d'office si filmée en miroir ; commentaires triés par instant puis par date ; « à 0:03 » positionne la vidéo ; « Prendre la position » ; suppression de la vidéo et des commentaires par l'auteur ou par Patrice — cookie d'administration — avec confirmation en page), `POST /videos/{id}/commentaires` (30/h), `…/commentaires/{c}/supprimer`, `…/supprimer` (fichier effacé), `…/duree` (auteur, une fois). Progression par `XMLHttpRequest` (`<progress>`, « 42 % · 31 Mo sur 74 Mo », Annuler, taille contrôlée avant l'envoi, 413, réseau coupé). Admin `/admin/videos` (taille, format, espace, Supprimer, « Remplacer par une version convertie » : même ligne, nouveau nom, HEVC refusé). Page d'une chanson et « Ce qui bouge » alimentés (vidéos, commentaires, présences, niveaux). Adresse des fichiers `/m/{id}?v={8 caractères du nom interne}`.
4. **Jour J** : `GET /jour-j` (« Mariage le [date] · [lieu]. » depuis les réglages, mode scène sur la première chanson à plusieurs de l'ordre avec retour au Jour J, frise ou « L'ordre de passage n'est pas encore fixé », durée coupée ou « (chanson entière) », « [à compléter] »), `POST /jour-j/check/{id}` (état voulu, pas une bascule), admin `/admin/jour-j` (ajouter, renommer, monter, descendre, supprimer avec confirmation).
5. **PWA** : `public/manifest.webmanifest` (adresses relatives : juste quel que soit `BASE_PATH`), icônes 192 / 512 (maskable) / 180 par `scripts/icones.php` (GD + FreeType, woff2 Yeseva One locale, couleurs lues dans `jetons.css`) ; `GET /sw.js` servi par PHP depuis `app/sw.js` (version = empreinte de l'interface et des vues, précache, pages réseau d'abord puis copie puis `/hors-ligne`, 60 pages au plus, statiques cache d'abord, `POST` jamais touchés, `/admin`, `/e`, `/api`, `/m`, `/gabarits` jamais gardés comme pages, pages et vidéos effacées sur `/lien`, pages effacées sur l'entrée, vidéo gardée servie en 200/206/416 après une vérification HEAD) ; `public/js/pwa.js` (« Garder sur mon téléphone » / « Retirer », `storage.estimate()` avant, `ReadableStream` avec progression, `persist()`, page gardée avec, anciennes copies effacées après un remplacement, conseils iPhone et Android) ; `GET /hors-ligne` (publique, liste des vidéos gardées) ; liste « Gardées sur ton téléphone » sur Ma page ; file des niveaux hors ligne (`public/js/file-niveaux.js`, « 2 niveaux à envoyer ») ; encart « Nouvelle version : recharge ».
6. **Tests** : PHPUnit (`GroupeTest`, `VideosTest`, `PwaTest` ajoutés : présence idempotente et refusée, confirmation non forgeable, constats, Jour J vide puis ordonné, check-list par état voulu, administration, signatures, `.txt` renommé, HEVC, quota, débit qui ne consomme rien sur refus, commentaires bornés et triés, droits de suppression, fichier effacé, durée, remplacement, service worker, manifeste, icônes, élision) ; Playwright `tests/e2e/groupe.spec.ts` (Je viens puis Je ne peux pas, compte rendu ; WebM fabriqué par canvas + MediaRecorder, envoi ralenti avec progression, refus d'un texte, commentaire « à 0:03 » qui positionne la vidéo, suppression ; Jour J sans puis avec ordre, check-list, mode scène et retour ; service worker : `/moi` hors ligne, `/hors-ligne` pour une page inconnue, vidéo gardée lue hors ligne ; axe dans les deux thèmes sur tous les écrans de la phase) ; balayage 320 px / texte 200 % / rien sous 14 px étendu aux nouvelles pages.
7. **Documentation** : `docs/VIDEOS.md`, `docs/ADMIN.md` § 9 à 11, `docs/A-FAIRE-PAR-PATRICE.md` n° 36 à 42. Captures 390 et 1 280 px dans `docs/captures/phase-05/`.

### Décisions prises sans validation

1. **Table `constats`** au lieu de `presences.present_constate` : un cousin présent sans avoir répondu exigerait une réponse inventée. Revenir : migration qui recopie `constats` dans `presences` et `bilan_repetition()`.
2. **Supprimer au nom de Patrice = cookie d'administration**, jamais le simple prénom « Patrice » (n'importe qui peut le choisir). Revenir : `appareil_de_patrice()` dans `app/controleurs/groupe.php`.
3. **Débit des vidéos réservé** (`debit_reserver`) : un fichier refusé, ou un envoi refusé par l'une des limites, ne consomme rien (sinon réessayer pendant l'heure bloquée viderait le quota du jour). Revenir : `debit_autorise()` dans `ajouter_video()`.
4. **HEVC et codec non reconnu acceptés pour les cousins** (refusés pour les vidéos de référence et les remplacements) ; un WebM n'est jamais marqué « format iPhone ».
5. **Adresse des fichiers avec `?v=`** : une vidéo remplacée garde sa page et ses commentaires, et aucun cache ne sert l'ancienne. Revenir : `adresse_media()`.
6. **Service worker servi par PHP** (`GET /sw.js`, modèle `app/sw.js`) au lieu d'un `public/sw.js` statique : version et liste précachée suivent les fichiers, `BASE_PATH` injecté. Le manifeste reste statique avec des adresses relatives.
7. **« Garder » télécharge depuis la page** (`pwa.js`, `fetch` + `tee()` vers `cache.put`) et non depuis le service worker : progression plus simple ; le service worker sert ensuite la copie.
8. **Vidéo gardée revérifiée** par une requête HEAD (une fois par vie du service worker, 3 s au plus ; sans réseau, la copie fait foi) : une vidéo supprimée ou un lien retiré efface la copie au prochain passage en ligne.
9. **Pages gardées** : clé sans les paramètres d'un instant (`ok`, `r`, `t`, `supprimer`…), 60 pages au plus, repli hors ligne sur la même page avec une autre section ; effacées sur l'entrée (prénom changé) et sur `/lien` (avec les vidéos ; côté page, `cm-gardees` et la file des niveaux).
10. **File des niveaux sans jeton rangé** : le renvoi prend le jeton CSRF de la page ouverte ; seul un JSON sans redirection compte comme envoyé ; vidée sur « Changer ».
11. **Check-list par état voulu** (`fait` = 1 ou 0) plutôt qu'une bascule. Pas de suppression de répétition (statut « annulée »).
12. **Mode scène du Jour J** : première chanson à plusieurs danseurs de l'ordre (un solo n'a pas de placements), sinon la première à plusieurs ; « Quitter » revient au Jour J (`depuis=jour-j`, gardé après un déplacement).
13. **Garde-fou CSS sans compression relevé de 60 à 72 Ko** (`OutilsTest::testBudgets`) ; le budget de la spec (40 Ko compressés) reste contrôlé. Revenir : découper `pages.css` par écran.
14. **Confirmation de présence** affichée seulement si la réponse enregistrée correspond (`?ok=` forgé sans effet).
15. **Encart « Nouvelle version »** en haut de page (zone `role="status"` vide toujours rendue, remplie par `site.js`), plus collant en bas (il pouvait cacher le focus).
16. **Résumé d'erreurs renvoyé par le serveur focalisé** au chargement (`data-resume-serveur`, toutes les pages à formulaire).
17. **Élision** « d’Alex » / « de Fabien » (`Chores\de()`) dans les textes et le journal de la phase.
18. **`/security-review`** remplacé, comme en phase 2, par une revue équivalente en contexte vierge (le skill exige `origin/HEAD`, dépôt distant interdit).
19. **Pages « Bientôt » supprimées** (`app/vues/bientot.php`) ; Chromium de test lancé avec `--lang=fr-FR`.

### Non fait, non vérifié

- Téléphones réels : envoi en 4G, vraie vidéo HEVC d'iPhone, installation sur l'écran d'accueil, « Garder » puis mode avion, stockage séparé de l'icône iPhone (A-FAIRE n° 41 et 42). Seul Chromium (Playwright) a servi.
- Le libellé natif du choix de fichier reste « Choose File » sur les captures malgré `--lang=fr-FR` (Chromium sans interface) : c'est le navigateur qui l'écrit, pas le site.
- Mémoire de `tee()` / `blob()` sur une vidéo de 200 Mo dans Safari ; quotas Safari réels.
- Lecteurs d'écran réels (annonces des zones `status` : progression, « Garder », « Nouvelle version »).
- Contrôle d'espace non atomique : deux envois simultanés peuvent dépasser `ESPACE_MAX_MO` d'une vidéo (neuf cousins : accepté).
- Version de SQLite chez Hostinger (le code évite `RETURNING` ; l'UPSERT demande 3.24, déjà utilisé depuis la phase 4) : à relever en phase 6.
- Une vidéo regardée reste dans le cache HTTP du navigateur (`/m/{id}` en `private, max-age=31536000, immutable`, comme le veut la spec § 2) après sa suppression, tant que la page qui la montre reste ouverte ; « Garder » et les vérifications du service worker passent, eux, en `no-store`.
- L'apparition réelle de l'encart « Nouvelle version » (aucun test ne simule un changement de service worker).
- Lighthouse : phase 6.

### Revues

- **Première série** (contexte vierge) : revue équivalente à `/security-review` « Findings bloquants : 0 » (aucune vulnérabilité au-dessus du seuil ; deux remarques corrigées : lien « Recharger » construit sur l'adresse, file des niveaux d'un autre prénom) ; `securite-reviewer` 0 bloquant, 1 important (vidéo supprimée restant lisible sur les téléphones qui l'avaient gardée), 6 mineurs ; revue de code (équivalent `/code-review`) 0 bloquant, 5 importants (file des niveaux : redirection comptée comme envoi, jeton figé, écrasement pendant le renvoi ; statiques hors précache jamais renouvelés ; conseil iPhone trompeur), 14 mineurs ; `a11y-reviewer` **1 bloquant** (classe `.admin__video` partagée : texte illisible sur `/admin/videos`), 8 importants, 12 mineurs. Tout corrigé (décisions 3, 8 à 10, 14 à 17), sauf la course sur l'espace disque (noté ci-dessus).
- **Contre-revues** : `a11y-reviewer` « Findings bloquants : 0 » (1 important : « Recharger » ne rechargeait pas une adresse avec ancre → bouton `location.reload()` ; 4 mineurs corrigés : confirmation du commentaire dans « Commentaires », message de date, nom de la ligne dans les erreurs de la check-list, « Non, la garder » qui revient à sa place ; « Choose File » noté) ; `securite-reviewer` « Findings bloquants : 0 » (1 important : le HEAD de vérification pouvait être servi par le cache HTTP → `cache: 'no-store'` ; 7 mineurs corrigés : promesse mémorisée, `?v=` périmé en 404, purge de `/lien` seulement sur un accès périmé constaté par le serveur, jeton périmé gardé en file, suppression et remplacement croisés, refus JSON journalisés, `/sw.js` léger).
- **Dernière revue** (`securite-reviewer`, contexte vierge) : « **Findings bloquants : 0** ». Son important (`UPDATE … RETURNING` exige SQLite 3.35) et ses 5 mineurs ont été corrigés ensuite : suppression par `SELECT` puis `UPDATE` dans une transaction d'écriture, `no-store` pour « Garder » et après une vérification négative, plus d'effacement de vidéo sur une navigation venue d'ailleurs, un 404 n'efface que la copie demandée, refus journalisés par prénom, file des niveaux vidée sur l'écran d'entrée. Tests relancés après ces corrections : tous verts (ci-dessous).

### Vérification finale (13 octobre 2026)

- `docker compose exec -T web composer check` : « Lint : 109 fichier(s) PHP, 0 erreur(s) », « Lint inline : 68 fichier(s), 0 défaut(s) », « Contrastes : 70 paire(s) mesurée(s), 0 échec(s) », « OK (168 tests, 1181 assertions) ».
- `pnpm test:js` : 15 tests, 15 réussis.
- `pnpm test:e2e` : 82 réussis (41 en `mobile`, 41 en `bureau`), 2 ignorés (les captures, lancées à part par `pnpm captures phase-05` : 2 réussis). Interception réseau active sur chaque test : aucune requête hors de 127.0.0.1:8095. Base de test effacée à la fin (`e2e-outil.php fin`).
- Captures à jour dans `docs/captures/phase-05/` (44 images, 390 et 1 280 px : Ma page avec la présence, « Je viens » et « Ce qui bouge », hors ligne ; répétitions en clair et en sombre ; vidéos vide, album, ajout, ajout refusé, une vidéo en clair et en sombre ; page d'une chanson avec vidéos et « Garder » ; Jour J sans ordre, avec ordre, en sombre ; page hors ligne ; administration : tableau, répétitions, une répétition, Jour J, vidéos).
- Poids : CSS des pages 64 Ko sans compression, 14 Ko compressés (budget de la spec : 40 Ko) ; icônes 3,7 à 11 Ko.

Phase 5 terminée le 2026-10-13 (répétitions, vidéos, Jour J, PWA)

## Phase 6 — Recette, paquet et procédure de mise en ligne (13 octobre 2026, mode autonome, tentative 1)

Tag `avant-phase-6` posé avant toute modification. Plan écrit avant le code : `docs/plans/phase-06.md`. Docker
répond. Restriction de la tentative respectée : recette et paquet en local seulement ; aucun accès à Hostinger, à un
serveur, à un DNS ni à un compte ; aucun déploiement ; aucun téléphone réel.

### Fait

1. **Accessibilité** (`tests/e2e/recette.spec.ts`, résultats dans `docs/RECETTE.md` § 1 et `docs/recette/`) : axe sur
   les 28 pages (cousin et administration) en clair et en sombre, en 390 et 1 280 px, sur le mode répétition (avec et
   sans vidéo), `/lien`, `/installer` : 0 violation ; structure (titre, un `h1`, `main`, évitement, `nav` nommées,
   `caption`, aide au même endroit) ; focus jamais masqué (10 pages, 60 tabulations) ; cibles (aucune sous 44 px hors
   liens dans une phrase) ; contrastes sur le rendu (badge « Miroir activé », surimpressions avec voile composé, points
   de scène : 6,10:1 au moins) ; zoom 400 % (320 × 225) sur 30 pages ; liste manuelle WCAG 2.2 consignée ; VoiceOver et
   TalkBack listés pour Patrice (A-FAIRE partie C).
2. **Performance** : `scripts/lighthouse.mjs` (`pnpm lighthouse`, Lighthouse 13.5.0, mobile, 3 passes, médiane) sur
   les sept pages : performance 0,97 à 1, accessibilité 1, bonnes pratiques 1 ; budgets de la spec § 13 mesurés sur
   toutes les pages (`docs/recette/poids.json`).
3. **Sécurité et vie privée** : en-têtes sur pages, JSON, 404, `/m/{id}`, statiques et page de maintenance ; CSP sans
   violation en console ; `/m/` refusé sans cookie ; fichiers sensibles en 403/404 ; `/admin` et `/installer` en 404 ;
   CSRF ; débit ; renouvellement du lien ; aucune requête hors origine ; journal et débit inspectés sans IP ni
   navigateur (`e2e-outil.php inspecter`, `MaintenanceTest`) ; cookies `HttpOnly` `Secure` `SameSite=Lax` sur
   `/chores` en production (essai du paquet en HTTPS).
4. **Contenu** : texte rendu de toutes les pages relu par la recette (aucun mot anglais hors noms propres, aucun
   emoji) ; inventaire des crochets (`docs/recette/crochets.json`, RECETTE § 4) ; 23 liens YouTube testés en `HEAD`
   (303 puis 200 ; l'existence des vidéos elles-mêmes n'est pas vérifiable ainsi).
5. **Code de production ajouté** : `app/version.php` (1.0.0) ; `app/maintenance.php` (sauvegarde `VACUUM INTO` avec
   repli, contrôle, restauration sous témoin de maintenance qui garde le lien en service, élagage, migrations en
   attente, faits du groupe dans `docs/` ou `donnees/`) ; installation sans SSH `GET|POST /installer` (niveau
   `installation` : base neuve seulement, 404 sinon, avant le CSRF) ; page `/admin/base` (version, « Mettre la base à
   jour », « Faire une sauvegarde maintenant », « Restaurer » avec confirmation en page) ; 503 « Petite pause » pendant
   une restauration ; `migrer()` sous verrou d'écriture ; connexion fermée avant l'envoi d'un fichier ;
   `scripts/sauvegarde.php`, `scripts/restaurer.php` ; boutons « Afficher la clé » et « Copier » (`site.js`).
6. **Défaut trouvé par le paquet et corrigé** : `/gabarits` lisait `jetons.css` à côté de `app/` (500 en production où
   `public/` est ailleurs) → `dossier_public()`.
7. **Paquet** : `scripts/paquet.php` → `dist/chores-mariage-v1.0.0.zip` (index git seulement, compressé, 132
   fichiers, contrôlé et relu) ; `pnpm essai:paquet` (`scripts/essai-paquet.mjs`, `tests/paquet/`,
   `playwright.paquet.config.ts`) : conteneur jetable `chores-mariage-paquet` en HTTPS sur 8096, arborescence de
   Hostinger imitée, installation sans SSH puis scripts comme en SSH, parcours, axe, sécurité, témoin de maintenance,
   sauvegarde puis restauration (script et administration) : vert, conteneur supprimé.
8. **Procédure** `docs/deploy/` : `README.md` (pas à pas hPanel, repli sous `public_html`, HTTPS et portée de HSTS,
   SSH ou `/installer`, dépannage), `verification.md` (`curl.exe`), `sauvegardes.md`, `mise-a-jour.md`, `lien.md`.
9. **Documentation** : `docs/RECETTE.md`, `docs/A-FAIRE-PAR-PATRICE.md` réordonné (A mise en ligne, B téléphones,
   C lecteurs d'écran, D lien et premières sections, E ce qui manque ; anciennes notes en annexe), `README.md`,
   `docs/ADMIN.md` § 12, `docs/TOOLING.md`, spec § 10 ; version 1.0.0 (`app/version.php`, `package.json`, README) ;
   captures 390 et 1 280 px dans `docs/captures/phase-06/`.

### Dépendance ajoutée

- `lighthouse@13.5.0` (pnpm, développement seulement, Apache-2.0, Google Chrome team) : mesure Lighthouse demandée
  par la phase ; même version que le projet voisin `hospitalite`, déjà dans le magasin pnpm, publiée depuis plus de
  7 jours (`minimumReleaseAge`). Installée hors ligne. Aucune dépendance côté client, aucune dépendance de production.

### Décisions prises sans validation

1. **Installation sans SSH par `/installer`** (spec : « route protégée `/admin/installer?cle=…` [à vérifier] ») : la
   clé se tape dans un champ (jamais dans l'adresse, qui irait aux journaux de l'hébergeur), sur une base sans lien
   seulement ; 404 ensuite pour toute méthode. Revenir : retirer les deux routes `installation` de `app/routes.php`.
2. **Page `/admin/base`** (sauvegarde, mise à jour, restauration en un clic) en plus des scripts : sans SSH, Patrice
   n'a sinon que des manipulations de fichiers risquées. Elle ne lit que la table des migrations et les fichiers, pour
   rester utilisable si une version attend ses migrations. Revenir : routes `/admin/base`, `/admin/migrer`,
   `/admin/sauvegarde`, `/admin/restaurer`.
3. **La restauration garde le lien en service** (écrit dans la copie avant la mise en place) : personne n'est
   déconnecté et un lien révoqué ne revient pas ; si la base remplacée n'a pas de lien lisible (vide, abîmée), c'est
   celui de la sauvegarde, et le script comme la page le disent ; si la base est saine mais son lien illisible, la
   restauration est refusée. Revenir : bloc `$lien` de `restaurer_base()`.
4. **Témoin de maintenance** `stockage/maintenance.actif` : le site répond 503 sans ouvrir la base pendant une
   restauration ; une seule à la fois ; un témoin automatique est oublié au bout de 10 minutes ; un témoin vide posé à
   la main (procédure de dernier recours) ne l'est jamais.
5. **Élagage** : 12 sauvegardes gardées quand Patrice en fait une depuis l'administration, 5 copies
   « avant-restauration » ; le script n'efface rien sans `--garder=N` ; la tâche planifiée conseillée garde 12.
6. **Paquet** : construit depuis l'index git (ce qui a été « git add »), jamais depuis l'arborescence de travail ;
   liens symboliques refusés ; scripts de production seulement (`migrer`, `importer-etat`, `nouveau-lien`,
   `sauvegarde`, `restaurer`) ; faits du groupe copiés dans `donnees/` (hors racine web) pour l'import ; `VERSION` ;
   `.htaccess` racine qui refuse tout (repli sous `public_html`). Exclus : `e2e-outil.php`, `config-dev.php`, lint,
   contrastes, captures, polices, icônes, vidéo de démo, essai, Lighthouse.
7. **Budget JS mesuré compressé** (40 Ko transférés), comme le CSS depuis la phase 3 (décision 4) : 17,4 Ko
   compressés au plus (`/chanson/kanimaa`, 50,3 Ko bruts). `/gabarits` (page de travail de Patrice, 102 Ko de HTML)
   est hors du budget HTML. Revenir : `recette.spec.ts` (et découper les modules de la page d'une chanson).
8. **Essai du paquet en HTTPS** (certificat auto-signé, port 8096, image locale du projet, configuration générée par
   `scripts/config-dev.php` avec ses nouvelles options `--dossier`, `--base`, `--url`, `--env`, jamais affichée) :
   seul moyen de vérifier les cookies `Secure` et HSTS en `ENV = production`.
9. **`/security-review`** remplacé, comme aux phases 2 et 5, par une revue équivalente en contexte vierge (le skill
   exige `origin/HEAD` ; un dépôt distant est interdit).
10. **Liens YouTube testés en `HEAD`** depuis le poste (réseau disponible ; aucune donnée des cousins envoyée ; ce
    n'est pas un appel du site).
11. **Contrôle des emoji** : toute la famille pictographique est refusée sauf les flèches de texte (↗ des liens
    sortants) ; la coche ✓ n'en fait pas partie.
12. **A-FAIRE réorganisé** dans l'ordre d'exécution ; les notes n° 1 à 42 des phases 2 à 5 sont gardées en annexe
    (PROGRESS y renvoie).
13. **Champ de la clé d'installation en `type="password"`** avec « Afficher la clé » (JavaScript) ; la clé n'est jamais
    réaffichée après un refus (exception de sécurité de WCAG 3.3.7).
14. **Groupe WhatsApp** : poster le lien dans le groupe le donne à tous ses membres ; `docs/deploy/lien.md` et
    A-FAIRE (D1) laissent à Patrice le choix d'envoyer le lien dans le groupe ou en messages privés.
15. **Version 1.0.0** et tag git local `v1.0.0` (jamais poussé).

### Non fait, non vérifié

- Tout ce qui dépend de Hostinger (PHP et extensions, LiteSpeed et `.htaccess`, `Require all denied`, compression,
  écriture hors `public_html`, SSH, tâches planifiées, version de SQLite, IP derrière un relais, journaux d'accès) :
  `docs/deploy/verification.md` et A-FAIRE partie A.
- Vrais téléphones, VoiceOver, TalkBack, vraie vidéo HEVC d'iPhone : A-FAIRE parties B et C.
- Firefox et Safari de bureau (seul Chromium a servi) ; Lighthouse sur le vrai réseau.
- Existence des vidéos YouTube derrière les liens (YouTube répond 200 même pour une vidéo retirée).
- Une vidéo envoyée après une sauvegarde puis « effacée » par une restauration reste sur le disque sans être listée
  (documenté dans `sauvegardes.md` ; pas d'écran pour la retrouver).
- Le cas « une migration échoue juste après une restauration » (`RestaurationPartielle`) n'a pas de test : il
  faudrait une migration volontairement fausse.
- Les paramètres `?ok=` des confirmations restent dans l'adresse (un rechargement réaffiche le message) : schéma
  existant depuis la phase 4, gardé.
- Mineurs d'accessibilité laissés : double deux-points « Erreur : Clé d’administration : … » au champ ; 44 px et 400 %
  non mesurés sur la page de résultat de `/admin/lien`.
- Deux restaurations lancées en même temps juste après un arrêt brutal de PHP (témoin périmé repris) pourraient se
  croiser ; un envoi de vidéo en cours pendant une restauration perd sa ligne (le fichier reste) : cas jugés
  improbables pour neuf cousins et un seul administrateur, laissés.

### Revues

- **Première série** (contexte vierge, en parallèle) : revue équivalente à `/security-review` (fichiers de la phase et
  surface complète du site) « Findings bloquants : 0 », aucune vulnérabilité retenue ; `securite-reviewer` 0 bloquant,
  1 important (restauration à la main risquée pendant que le site tourne), 8 mineurs ; `a11y-reviewer` 0 bloquant,
  2 importants (messages de maintenance hors écran ; erreur d'import attribuée au champ de la clé, texte anglais
  possible), 14 mineurs ; revue de code (équivalent `/code-review`) 0 bloquant, 4 importants (`restaurer.php` bloqué
  sur une base vide ou abîmée ; restauration qui pouvait enfermer Patrice dehors ; captures impossibles sur une zone
  en lecture seule ; essai du paquet dépendant des dates réelles), 8 mineurs. Corrigés : page `/admin/base` avec
  restauration en un clic, témoin de maintenance, lien gardé, copie brute de repli, niveau `installation` (404 avant
  le CSRF), `migrer()` sous verrou, élagage, paquet depuis l'index git et compressé, `.htaccess` sans `IfModule`,
  docs (zip sous `public_html`, HSTS, `cm_admin`, dépannage, repli HTTPS), messages et champ de l'installation,
  « Afficher la clé », « Copier le lien », tests (répétition de 2099, booléens au lieu de liens dans les rapports,
  `toBeInViewport`, 44 px en assertion, fond semi-transparent composé, emoji élargi).
- **Contre-revues** : `a11y-reviewer` 0 bloquant, 7 mineurs (corrigés : « non modifiable » dans les étiquettes,
  confirmation focalisée avec un titre, une zone d'état par bouton, repli de copie sans fausse promesse, boutons
  cachés avec leur paragraphe, espace insécable ; laissés : double deux-points au champ, mesures sur le résultat de
  `/admin/lien`) ; `securite-reviewer` 0 bloquant, 1 important (échec après la permutation : ancien lien et message
  faux), 7 mineurs ; revue de code 0 bloquant, 2 importants (le même ; élagage qui pouvait effacer la sauvegarde à
  restaurer), 10 mineurs. Corrigés : lien écrit dans la copie avant la mise en place, `RestaurationPartielle`, base et
  `-wal`/`-shm` mis de côté puis remis en cas d'échec, témoin exclusif à jeton, oublié au bout de 10 minutes,
  élagage qui épargne la source, `BEGIN IMMEDIATE` dans le `try`, liens symboliques refusés, `safe.directory` limité,
  docs (procédure à la main, HTTPS après mise à jour, ordre du repli).
- **Troisième revue** (`securite-reviewer`) : 0 bloquant, 2 importants (une connexion longue, une vidéo, pouvait
  effacer le `-wal` d'une base restaurée ; `rename` non muets transformés en exceptions avant la remise en place),
  5 mineurs. Corrigés : connexion fermée avant l'envoi d'un fichier, `rename` muets, tout l'après-permutation en
  `RestaurationPartielle`, refus si le lien d'une base saine ne se lit pas, message distinct quand le lien de la
  sauvegarde est remis, sauvegarde ratée effacée, confirmation en `role="region"`.
- **Dernière revue** (`securite-reviewer`, contexte vierge) : « **Findings bloquants : 0** ». Son important (deux
  sauvegardes dans la même seconde : l'échec de l'une pouvait effacer l'autre, régression de la série précédente) et
  4 de ses 7 mineurs ont été corrigés ensuite : nom de sauvegarde réservé (`fopen 'x'`), journal de la restauration
  protégé, scripts qui écrivent refusés pendant une maintenance, base occupée (BUSY) refusée au lieu de remettre un
  vieux lien, restes d'une restauration interrompue effacés. Laissés (notés plus haut) : reprise non atomique d'un
  témoin périmé, envoi de vidéo pendant une restauration, identifiants des cousins (stables : l'import ne les change
  jamais). Tests relancés après ces corrections : tous verts (ci-dessous).

### Vérification finale (13 octobre 2026)

- `docker compose exec -T web composer check` : « Lint : 120 fichier(s) PHP, 0 erreur(s) », « Lint inline : 70 fichier(s), 0 défaut(s) », « Contrastes : 70 paire(s) mesurée(s), 0 échec(s) », « OK (180 tests, 1283 assertions) ».
- `pnpm test:js` : 15 tests, 15 réussis.
- `pnpm test:e2e` : 94 réussis (47 en `mobile`, 47 en `bureau`, dont la recette de la phase 6), 4 ignorés (les captures des phases 5 et 6, lancées à part par `pnpm captures` : 2 réussis). Interception réseau : aucune requête hors de 127.0.0.1:8095. Base de test effacée à la fin.
- `pnpm essai:paquet` : 1 scénario réussi (installation sans SSH, parcours, axe, sécurité, scripts, maintenance, sauvegarde et restauration par script et par l'administration) ; conteneur `chores-mariage-paquet` supprimé.
- `pnpm lighthouse` : seuils atteints sur les sept pages (`docs/recette/lighthouse.json`).
- Paquet : `docker compose exec -T web php scripts/paquet.php` → `dist/chores-mariage-v1.0.0.zip`, 132 fichiers (ignoré par git : à reconstruire au moment de la mise en ligne).
- Captures dans `docs/captures/phase-06/` (18 images, 390 et 1 280 px : installation, refus, en sombre, site installé avec le lien masqué ; tableau d'administration en clair et en sombre ; « Base et sauvegardes » avec sauvegarde, confirmation de restauration, en sombre).

Phase 6 terminée le 2026-10-13 (recette locale et paquet)
