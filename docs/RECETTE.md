# Recette — version 1.0.0

Recette locale du 13 octobre 2026 (phase 6), sur le site Docker (http://127.0.0.1:8095, `ENV = dev`) et sur le
paquet installé dans un conteneur qui imite Hostinger (https://127.0.0.1:8096/chores/, `ENV = production`). Aucun
accès à Hostinger, à un serveur ni à un téléphone réel : ce qui en dépend est listé dans
`docs/A-FAIRE-PAR-PATRICE.md` (parties A à C). Les chiffres bruts sont dans `docs/recette/*.json`.

Données de test seulement : la base de test des e2e (faits importés d'`etat.json` et de `distribution.md`,
« Section A », « Section B », vidéo « Démo » synthétique, une répétition datée de 2099) ; aucune donnée inventée
n'entre dans la base de Patrice ni dans le paquet.

## Résumé

| Critère | Résultat |
|---|---|
| axe (WCAG 2.2 A/AA) sur toutes les pages, cousin et administration, clair et sombre, mode répétition, 390 et 1 280 px | 0 violation |
| Liste manuelle WCAG 2.2 (§ 1.2) | parcourue ; aucun défaut ouvert ; lecteurs d'écran réels à faire (A-FAIRE C) |
| Lighthouse mobile, 3 passes, médiane, 7 pages | performance 0,97 à 1 ; accessibilité 1 ; bonnes pratiques 1 |
| Budgets de la spec § 13 | HTML ≤ 21 Ko, CSS 14 Ko compressés, JS ≤ 18 Ko compressés, polices 49 Ko transférés (87 Ko au total), `preload="metadata"` partout |
| En-têtes, CSP, accès, CSRF, débit, journal sans IP, cookies en production | vérifiés (PHPUnit, e2e, essai du paquet) |
| Aucune requête hors origine | vérifié sur tout le parcours (interception e2e et essai du paquet) |
| Contenu (français, tutoiement, crochets, aucun mot anglais ni emoji) | vérifié ; inventaire des crochets au § 4 |
| Paquet décompressé en sous-dossier, chemin externe, base vide | démarre ; installation sans SSH puis scripts, parcours, axe, sécurité : vérifié |
| Sauvegarde puis restauration sur une base vide | vérifiée (script et administration) |
| Revues `a11y-reviewer`, `securite-reviewer`, revue équivalente à `/security-review`, revue de code | 0 bloquant (voir `docs/PROGRESS.md`) |

## 1. Accessibilité

### 1.1 Outillée (axe-core 4.13 par Playwright, `tests/e2e/recette.spec.ts`)

- **Pages** (projets `mobile` 390 × 844 et `bureau` 1 280 × 900, thème clair puis sombre) : `/moi`,
  `/chanson/kanimaa` (sections, vidéo, grille, « Ma place »), `/chanson/kanimaa?section=2`, `/chanson/yaaro-yaarodi`,
  `/chanson/innisai`, `/placements/kanimaa`, `/placements/kanimaa?scene=1`, `/repetitions`, `/videos`,
  `/videos/ajouter`, `/videos/{id}`, `/jour-j`, `/hors-ligne`, page introuvable, `/admin`, `/admin/lien`,
  `/admin/reglages`, `/admin/chansons`, `/admin/chansons/kanimaa`, `…/sections`, `…/placements`, `/admin/journal`,
  `/admin/repetitions`, `/admin/repetitions/1`, `/admin/jour-j`, `/admin/videos`, `/admin/base`, `/gabarits` ;
  mode répétition (toujours sombre) : `/chanson/kanimaa/repeter`, `?section=2`, `/chanson/yaaro-yaarodi/repeter`
  (sans vidéo), `/gabarits/repeter` ; `/lien` sans cookie ; `/installer` (formulaire, refus, site installé) dans les
  deux thèmes ; entrée et Ma page aussi dans l'essai du paquet. **Résultat : 0 violation.**
- **Structure** relevée sur les 28 pages (`docs/recette/structure.json`) : titre « … — Chorés du mariage », un seul
  `h1`, `<main id="contenu">`, lien d'évitement en premier, toute `<nav>` nommée, tout `<table>` avec `<caption>`,
  `lang="fr"`, aide « Un souci ? Écris à Patrice » en pied de page. **Tout conforme.**
- **Console** : aucune violation de la CSP ni erreur de script sur toutes ces pages (seule la page introuvable
  journalise son propre 404, ce qui est attendu).

### 1.2 Liste manuelle WCAG 2.2

| Critère | Comment c'est vérifié | Résultat |
|---|---|---|
| 2.1.1 Clavier complet, 2.1.2 aucun piège | 60 tabulations sur 10 pages dont le mode répétition (`recette.spec.ts`) ; lecteur au clavier (`gabarits.spec.ts` « raccourcis, Tab et Entrée ») ; Échap quitte le mode répétition (`repetition.spec.ts`) ; placements par flèches et champs, aucun glisser-déposer | conforme |
| 2.1.4 Raccourcis du lecteur | Espace, M, B, 1, 2, 3, N, P, Échap actifs seulement quand le focus est dans le lecteur (partout en mode répétition), désactivables et mémorisés (« Raccourcis clavier ») | conforme |
| 2.4.3 Ordre du focus | Relevé sur `/moi` et `/chanson/kanimaa` (`structure.json`) : évitement, barre haute (Changer, Mode sombre), contenu dans l'ordre de lecture (répétition, chorés, fil, navigation) ; lecteur : Lecture, Plein écran, vitesses, Miroir, Boucle, raccourcis, Garder, sections | conforme |
| 2.4.7 / 2.4.11 Focus visible, jamais masqué | Contour or 3 px doublé d'un filet sombre ; aucun élément `fixed` ou `sticky` ; à chaque arrêt de tabulation, l'élément est visible sous au moins un de trois points (`elementFromPoint`) sur 10 pages | conforme |
| 2.5.8 Taille des cibles | Toutes les cibles hors liens dans une phrase mesurées sur 10 pages : aucune sous 24 px, aucune sous 44 px (règle du projet) | conforme |
| 3.2.6 Aide au même endroit | Pied « Un souci ? Écris à Patrice [contact] » dernier élément de toutes les pages ; absent en mode répétition plein écran (décision 12 de la phase 3 : aucune aide ailleurs sur ces écrans) | conforme |
| 3.3.1 / 3.3.3 Erreurs | Résumé en haut (focalisé) et message au champ (`aria-invalid`, `aria-describedby`), en toutes lettres (« Erreur : … ») ; vérifié sur l'installation, les vidéos, les sections | conforme |
| 3.3.7 Aucune ressaisie | Formulaires renvoyés avec les valeurs saisies (titre de vidéo, sections, réglages) ; seule la clé d'installation est à recoller (exception de sécurité) | conforme |
| 3.3.8 Authentification accessible | Aucun mot de passe à retenir, aucun CAPTCHA ; la clé d'installation se colle (« Afficher la clé » pour la relire) | conforme |
| 1.4.10 Zoom 200 % et 400 % | 320 px avec le texte à 200 % sur toutes les pages (phases 3 à 5) ; 400 % = 320 × 225 sur les 30 pages de la recette, `/lien`, l'entrée et `/installer` : aucun défilement horizontal | conforme |
| 2.3.3 / mouvement réduit | Seul le décompte bouge ; `prefers-reduced-motion` coupe la mise à l'échelle (`gabarits.spec.ts` « mouvement réduit ») | conforme |
| 1.4.3 / 1.4.11 Contrastes | `scripts/contraste.php` : 70 paires des deux thèmes, 0 échec. Sur le rendu (`cibles-et-contrastes.json`) : badge « Miroir activé » 6,10:1 (clair) et 8,81:1 (sombre) ; points de scène 6,10 à 9,01:1 (clair) et 6,62 à 8,81:1 (sombre) ; surimpressions du mode répétition 6,62:1 au moins (voile semi-transparent composé sur blanc et sur noir, le plus faible retenu : 11,21:1) | conforme |
| 1.3.1 Titres, repères | Un `h1` par page, `h2`/`h3` dans l'ordre, `main`, `nav` nommées, régions défilantes nommées (`role="region"`, `tabindex="0"`) | conforme |
| 1.3.1 Grille « Qui maîtrise quoi » | `<table>` avec `<caption>`, `th scope="col"` (sections) et `th scope="row"` (prénoms) | conforme |
| 1.1.1 Scène | Plateau `role="img"` nommé « Scène vue de dessus… », doublé de la liste « Les positions en mots » (« Catherine (toi) : au centre, au milieu. ») | conforme |
| 4.1.3 Messages d'état | Décompte annoncé par une zone `aria-live="polite"` (« Décompte : 5 » … « 8 ») ; confirmations en `role="status"` ; « Lien copié. » annoncé | conforme |
| 1.4.4 / 1.4.12 Texte agrandi | Rien sous 14 px (balayage de toutes les pages, phases 3 à 5) ; décompte jamais coupé à 200 % | conforme |

**À faire sur de vrais appareils** (impossible ici) : VoiceOver (iPhone) et TalkBack (Android) sur l'entrée, Ma
page, une chanson, la grille, les placements, l'envoi d'une vidéo, la bascule de thème : procédure et résultat
attendu dans `docs/A-FAIRE-PAR-PATRICE.md`, partie C.

## 2. Performance

### 2.1 Lighthouse (`pnpm lighthouse`, `docs/recette/lighthouse.json`)

Lighthouse 13.5.0, réglages mobiles par défaut (Moto G Power émulé, « Slow 4G » simulé, processeur ralenti 4 fois),
3 passes par page, médiane ; base de test préparée (sections, vidéo « Démo », placements), cookies de Catherine.

| Page | Performance | Accessibilité | Bonnes pratiques | FCP | LCP | TBT | CLS | Transféré |
|---|---|---|---|---|---|---|---|---|
| `/moi` | 1 | 1 | 1 | 1 289 ms | 1 759 ms | 0 ms | 0 | 96 Ko |
| `/chanson/kanimaa` | 0,97 | 1 | 1 | 1 480 ms | 2 070 ms | 0 ms | 0,045 | 126 Ko |
| `/chanson/kanimaa/repeter` | 0,99 | 1 | 1 | 1 289 ms | 1 887 ms | 0 ms | 0,052 | 163 Ko (vidéo comprise) |
| `/placements/kanimaa` | 0,99 | 1 | 1 | 1 340 ms | 1 798 ms | 0 ms | 0 | 94 Ko |
| `/repetitions` | 1 | 1 | 1 | 1 294 ms | 1 767 ms | 0 ms | 0 | 95 Ko |
| `/videos` | 1 | 1 | 1 | 1 293 ms | 1 762 ms | 0 ms | 0 | 87 Ko |
| `/jour-j` | 1 | 1 | 1 | 1 266 ms | 1 589 ms | 0 ms | 0 | 89 Ko |

Seuils (performance ≥ 0,90, accessibilité ≥ 0,95, bonnes pratiques ≥ 0,95) : **atteints partout**. Mesure sur
http://127.0.0.1 (pas de TLS, pas de réseau réel) : à refaire en ligne pour avoir le vrai temps de réponse de
Hostinger (A-FAIRE B, facultatif).

### 2.2 Budgets de la spec § 13 (`docs/recette/poids.json`, toutes les pages)

| Budget | Mesure la plus lourde | Résultat |
|---|---|---|
| Page HTML ≤ 60 Ko | 21,0 Ko (`/chanson/kanimaa`) ; `/gabarits`, page de travail de Patrice, 102 Ko (hors budget) | conforme |
| CSS ≤ 40 Ko | 14,0 Ko compressés (64 Ko bruts, décision 4 de la phase 3) | conforme |
| JS ≤ 40 Ko, lecteur compris | 17,4 Ko compressés (`/chanson/kanimaa`, 50,3 Ko bruts) | conforme (mesure compressée, décision de la phase 6) |
| Polices ≤ 120 Ko | 87,3 Ko pour les quatre fichiers ; 47,4 Ko chargés par page (latin) | conforme |
| Images | icônes seulement (3,7 à 11 Ko) | conforme |
| Vidéos `preload="metadata"` | vérifié sur chaque `<video>` de chaque page | conforme |

## 3. Sécurité et vie privée

| Point | Preuve | Résultat |
|---|---|---|
| En-têtes complets sur toute réponse : pages, JSON, 404, `/m/{id}`, fichiers statiques, page de maintenance 503 | `EntetesTest` (Apache réel), `tests/paquet/fumee.spec.ts` (production : CSP avec `upgrade-insecure-requests`, HSTS, `no-referrer`, `nosniff`, `DENY`, `noindex`, sans `X-Powered-By`) | conforme |
| CSP sans `unsafe-inline` respectée | `lint-inline.php` (0 défaut sur 70 fichiers) ; aucune violation en console sur toutes les pages | conforme |
| `/m/{id}` refusé sans cookie | essai du paquet : 302 vers `/chores/lien` ; `MediasTest` | conforme |
| `stockage/`, configuration, `chemin-app.php` inaccessibles par HTTP | essai du paquet : `/chores-app/config.php`, `/chores-app/VERSION`, `/chores-app/stockage/base.sqlite`, `/chores/chemin-app.php`, `/chores/.htaccess` en 403 ou 404 ; repli sous la racine web : `/repli-essai/VERSION` en **403** (`.htaccess` du paquet) ; `EntetesTest` | conforme |
| `/admin` en 404 sans clé ; `/installer` en 404 une fois installé, pour toute méthode | `ParcoursTest`, `MaintenanceTest`, essai du paquet | conforme |
| CSRF refusé | `CsrfTest`, `acces.spec.ts` | conforme |
| Débit : `/e/`, `/prenom`, téléversement, commentaires, installation | `DebitTest`, `VideosTest`, `MaintenanceTest` (11ᵉ essai d'installation : 429) | conforme |
| Renouvellement du lien qui déconnecte tout le monde | `ParcoursTest`, `acces.spec.ts`, essai du paquet (`nouveau-lien.php` : ancien cookie et ancien lien vers `/lien`) | conforme |
| Aucune requête hors origine | interception sur chaque test e2e et dans l'essai du paquet : aucune | conforme |
| Journal sans IP ni navigateur | `MaintenanceTest` (parcours complet) et inspection de la base de test après la recette (`e2e-outil.php inspecter`) : aucune adresse, aucun navigateur | conforme |
| Cookies `HttpOnly`, `SameSite=Lax`, `Secure`, chemin `/chores` en production | essai du paquet en HTTPS (`cm_acces`, `cm_admin`, `cm_prenom`) ; `CookiesTest` | conforme |
| Liens sortants `rel="noopener noreferrer"`, `Referrer-Policy: no-referrer` | composant `lien-tutoriel`, `InterfaceTest`, en-têtes | conforme |
| Pages jamais compressées (BREACH) | essai du paquet : aucune `Content-Encoding` sur pages, JSON et `sw.js` avec `Accept-Encoding: gzip, br` | conforme en local ; à revérifier chez Hostinger (LiteSpeed) |

## 4. Contenu

- **Relecture** : toutes les pages parcourues par la recette (texte rendu, `aria-label`, `title`) : aucun mot anglais
  hors noms propres (titres des chansons d'`etat.json`, artistes et chaînes des tutoriels, décision 19 de la phase 4),
  aucun emoji (les flèches de texte ↗ des liens sortants et la coche ✓ restent), tutoiement et accents vérifiés.
- **Prénoms, chansons, dates** : uniquement ceux d'`etat.json`, de `distribution.md` et des réglages ; aucune valeur de
  démonstration de la maquette importée (`ImportTest`).
- **Inventaire des crochets** (`docs/recette/crochets.json`, base de test sans réglages) :

| Page | Crochets |
|---|---|
| toutes les pages (pied de page) | « [contact] » tant que « Comment te joindre » est vide |
| `/chanson/innisai`, `/admin/chansons/kanimaa` (aide des champs Film et Durée) | « [film à confirmer] », « [durée inconnue] » |
| `/jour-j`, `/admin/reglages` | « [date] », « [lieu] » |
| `/gabarits` (démonstration des composants) | « [date] », « [heure] », « [lieu] », « [à compléter] » |

  Chacun est à remplir par Patrice (`docs/A-FAIRE-PAR-PATRICE.md`, partie E).
- **Liens YouTube** (23 adresses `youtu.be` d'`etat.json` et des tutoriels) : `HEAD` le 13 octobre 2026, réseau
  disponible : les 23 répondent 303 puis 200 sur `youtube.com/watch`. YouTube répond 200 même pour une vidéo
  retirée : l'existence des vidéos elles-mêmes **n'est pas vérifiée**.

## 5. Essai du paquet (`pnpm essai:paquet`)

- `scripts/paquet.php` : `dist/chores-mariage-v1.0.0.zip`, 132 fichiers pris dans l'index git, compressé (≈ 366 Ko),
  contenu contrôlé (aucun `stockage/`, configuration, test, `node_modules`, `vendor`, `.claude`, `.git`, doc hors
  `docs/deploy/`, outil de développement), relu après écriture.
- Conteneur jetable `chores-mariage-paquet` (image `php:8.3-apache` du projet), https://127.0.0.1:8096 avec un
  certificat auto-signé ; arborescence de Hostinger imitée (`/home/u000000000/domains/exemple.test/public_html/chores/`
  et `…/chores-app/` hors racine web), `chemin-app.php`, `BASE_PATH=/chores`, `ENV=production`, configuration générée
  sans être affichée, `stockage/` en 750, configuration en 600, tout au compte qui fait tourner PHP.
- Déroulé (`tests/paquet/fumee.spec.ts`), **tout vert** :
  1. `/chores/installer` sur base vide : clé refusée puis acceptée, 8 chansons importées, premier lien affiché une fois,
     cookies `HttpOnly` `Secure` `SameSite=Lax` sur `/chores`, puis `/chores/installer` en 404 ;
  2. entrée, prénom, Ma page, une chanson, placements, répétitions, vidéos, ajout, Jour J, hors ligne : 200 et axe à
     390 et 1 280 px ;
  3. administration (tableau, `/admin/base`, chansons, réglages, répétitions, vidéos, Jour J, journal, lien,
     gabarits), vidéo de référence déposée et servie par `/chores/m/{id}` en 206, refusée sans cookie ;
  4. en-têtes et fichiers protégés (§ 3) ;
  5. comme en SSH : `migrer.php` (« Base à jour »), `importer-etat.php` (0 ajout), `nouveau-lien.php` (tout le monde
     déconnecté, le nouveau lien marche) ;
  6. témoin de maintenance : 503 « Petite pause » avec les en-têtes, puis retour à la normale ;
  7. sauvegarde, perte de la base, restauration (§ 6) ;
  8. aucune requête hors origine, aucune violation de la CSP.
- Conteneur supprimé à la fin (`docker rm -f chores-mariage-paquet`).

## 6. Sauvegarde et restauration

- **Script, sur une base vide** (essai du paquet) : `sauvegarde.php` → `base-AAAA-MM-JJ-HHMMSS.sqlite` ; la base est
  déplacée puis remplacée par une base vide migrée (le site renvoie vers « Lien à demander ») ; `restaurer.php … --oui`
  → « Base restaurée depuis … (9 cousins). », copie de l'ancienne dans `avant-restauration/` ; la réponse « je viens »
  et la session reviennent.
- **Administration** (essai du paquet et e2e) : « Faire une sauvegarde maintenant », changement de réponse, nouveau
  lien, puis « Restaurer » › « Oui, restaurer » : la réponse d'avant revient, la session de Patrice reste ouverte, le
  lien d'avant la sauvegarde ne revient pas, le dernier lien marche toujours.
- **PHPUnit** (`MaintenanceTest`) : restauration sur une base sans tables, sur un fichier abîmé (copie brute gardée),
  avec un lien plus récent gardé ; sauvegarde étrangère, abîmée ou absente refusée sans toucher la base ; deux
  sauvegardes dans la même seconde ; tri par nom et élagage par base.

## 7. Non vérifié

- Tout ce qui dépend de Hostinger : PHP et extensions réels, LiteSpeed (`.htaccess`, compression, `Require all
  denied`), écriture hors `public_html`, SSH, tâches planifiées, version de SQLite (`VACUUM INTO` demande 3.27, repli
  par copie prévu), adresse IP vue derrière un éventuel relais, journaux d'accès : `docs/deploy/verification.md`.
- Vrais téléphones (Safari iPhone, Chrome Android), lecteurs d'écran réels, vraie vidéo HEVC : A-FAIRE B et C.
- Firefox et Safari de bureau : seul Chromium (Playwright) a servi.
- Existence des vidéos YouTube derrière les liens (§ 4).
