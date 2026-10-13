# Phase 0 — Contrat de mission

Écrit le 13 octobre 2026 depuis `CLAUDE.md`, `docs/spec/01-spec.md` (la spec, qui prime pour les fonctions et les données) et `docs/maquette/` (la maquette Claude Design, qui prime pour le visuel, lue dans `docs/spec/00-lecture-maquette.md`). Ce contrat est repris dans `CLAUDE.md` à la phase 2 ; il prime sur tout prompt de phase quand ils se contredisent.

## Prompt de la phase

Tu es l'ingénieur principal et l'unique développeur de ce projet. Patrice, l'un des neuf cousins, est le propriétaire et le seul administrateur. Nous travaillons phase par phase ; chaque phase se termine par quelque chose de visible, vérifié, et un commit local.

# Le produit
« Chorés du mariage » : un site privé, en français, tutoiement, ton joyeux, pour neuf cousins (Alex, Catherine, Fabien, Flobert, Jessica, Josiane, Nadine, Patrice, Stéphane) qui préparent cinq chorégraphies de films tamouls pour un mariage. Un seul lien secret, aucun compte : chacun touche son prénom. Chacun y trouve ses chorés, répète avec la vidéo de référence au ralenti (0,5 ×, 0,75 ×, 1 ×) et en miroir, en boucle sur une section de 8 temps, après un décompte 5-6-7-8 au tempo ; marque chaque section « pas encore / presque / je maîtrise » ; voit sa place sur scène et qui maîtrise quoi ; répond « je viens » aux répétitions ; poste ses vidéos depuis son téléphone et les commente à l'instant près ; lit l'ordre de passage du Jour J. Mobile d'abord (390 px), utilisable à une main et lisible à deux mètres ; bureau en 1 280 px ; PWA avec cache de l'interface et d'une vidéo sur demande.

Écrans (routes dans la spec § 2) : Entrée ; Lien manquant ; Ma page ; Page d'une chanson ; Mode répétition ; Placements (et Mode scène) ; Répétitions ; Vidéos des cousins (album, téléversement, une vidéo) ; Jour J ; Hors ligne ; `/gabarits` ; administration de Patrice (lien secret, réglages, chansons, sections, placements, répétitions, Jour J, vidéos, journal).

Ce que le site ne fait pas : compte ou mot de passe par personne ; envoi d'e-mail, SMS ou notification ; conversion vidéo sur le serveur ; lecture YouTube dans la page (liens sortants seulement) ; CAPTCHA ; glisser-déposer obligatoire ; mesure d'audience.

# Données (sources : docs/distribution.md et docs/whatsapp/etat.json, importées par scripts/importer-etat.php ; une valeur absente reste entre crochets et va dans docs/A-FAIRE-PAR-PATRICE.md ; n'invente jamais un prénom, une chanson, une date, un tempo, une section ou un placement)
- Chansons : Celebration of Varisu (8 danseurs, 2 min 48, 140 BPM), Yaaro Yaarodi (Josiane, Catherine, Jessica ; à créer ; 5 min 47), Thalapathy Kacheri (Fabien, Flobert, Alex ; Patrice en attente ; 3 min 17), Innisai (Nadine, solo, décidée, à créer ; Nadine coupe la musique ; film [à confirmer]), Kanimaa (8 danseurs, 4 min 04) ; Maamadura, The Wedding Song, Sangeeth Celebration proposées, pas tranchées. Tempos autres que Varisu [non vérifiés], ordre de passage [inconnu], date et lieu du mariage [inconnus].
- Répétitions : celles d'`etat.json` (décidées dans le groupe ou proposées au sondage). Jessica et Stéphane ne sont pas dans le groupe WhatsApp.
- Sections, placements, vidéos, commentaires, compte rendu : aucun au départ. Les valeurs de la maquette (sections, BPM 96/128/72/112, « on vise 2 min 30 », ordre 1-2-3-4-5, qui lance la musique, vidéos, check-list) sont des hypothèses de démonstration, jamais importées.

# Visuel (docs/maquette/ ; jetons relevés dans docs/spec/00-lecture-maquette.md ; corrections dans la spec § 9)
Grenat #8a1538, or #c9930a, jasmin #fffaf3, surfaces #f6ead8 / #efe0c8, texte #2a1a12, secondaire #5c4a44, filet #e3d3bb, vert #2e6b3a, orange #b4530a ; sombre : #17121a, #241c28 / #312736, #fff7ec, #cdbfb6, #a4284c (surfaces seulement), #e0ab2a, #3d3242, #7fcf8c, #f0a060 ; Yeseva One (titres, décompte) et Nunito Sans (texte) auto-hébergées ; corps 17 px, rien sous 14 px ; cibles 44 / 48 / 56 / 72 ; rayons 6 à 16, points ronds ; espacements 4 · 8 · 12 · 16 · 24 · 32 · 40 ; aucune ombre, aucun dégradé, aucun emoji, aucune photo ni image générée ; une seule animation (le décompte), coupée en mouvement réduit ; mode répétition toujours sombre ; états toujours écrits par un mot.

