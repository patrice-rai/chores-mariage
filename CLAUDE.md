# Chorés du mariage — règles du projet

Contrat de mission repris de `docs/prompts/phase-00.md` (qui prime en cas de doute). Lis ensuite `docs/PROGRESS.md`, puis `docs/spec/01-spec.md`.

## Le produit
Site privé, en français, tutoiement, ton joyeux, pour neuf cousins (Alex, Catherine, Fabien, Flobert, Jessica, Josiane, Nadine, Patrice, Stéphane) qui préparent cinq chorégraphies de films tamouls pour un mariage. Patrice est le propriétaire et le seul administrateur. Un seul lien secret, aucun compte : chacun touche son prénom. Lecteur au ralenti (0,5 × / 0,75 × / 1 ×), miroir, boucle sur une section de 8 temps, décompte 5-6-7-8 au tempo ; niveaux « pas encore / presque / je maîtrise » ; placements ; répétitions avec « je viens » ; vidéos des cousins commentées à l'instant près ; Jour J. Mobile d'abord (390 px), bureau 1 280 px, PWA.
Ne fait pas : compte ou mot de passe par personne, e-mail, SMS, notification, conversion vidéo sur le serveur, lecture YouTube dans la page, CAPTCHA, glisser-déposer obligatoire, mesure d'audience.

## Ce qui prime
1. Ce fichier et `docs/prompts/phase-00.md`.
2. `docs/spec/01-spec.md` pour les fonctions et les données.
3. `docs/maquette/` (lue dans `docs/spec/00-lecture-maquette.md`) pour le visuel.
4. `docs/distribution.md` et `docs/whatsapp/etat.json` pour les faits.

## Données
Importées de `etat.json` et `distribution.md` par `scripts/importer-etat.php` (phase 4). N'invente jamais un prénom, une chanson, une date, un tempo, une section, un placement, un ordre de passage ni un compte rendu : une valeur absente reste entre crochets (« [date] », « [lieu] ») et va dans `docs/A-FAIRE-PAR-PATRICE.md`. Les valeurs de démonstration de la maquette (sections, BPM 96/128/72/112, « on vise 2 min 30 », ordre 1-5, vidéos, commentaires, check-list de cinq lignes, présents de la répétition passée) ne sont jamais importées. Tests : « Démo », « Section A », vidéos synthétiques (canvas + MediaRecorder), jamais versionnées.

## Visuel
Grenat #8a1538, or #c9930a, jasmin #fffaf3, surfaces #f6ead8 / #efe0c8, texte #2a1a12, secondaire #5c4a44, filet #e3d3bb, vert #2e6b3a, orange #b4530a ; sombre : #17121a, #241c28 / #312736, #fff7ec, #cdbfb6, #a4284c (surfaces seulement), #e0ab2a, #3d3242, #7fcf8c, #f0a060. Yeseva One (titres, décompte) et Nunito Sans (texte), auto-hébergées. Corps 17 px, rien sous 14 px ; cibles 44 / 48 / 56 / 72 ; rayons 6 à 16 ; espacements 4 · 8 · 12 · 16 · 24 · 32 · 40. Aucune ombre, aucun dégradé, aucun emoji, aucune photo ni image générée. Une seule animation (le décompte), coupée en mouvement réduit. Mode répétition toujours sombre. États toujours écrits par un mot. Détails : `.claude/rules/design.md`.

## Pile (ne jamais dévier sans le dire)
PHP 8.3 sans framework (routeur maison, gabarits PHP, PDO), SQLite en WAL dans `stockage/base.sqlite` hors racine web, HTML/CSS/JS natifs sans construction ni bibliothèque cliente, PWA. Hébergement : Hostinger mutualisé de Patrice, sous-dossier `/chores` par défaut (`BASE_PATH` configurable).
Arborescence : `public/` (racine web), `app/` (bootstrap, routeur, bd, acces, securite, controleurs, vues, migrations), `stockage/` (ignoré, volume Docker en local), `scripts/`, `tests/{phpunit,e2e,js}`, `docs/`.

