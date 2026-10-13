# Plan — Phase 3 : jetons, composants et lecteur

Écrit le 13 octobre 2026 avant le code, en mode autonome (tentative 1). Sources : `docs/prompts/phase-03.md`, spec § 4, 9, 12, 13, maquette (`Chorés du mariage.dc.html` : `THEMES`, `Component` ; `Planches.dc.html` : 1v Composants, 1w Fondations). Tag `avant-phase-3` posé.

## 1. Jetons et thèmes

- `public/css/jetons.css` : `@font-face` (polices locales) puis trois blocs de variables :
  - `:root, [data-theme="clair"]` : clair de la maquette ;
  - `[data-theme="sombre"]` : sombre de la maquette, corrigé ;
  - `@media (prefers-color-scheme: dark) { :root:not([data-theme="clair"]) { … } }` : copie exacte du bloc sombre (vérifiée par `contraste.php`, qui échoue si les deux diffèrent).
  - Les sélecteurs par attribut (pas seulement `:root`) permettent à `/gabarits` de montrer un panneau sombre dans une page claire, et au mode répétition de forcer `data-theme="sombre"` sur `<html>`.
- Jetons de couleur : `--fond`, `--surface`, `--surface-2`, `--texte`, `--secondaire`, `--grenat`, `--sur-grenat`, `--or`, `--sur-or`, `--filet`, `--vert`, `--orange` (contour), `--orange-texte` (texte « Choré à créer »), `--sur-orange`, `--accent` (texte et contour des boutons contour : grenat en clair, `--texte` en sombre, spec § 9), `--lien` (grenat en clair, or éclairci en sombre), `--focus`, `--focus-filet`, `--voile` (hex 8 chiffres, fond des voiles sur la vidéo), `--decompte` (or éclairci).
- Correction orange : `#a04a08` (5,08:1 sur `--surface`, 4,65:1 sur `--surface-2`, 5,81:1 sur `--fond`) ; `#b4530a` reste la couleur de contour.
- Tailles, hauteurs, rayons, espacements nommés (spec § 9), en `rem` ; `font-variant-numeric: tabular-nums` global ; `color-scheme` par thème.
- `scripts/contraste.php` réécrit : lit `jetons.css` (ou un fichier passé en argument, pour les tests), mesure chaque paire autorisée dans chaque thème (texte 4,5:1, contours et focus 3:1), compose `--voile` sur blanc (pire cas sous la vidéo), échoue si un jeton manque, si une paire échoue ou si les deux blocs sombres diffèrent. Fonctions communes dans `app/jetons.php` (aussi utilisées par `/gabarits` pour afficher les contrastes mesurés : aucune valeur hexadécimale dans les vues).
- `scripts/lint-inline.php` : nouvelle règle « couleur hors jetons » (hexadécimal, `rgb()`, `hsl()` hors `jetons.css`, dans les CSS, JS et vues) ; accepte un dossier racine en argument (tests).
- Bascule de thème : `POST /theme` (niveau public, CSRF exigé comme tout POST) pose `cm_theme` (clair ou sombre, 400 jours) et revient à la page (`retour` validé : chemin interne seulement). Rendu serveur : `data-theme` posé sur `<html>` d'après le cookie, rien sans cookie (le système décide). Deux boutons rendus, un seul visible selon le thème effectif (CSS) : « Mode sombre » avec l'état écrit « activé / désactivé » et `aria-pressed`. Avec JavaScript (`public/js/site.js`), bascule immédiate et enregistrement par `fetch`, sans rechargement.

## 2. Polices

- Dépendances de développement : `@fontsource/yeseva-one@5.3.0` et `@fontsource-variable/nunito-sans@5.3.0` (OFL-1.1, publiées en juillet 2026). Variable `wght` plutôt que trois graisses statiques : 59 Ko pour latin + latin-ext contre 83 Ko.
- `scripts/polices.mjs` copie les quatre woff2 (latin, latin-ext) vers `public/polices/` avec la version dans le nom (cache long sans risque), recopie les licences dans `public/polices/LICENCES.md`.
- Budget : 17,1 + 12,6 + 30,3 + 27,3 ≈ 87 Ko (≤ 120 Ko, vérifié par PHPUnit). Préchargement des deux fichiers latin.

## 3. Composants