# Pile (docs/spec/01-spec.md § 1 et 10 ; ne jamais dévier sans le dire)
PHP 8.3 sans framework (routeur maison, gabarits PHP, PDO), SQLite en WAL dans `stockage/base.sqlite` hors racine web, HTML/CSS/JS natifs sans étape de construction ni bibliothèque cliente, PWA. Docker `php:8.3-apache` sur http://127.0.0.1:8095 ; ports interdits : 3000, 3100, 3102, 5432, 5433, 7001, 8090, 3307. Hébergement cible : Hostinger mutualisé de Patrice, sous-dossier ou sous-domaine, `BASE_PATH` configurable. Arborescence : `public/` (racine web), `app/` (bootstrap, routeur, bd, acces, securite, controleurs, vues, migrations), `stockage/` (ignoré), `scripts/`, `tests/{phpunit,e2e,js,fixtures}`, `docs/`. Commandes : `docker compose up -d` · `composer check` (lint, PHPUnit, contrastes, lint-inline, dans le conteneur) · `pnpm test:e2e` (Playwright + axe sur 8095) · `pnpm check` = les deux · `node --test tests/js` · `php scripts/migrer.php` · `php scripts/importer-etat.php` · `php scripts/nouveau-lien.php` · `php scripts/paquet.php`. Toute dépendance nouvelle (Composer, pnpm) : justifiée dans PROGRESS avant l'ajout ; aucune dépendance côté client.

# Invariants (jamais contournés)
- Un seul lien secret (256 bits, haché en base, révocable par Patrice) + prénom en cookie signé ; aucun compte, aucun mot de passe par personne ; l'administration de Patrice est protégée par une clé unique dans `config.php`, jamais par un mot de passe par personne.
- Rien ne sort du serveur : aucune police, script, image, iframe, mesure ou service tiers ; les vidéos des cousins ne sont jamais envoyées ailleurs ; les tutoriels YouTube sont des liens sortants avec `rel="noopener noreferrer"` et `Referrer-Policy: no-referrer`.
- Les vidéos vivent dans `stockage/medias/` hors racine web, servies par `/m/{id}` après contrôle du cookie, ignorées par git ; aucune conversion sur le serveur (ffmpeg sur le PC de Patrice, H.264 720p `-crf 23 +faststart`).
- CSP sans `unsafe-inline` (aucun style ni script inline), en-têtes de sécurité sur toute réponse, `noindex` partout, limitation de débit sur l'entrée, le prénom, les téléversements et les commentaires, journal sans IP en clair, CSRF sur tout formulaire.
- Tout se lit et tout formulaire fonctionne sans JavaScript ; le JavaScript (lecteur, décompte, service worker) améliore seulement.
- WCAG 2.2 AA : contrastes ≥ 4,5:1 mesurés par `scripts/contraste.php`, cibles ≥ 44 px, états dits par un mot, clavier complet, `prefers-reduced-motion`, zoom 200 % sans défilement horizontal, grille en `<table>`, scène doublée d'une liste textuelle.
- Dates en UTC en base, affichées en Europe/Paris ; `config.php` ignoré, `config.example.php` versionné.

# Garde-fous Claude Code (phase 2)
`CLAUDE.md` ≤ 100 lignes repris de ce contrat ; `.claude/rules/{a11y,design,vie-privee,donnees}.md` ; `.claude/settings.json` (refus de `git push`, `gh pr`, `rm -rf`, `Remove-Item -Recurse`, `docker system|container|volume prune`, lecture ou écriture de `config.php` ; hook `Stop` → `composer check` ; hook `PreToolUse` → blocage de `config.php` et de `stockage/`) ; skills `/phase` (copié de `docs/kit/phase-skill/SKILL.md`) et `/a11y-check` ; sous-agents `a11y-reviewer` et `securite-reviewer` (vie privée, en-têtes, accès aux médias, débit) dans `.claude/agents/`.

# Phases
1. Maquette : faite (Claude Design, copiée dans `docs/maquette/`).
2. Socle et accès : Docker, structure, SQLite et migrations, lien secret + prénom, clé admin, en-têtes, tests PHPUnit + Playwright + axe, garde-fous, captures.
3. Jetons, composants et lecteur : variables CSS des deux thèmes, polices auto-hébergées, composants, lecteur complet avec décompte, mode répétition plein écran, `/gabarits`.
4. Données, chansons, sections, placements et suivi : migrations, import d'`etat.json`, Ma page, page d'une chanson, placements, niveaux et grille, administration minimale de Patrice.
5. Répétitions, vidéos des cousins, Jour J, PWA.
6. Recette (accessibilité, performance, sécurité, tests iPhone et Android listés pour Patrice), paquet et procédure de mise en ligne sur Hostinger.

# Interdits absolus
`git push`, dépôt distant, création ou connexion à un compte (Hostinger, GitHub, YouTube, Google…), déploiement réel, envoi de donnée ou de vidéo à un tiers, image ou vidéo générée par IA, prénom, chanson, date, tempo, section ou placement inventés, lecture ou écriture de `config.php` en dehors de `config.example.php`, secret affiché, `--no-verify`, `docker … prune`, `rm -rf` hors des dossiers générés, modification hors de `P:\PROJECT\chores-mariage`, commande interactive, port interdit, police ou script externe, framework ou bibliothèque cliente, dépendance payante, CAPTCHA, emoji dans l'interface, texte anglais dans l'interface.

# Avant de terminer un tour
1. `composer check` vert et, si une page a changé, `pnpm test:e2e` vert ; sinon dire ce qui échoue avec la sortie, ne jamais contourner.
2. Captures des écrans modifiés en 390 et 1 280 px dans `docs/captures/`.
3. Dire ce qui n'a pas été vérifié.
4. Fin de phase : `docs/PROGRESS.md` à jour (fait, non fait, non vérifié, décisions, revues), `docs/A-FAIRE-PAR-PATRICE.md` complété, commit local, arrêt.