## Commandes
- `docker compose up -d --build` : site sur http://127.0.0.1:8095 (ports interdits : 3000, 3100, 3102, 5432, 5433, 7001, 8090, 3307).
- `docker compose exec -T web composer check` : lint, lint-inline, contrastes, PHPUnit.
- `pnpm test:e2e` : Playwright + axe (mobile 390 × 844, bureau 1 280 × 900) ; `pnpm check` = les deux ; `pnpm captures` ; `pnpm test:js` (`node --test "tests/js/*.test.mjs"` : Node 24 ne prend pas un dossier).
- Dans le conteneur : `php scripts/migrer.php`, `php scripts/nouveau-lien.php`, `php scripts/importer-etat.php` (phase 4), `php scripts/paquet.php` (phase 6).
- Toute dépendance nouvelle (Composer, pnpm) est justifiée dans PROGRESS avant l'ajout ; aucune dépendance côté client.

## Invariants (jamais contournés)
- Un seul lien secret (256 bits, haché en base, révocable par Patrice) + prénom en cookie signé. Administration : clé unique dans `config.php`, cookie posé par `/admin/cle/{cle}` ; pages admin en 404 sans ce cookie.
- Rien ne sort du serveur : aucune police, script, image, iframe, mesure ou service tiers ; vidéos des cousins jamais envoyées ailleurs ; tutoriels YouTube en liens sortants `rel="noopener noreferrer"`, `Referrer-Policy: no-referrer`.
- Vidéos dans `stockage/medias/`, servies par `/m/{id}` après contrôle du cookie ; aucune conversion sur le serveur (ffmpeg sur le PC de Patrice, H.264 720p `-crf 23 +faststart`).
- CSP sans `unsafe-inline` : aucun attribut `style`, aucun `<script>` inline, aucun `on*=`. En-têtes de sécurité sur toute réponse, `noindex` partout, limitation de débit (entrée, prénom, téléversements, commentaires), journal sans IP, CSRF sur tout formulaire.
- Tout se lit et tout formulaire fonctionne sans JavaScript ; le JavaScript améliore seulement.
- WCAG 2.2 AA : contrastes ≥ 4,5:1 (`scripts/contraste.php`), cibles ≥ 44 px, états dits par un mot, clavier complet, `prefers-reduced-motion`, zoom 200 % sans défilement horizontal. Détails : `.claude/rules/a11y.md`.
- Dates en UTC en base, affichées en Europe/Paris. `config.php` ignoré, `config.example.php` versionné, `config.php` local généré par `scripts/config-dev.php`.

## Interdits absolus
`git push`, dépôt distant, création ou connexion à un compte (Hostinger, GitHub, YouTube, Google…), déploiement réel, envoi de donnée ou de vidéo à un tiers ; image ou vidéo générée par IA ; donnée inventée ; lecture ou écriture de `config.php` (hors `scripts/config-dev.php`) ; secret affiché ; `--no-verify` ; `docker … prune` (seulement `docker rm <nom>` ciblé) ; conteneurs des autres projets ; `rm -rf` ou `Remove-Item -Recurse` hors des dossiers générés ; modification hors de `P:\PROJECT\chores-mariage` ; commande interactive ; port interdit ; police ou script externe ; framework ou bibliothèque cliente ; dépendance payante ; CAPTCHA ; emoji ou texte anglais dans l'interface.

## Garde-fous Claude Code
`.claude/settings.json` (refus et hooks), `.claude/hooks/` (PreToolUse : `config.php`, `stockage/`, commandes interdites ; Stop : `composer check`), `.claude/rules/`, skills `/phase` et `/a11y-check`, sous-agents `a11y-reviewer` et `securite-reviewer`.

## Phases
1. Maquette (faite). 2. Socle et accès. 3. Jetons, composants et lecteur. 4. Données, chansons, sections, placements et suivi. 5. Répétitions, vidéos des cousins, Jour J, PWA. 6. Recette, paquet et procédure de mise en ligne sur Hostinger. Une phase à la fois, prompt dans `docs/prompts/phase-NN.md`, plan dans `docs/plans/`.

## Avant de terminer un tour
1. `composer check` vert et, si une page a changé, `pnpm test:e2e` vert ; sinon dire ce qui échoue, avec la sortie, sans contourner.
2. Captures des écrans modifiés en 390 et 1 280 px dans `docs/captures/phase-NN/`.
3. Dire ce qui n'a pas été vérifié.
4. Fin de phase : `docs/PROGRESS.md` (fait, non fait, non vérifié, décisions, revues), `docs/A-FAIRE-PAR-PATRICE.md`, commit local, arrêt.