- `app/vues/composants/*.php`, appelés par `Chores\composant($c, 'nom', [...])` ; `public/css/composants.css` (BEM français, un bloc par composant). `base.css` réécrit sans aucune couleur en dur.
- Liste : barre haute, bascule de thème, bouton prénom, carte répétition (variantes réponses / personne n'a répondu / date pas fixée avec lien ou encart de repli), bouton de présence, carte chanson (progression par classes de 5 %, à créer, décidée, en attente), encart (pointillé, orange « Qui s'en occupe ? », attente), fil « Ce qui bouge », lecteur et commandes, ligne de section, mini-scène, grille de maîtrise (`<table>`), lien tutoriel, carte vidéo, commentaire horodaté, bannière hors ligne, scène de placements (grille 21 × 21 par classes `col-N lig-N`, points 58/68 px abrégés à trois lettres, flèches SVG `aria-hidden` + texte, select + ← →, « Me surligner », ↑ ↓ ← →, « Public » 14 px, liste textuelle), frise Jour J, ligne de check-list, décompte, champs (texte, select, fichier, case, zone, erreur reliée), confirmation `role="status"`, encart « Nouvelle version : recharge ».
- États de niveau : le bouton choisi porte « ✓ » et un contour accent en plus de `aria-pressed` (pas d'état par la couleur seule). Section courante : mot « en cours ».

## 4. Lecteur

- `public/js/lecteur-calc.js` (fonctions pures : `cadenceMs`, `dansBoucle`, `formatTemps`, `formatDuree`, `sectionSuivante`, `sectionPrecedente`, `libelleVitesse`) testé par `node --test` ; `public/js/decompte.js` (décompte partagé par le lecteur et le composant autonome) ; `public/js/lecteur.js` (≤ 25 Ko).
- Le HTML du lecteur fonctionne sans JavaScript (`<video controls>`) ; les commandes de 56 px sont rendues avec `hidden` et révélées par le script, qui masque alors les contrôles natifs.
- Comportements de la spec § 4 : vitesses + `preservesPitch`, miroir par classe, boucle sur `timeupdate` + `requestVideoFrameCallback`, décompte 5-6-7-8 à `max(380, 60000 / bpm / vitesse)` ms avec zone `aria-live="polite"`, « Arrêter » pendant le décompte, Pause pendant la lecture, « Touche Lecture à nouveau » si `play()` est refusé (le geste suivant lance sans décompte), sections par lien `?section=N` (sans JS) ou clic intercepté (avec JS), raccourcis Espace, M, B, 1/2/3, N, P, Échap, désactivables (« Raccourcis clavier : activés / désactivés », mémorisé), vitesse et miroir mémorisés par chanson dans `localStorage` (avec `try/catch`), voile de chargement sur `waiting`/`stalled`.
- Tempo inconnu : 120 temps par minute et la mention « tempo à régler ». La chanson « Démo » n'a pas de tempo : elle montre cet état.

## 5. Mode répétition

- `GET /chanson/{slug}/repeter?section=N` : slug `demo` seulement en phase 3 (404 sinon), mêmes droits que `/gabarits`. Toujours sombre (`data-theme="sombre"` forcé), vidéo en haut, section et comptes en surimpression, statut « Miroir · 0,75 × · Boucle », Lecture et Section suivante 72 px, vitesses 60 px, Miroir, Boucle, Plein écran (`requestFullscreen`, masqué si absent), Quitter (lien) ; `wakeLock` pendant la lecture ; bureau : vidéo centrée, 1 100 px au plus ; décompte en 260 px.

## 6. Gabarits et vidéo de démonstration

- `GET /gabarits` : libre si `ENV = dev`, sinon cookie d'administration exigé (404). Palette des deux thèmes avec contrastes mesurés (lus dans `jetons.css`), échelle typographique, espacements, rayons, hauteurs, chaque composant dans chaque état, lecteur sur « Démo » (sections « Section A », « Section B », « Section C »), lecteur sans vidéo, décompte avec « Rejouer », scène avec huit points (les neuf cousins moins Nadine, positions géométriques de démonstration). `POST /gabarits/essai` : formulaires de démonstration, rien n'est enregistré, retour avec message `role="status"`.
- `GET /gabarits/demo.webm` : sert `stockage/medias/demo.webm` avec `Accept-Ranges` et réponses 206 (fonction `servir_fichier()` réutilisable en phase 5 pour `/m/{id}`), mêmes droits.
- `scripts/video-demo.mjs` : Chromium de Playwright, `canvas` + `MediaRecorder`, 20 s de bandes colorées avec le numéro de seconde, durée réécrite dans l'en-tête WebM (sinon `duration = Infinity` et le positionnement échoue), copie dans le volume par `docker compose exec -u www-data`. Jamais versionnée. Lancée par la préparation globale des tests si absente.

## 7. Tests

- PHPUnit : contraste (faux jeton à 4,4:1 → échec ; blocs sombres différents → échec), lint-inline (`style=`, couleur en dur → échec), budgets (polices ≤ 120 Ko, CSS ≤ 40 Ko, lecteur ≤ 25 Ko, JS ≤ 40 Ko), routes `/gabarits` (dev 200, production 404 sans clé), `/theme` (cookie, retour validé), mode répétition (demo 200, autre 404), plages HTTP (`Range`).
- `node --test` : cadence 140 tpm à 0,5 × = 857 ms, plancher 380 ms, boucle, formats.
- Playwright (390 et 1 280) : axe sur `/gabarits` et `/chanson/demo/repeter` en clair et en sombre, mouvement réduit (aucune animation pendant le décompte), largeur réduite (640 px au bureau = zoom 200 %, 320 px au téléphone) sans défilement horizontal, lecteur (vitesse → `playbackRate`, miroir → classe, boucle → retour au début, décompte 5 → 6 → 7 → 8 → lecture), clavier.
- Captures dans `docs/captures/phase-03/`.

## 8. Documentation

`docs/DESIGN.md`, `docs/PROGRESS.md` (section Phase 3), `docs/A-FAIRE-PAR-PATRICE.md`.

## Décisions prévues (reprises dans PROGRESS)

1. Nunito Sans variable (`wght`) au lieu de trois fichiers statiques (poids et budget).
2. Orange texte `#a04a08`.
3. `--accent` et `--lien` en plus des jetons de la spec (corrections de la spec § 9 rendues explicites).
4. Fichiers JS en `Cache-Control: no-cache` (les modules importés n'ont pas d'empreinte dans l'adresse) ; CSS et polices restent en cache long.
5. Bascule : libellé stable « Mode sombre » + état écrit, au lieu du libellé qui change de la maquette (un bouton bascule garde son nom).
6. Points de scène abrégés à trois lettres partout (prénoms entiers dans la liste textuelle), mini-scène en points de 36 px pour tenir trois lettres à 14 px.
7. Tempo de « Démo » inconnu (120 par défaut, « tempo à régler »).
