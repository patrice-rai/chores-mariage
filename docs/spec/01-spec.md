# Chorés du mariage — spécification

Écrite le 13 octobre 2026 à partir de la maquette (`docs/maquette/`, lue dans `00-lecture-maquette.md`), du brief (`docs/prompts/prompt-claude-design.md`), des faits (`docs/distribution.md`, `docs/whatsapp/etat.json`) et des recherches (`docs/recherches/`). Ordre de priorité quand deux sources se contredisent : `CLAUDE.md`, puis cette spec, puis la maquette pour le visuel, puis les faits pour les données. Tout ce qui n'est ni dans la maquette ni dans `etat.json` reste entre crochets ; aucun prénom, chanson ou date n'est inventé.

## 1. Pile et principes

- **Pile retenue** : celle de `CLAUDE.md`. PHP 8.3 sans framework (un routeur de 100 lignes, des gabarits PHP, PDO), SQLite (un fichier `stockage/base.sqlite` hors racine web, mode WAL), HTML/CSS/JS statiques sans étape de construction (CSS natif avec variables, JavaScript ES2022 en modules, aucune bibliothèque côté client), PWA. Développement local dans Docker `php:8.3-apache` sur http://127.0.0.1:8095. Ports interdits : 3000, 3100, 3102, 5432, 5433, 7001, 8090, 3307.
- Je ne préfère pas d'autre pile : le mutualisé Hostinger n'exécute ni Node en continu ni ffmpeg, et neuf cousins ne justifient ni framework ni base serveur ; SQLite en WAL supporte sans peine quelques écritures par minute.
- **Progressive enhancement** : chaque page se lit et chaque formulaire (prénom, présence, niveau, commentaire, téléversement, check-list) fonctionne sans JavaScript, par `POST` et redirection. Le JavaScript (lecteur, décompte, boucle, miroir, placements interactifs, progression de téléversement, service worker, bascule de thème) améliore sans conditionner.
- **Rien ne sort du serveur** : aucune police, script, image, iframe ou mesure d'audience externe ; les seules ressources externes sont des liens sortants vers YouTube (`rel="noopener noreferrer"`, `Referrer-Policy: no-referrer`). Les vidéos des cousins ne quittent jamais le serveur.
- **Arborescence** :

```
public/            racine web : index.php, .htaccess, css/, js/, polices/, icones/, manifest.webmanifest, sw.js, hors-ligne.html
app/               bootstrap.php, routeur.php, bd.php, acces.php, securite.php, controleurs/, vues/ (gabarits et composants), migrations/*.sql
stockage/          IGNORÉ PAR GIT : base.sqlite, medias/ (vidéos), sauvegardes/
scripts/           migrer.php, importer-etat.php, nouveau-lien.php, contraste.php, lint-inline.php, icones.php, paquet.php, sauvegarde.php
tests/             phpunit/, e2e/ (Playwright), fixtures/
docs/              ce dossier
config.example.php versionné ; config.php ignoré (secrets)
compose.yml, Dockerfile, composer.json, package.json, playwright.config.ts
```

- `public/index.php` charge `app/bootstrap.php` par `__DIR__ . '/../app/'` ; un fichier facultatif et non versionné `public/chemin-app.php` peut redéfinir ce chemin sur l'hébergeur (voir § 10). `BASE_PATH` (par exemple `/chores`) est lu dans `config.php` : toutes les routes, cookies et le périmètre du service worker en tiennent compte.

## 2. Écrans et routes

Toutes les routes sauf `/e/{jeton}`, `/lien`, `/hors-ligne`, `/manifest.webmanifest`, `/sw.js` exigent le cookie d'accès (§ 6) ; toutes sauf `/` exigent en plus un prénom. Les écrans renvoient à la maquette (planche entre parenthèses).

| Route | Écran | Détail |
| --- | --- | --- |
| `GET /e/{jeton}` | Entrée par le lien secret | Vérifie le jeton, pose le cookie d'accès, redirige vers `/` (le jeton ne reste ni dans l'historique ni dans un referrer). Jeton faux ou révoqué → `/lien`. |
| `GET /lien` | Lien manquant ou périmé (absent de la maquette) | Même bandeau grenat que l'entrée, texte « Ce lien n'est plus valable (ou tu n'en as pas). Demande le nouveau à Patrice. » Aucun formulaire. |
| `GET /` | Entrée (1a) | Neuf prénoms en boutons de 72 px, triés comme dans `CLAUDE.md`. Si le cookie prénom existe → redirection vers `/moi`. |
| `POST /prenom` | — | Pose le cookie prénom, journalise « entrée », redirige vers `/moi`. |
| `POST /changer` | — | Efface le cookie prénom, redirige vers `/` (bouton « Changer » de la barre haute). |
| `GET /moi` | Ma page (1b, 1c, 1d, 1y, 1r) | « Salut {prénom} », prochaine répétition avec présence, « Tes chorés » (chansons où je danse ou suis en attente, triées par ordre de passage puis par titre), encart « Proposées, pas tranchées », « Ce qui bouge » (10 derniers événements : vidéos, commentaires, présences, niveaux « je maîtrise »), navigation Placements / Répétitions / Vidéos des cousins / Jour J. Si aucune chanson ne me concerne : « Tu n'es sur aucune choré pour l'instant. » |
| `GET /chanson/{slug}` | Page d'une chanson (1e à 1j, 1z, 1s, bureau 1t) | Titre, film, durée, tempo, danseurs ; encart « choré à créer » ; lecteur ; sections avec mes niveaux ; « Ma place » ; « Qui maîtrise quoi » ; tutoriels ; vidéos des cousins pour cette chanson. Paramètre `?section=N` pour arriver sur une section. |
| `GET /chanson/{slug}/repeter?section=N` | Mode répétition (1k, 1l) | Plein écran, toujours sombre, mêmes commandes en 72/60 px, « Section suivante », « Quitter » (retour sur la page de la chanson, même section). En 1 280 px : vidéo centrée, largeur maximale 1 100 px, commandes sous la vidéo [composition non dessinée : même hiérarchie que le mobile]. |
| `POST /chanson/{slug}/section/{id}/niveau` | — | `niveau` ∈ {0, 1, 2} pour moi ; répond par redirection, ou JSON si `Accept: application/json`. |
| `POST /chanson/{slug}/createur` | — | « Qui s'en occupe » pour une chanson à créer : le prénom choisi devient `createur` (modifiable par tout danseur de la chanson ; journalisé). |
| `GET /placements/{slug}?section=N` | Placements (1m ; bureau 1u) | Une section à la fois, flèches vers la suivante, select + ← →, « Me surligner », déplacement de mon point par ↑ ↓ ← → (enregistré pour tous, journalisé ; en lecture seule si `placements_verrouilles` est vrai dans les réglages). Bureau : toutes les sections côte à côte. `?scene=1` : mode scène (titre « Mode scène », points 68 px, bouton Quitter → Jour J). |
| `GET /repetitions` | Répétitions (1n, 1o) | « Proposer des dates » (lien `lien_sondage` des réglages ; si vide : encart « Pas de sondage en cours : dis tes dispos à Patrice ») ; à venir (décidées puis proposées, avec Je viens / Je ne peux pas sur chacune) ; passées (présents, absents, sans réponse, compte rendu). |
| `POST /repetitions/{id}/presence` | — | `reponse` ∈ {oui, non} pour moi. |
| `GET /videos` | Vidéos des cousins (1p) | « Ajouter ma vidéo », « Ces vidéos restent entre nous. », un album par chanson (ordre de passage puis titre), chaque vidéo avec ses commentaires. |
| `GET /videos/ajouter` | Téléversement (non dessiné) | Formulaire : chanson (select), titre (obligatoire, 80 caractères), note (facultative, 300), « Filmée en miroir » (case), fichier (`accept="video/mp4,video/quicktime,video/webm"`), rappel des limites, bouton 56 px « Envoyer ». Avec JavaScript : barre de progression et texte « 42 % · 31 Mo sur 74 Mo », bouton Annuler. |
| `POST /videos` | — | Reçoit le fichier (§ 7), redirige vers `/videos/{id}`. |
| `GET /videos/{id}` | Une vidéo (variante de 1p) | Lecteur simple (`<video controls>`, miroir si l'auteur l'a coché ou sur bouton), commentaires horodatés cliquables (seek), formulaire « à {temps} » prérempli avec la position courante + texte (200 caractères), bouton « Supprimer ma vidéo » pour l'auteur et pour Patrice (confirmation en page). |
| `POST /videos/{id}/commentaires` | — | `temps` (secondes, facultatif) + `texte`. |
| `POST /videos/{id}/supprimer` | — | Auteur ou Patrice ; le fichier est effacé, la ligne marquée `supprime_le`. |
| `GET /jour-j` | Jour J (1q) | « Mariage le [date] » depuis les réglages, bouton « Mode scène », frise de l'ordre de passage (chansons avec `ordre_passage` non nul ; sinon « L'ordre de passage n'est pas encore fixé »), pour chacune « Qui entre / D'où / Musique » ([à compléter] si vide), check-list partagée (chaque ligne : fait / à faire, par qui). |
| `POST /jour-j/check/{id}` | — | Bascule une ligne de la check-list (journalisé avec le prénom). |
| `GET /hors-ligne` | Hors ligne (bannière 1h + page de repli) | Servie par le service worker quand la page demandée n'est pas en cache : « Pas de réseau : tu peux répéter les chansons gardées sur ton téléphone » + liste des chansons dont la vidéo est en cache (lue par JS dans Cache Storage). |
| `GET /m/{id}` | Flux vidéo | Sert un fichier de `stockage/medias/` après contrôle du cookie d'accès, avec `Accept-Ranges`, réponses `206`, `Content-Type`, `Cache-Control: private, max-age=31536000, immutable`. |
| `GET /gabarits` | Gabarits (phase 3) | Palette, textes, chaque composant dans chaque état, les deux thèmes, le décompte avec « Rejouer » ; réservé à Patrice en production (`noindex` partout de toute façon). |
| `GET /api/etat` | JSON | Pour le lecteur : sections d'une chanson (`?chanson=slug`), mes niveaux, version du lien. Jamais de donnée d'un autre cousin au-delà de ce que les pages montrent déjà. |

Administration (Patrice seulement, § 6 ; écrans non dessinés, à composer avec les mêmes composants, sobres, sans nouvelle couleur) :

| Route | Fonction |
| --- | --- |
| `GET /admin` | Tableau : lien secret actuel (copiable), nombre d'entrées par prénom (journal), dernières actions, taille de `stockage/`, estimation de l'espace vidéo, bouton « Télécharger une sauvegarde ». |
| `GET/POST /admin/lien` | « Nouveau lien » : génère un nouveau jeton, invalide l'ancien aussitôt, affiche le nouveau lien en entier et un texte prêt à coller pour WhatsApp et pour Jessica et Stéphane. |
| `GET/POST /admin/reglages` | Date et lieu du mariage, lien du sondage, texte d'accueil, `placements_verrouilles`, taille maximale d'upload affichée, durée accordée aux danses [inconnue]. |
| `GET/POST /admin/chansons` et `/admin/chansons/{slug}` | Titre, film, durée, BPM (champ + bouton « Taper le tempo » : moyenne des 8 derniers tapotements), statut (proposée / décidée / écartée), à créer, ordre de passage, « qui entre, d'où, qui lance la musique », danseurs et danseurs en attente (cases à cocher), liens (URL, titre, durée, type, en miroir), vidéo de référence (téléversement par Patrice d'un MP4 H.264 déjà converti, § 7). |
| `GET/POST /admin/chansons/{slug}/sections` | Liste ordonnée : nom, début, fin (mm:ss,d), comptes (nombre de 8), note ; boutons « Depuis le lecteur » qui posent début/fin à la position courante du lecteur affiché sur la même page ; ajouter, supprimer, monter, descendre ; contrôle : sections contiguës ou disjointes mais jamais chevauchantes, fin > début, fin ≤ durée. |
| `GET/POST /admin/chansons/{slug}/placements` | Pour chaque section : grille 5 × 5, un point par danseur, déplacement par flèches ou par champs x/y (0 à 100) ; « Copier la section précédente ». |
| `GET/POST /admin/repetitions` et `/admin/repetitions/{id}` | Date, heure, lieu, statut (proposée / décidée / passée / annulée), « on travaille », compte rendu (après coup), présents et absents constatés (cases, distinctes des réponses « je viens »). |
| `GET/POST /admin/jour-j` | Lignes de la check-list (ajouter, renommer, ordonner, supprimer). |
| `GET /admin/videos` | Toutes les vidéos avec taille, codec deviné, bouton Supprimer, bouton « Remplacer par une version convertie ». |
| `GET /admin/journal` | 200 dernières lignes du journal. |

## 3. Modèle de données (SQLite)

Conventions : `id INTEGER PRIMARY KEY`, dates ISO 8601 en texte (`cree_le`, `maj_le`, fuseau Europe/Paris en affichage, UTC en base), clés étrangères activées (`PRAGMA foreign_keys = ON`), `PRAGMA journal_mode = WAL`, migrations numérotées dans `app/migrations/NNN-nom.sql` appliquées par `scripts/migrer.php` (table `migrations`).

| Table | Colonnes | Règles |
| --- | --- | --- |
| `cousins` | `id`, `prenom` (unique), `ordre`, `dans_le_groupe_whatsapp` (0/1), `contact_whatsapp` (texte ou NULL), `est_admin` (0/1, Patrice seulement), `actif` | Les neuf prénoms de `CLAUDE.md` ; aucun ajout sans Patrice. |
| `chansons` | `id`, `slug`, `titre`, `film` (NULL ou crochets), `duree_s` (NULL si inconnue), `bpm` (NULL si inconnu), `bpm_verifie` (0/1), `statut` ∈ {proposee, decidee, ecartee}, `a_creer` (0/1), `createur_id` (cousin, NULL), `ordre_passage` (NULL), `entree_qui`, `entree_dou`, `musique_qui` (textes, NULL), `duree_coupee_s` (NULL), `video_ref_id` (vidéo de référence, NULL), `note` | Le tempo pilote le décompte : s'il est NULL, le décompte utilise 120 BPM et l'écran le dit (« tempo à régler »). |
| `liens` | `id`, `chanson_id`, `url`, `titre`, `duree_s` (NULL), `type` ∈ {officiel, reference, tutoriel}, `en_miroir` (0/1/NULL = inconnu), `partage_par` (texte), `partage_le` | Liens sortants uniquement, jamais d'iframe. |
| `distributions` | `chanson_id`, `cousin_id`, `statut` ∈ {danse, en_attente} ; clé unique | « en attente » = a demandé, pas de réponse (Patrice sur Thalapathy Kacheri). |
| `sections` | `id`, `chanson_id`, `ordre`, `nom` (40 car.), `debut_s` (réel), `fin_s` (réel), `comptes` (entier : nombre de « 8 »), `note` | `fin_s > debut_s` ; pas de chevauchement dans une chanson ; `fin_s ≤ duree_s` si connue. Comptes et durée affichés « 4 × 8 · 0:17 ». |
| `placements` | `section_id`, `cousin_id`, `x`, `y` (entiers 0 à 100, origine en haut à gauche, public en bas) ; clé unique | Sans ligne : le danseur est « non placé » (affiché en bas, hors grille, avec le mot « non placé »). |
| `niveaux` | `cousin_id`, `section_id`, `niveau` ∈ {0, 1, 2}, `maj_le` ; clé unique | 0 « pas encore », 1 « presque », 2 « je maîtrise ». Absence de ligne = 0. |
| `repetitions` | `id`, `date` (AAAA-MM-JJ), `heure` (texte, NULL), `lieu` (NULL), `statut` ∈ {proposee, decidee, passee, annulee}, `travail` (texte), `compte_rendu` (texte, NULL), `source` ∈ {whatsapp, site}, `note` | « proposée » couvre les options du sondage WhatsApp. |
| `presences` | `repetition_id`, `cousin_id`, `reponse` ∈ {oui, non, peut_etre}, `source` ∈ {whatsapp, site}, `maj_le` ; clé unique | `peut_etre` sert aux votes « dispo » importés et aux incertains ; constatés après coup : colonnes `present_constate` (NULL/0/1) posées par Patrice. |
| `videos` | `id`, `chanson_id`, `cousin_id` (auteur ; NULL pour une vidéo de référence déposée par Patrice), `type` ∈ {cousin, reference}, `titre`, `note`, `fichier` (nom interne aléatoire + extension), `mime`, `taille_o`, `duree_s` (NULL si inconnue, posée par JS `loadedmetadata` au premier visionnage de l'auteur), `codec_devine` ∈ {h264, hevc, inconnu}, `en_miroir` (0/1), `cree_le`, `supprime_le` (NULL) | Fichier dans `stockage/medias/{id}-{aleatoire}.{ext}` ; jamais sous `public/`. |
| `commentaires` | `id`, `video_id`, `cousin_id`, `temps_s` (réel, NULL = commentaire général), `texte` (200 car.), `cree_le`, `supprime_le` | Supprimable par l'auteur et par Patrice. |
| `checklist` | `id`, `libelle`, `ordre`, `fait` (0/1), `fait_par` (cousin, NULL), `fait_le` | Partagée par tout le groupe. |
| `reglages` | `cle` (PK), `valeur` | `jeton_hash`, `jeton_version`, `jeton_cree_le`, `date_mariage`, `lieu_mariage`, `lien_sondage`, `texte_entree`, `placements_verrouilles`, `duree_danses_min`, `version_schema`. |
| `journal` | `id`, `quand`, `type` (entree, prenom, niveau, presence, video_ajout, video_suppr, commentaire, placement, createur, check, admin_lien, admin_modif, refus_debit, refus_jeton), `cousin_id` (NULL), `detail` (texte court, sans IP ni user-agent) | Append-only (aucune route de modification), purge des lignes de plus d'un an par `scripts/migrer.php`. |
| `debit` | `cle` (hash), `fenetre` (texte AAAA-MM-JJTHH), `compteur` | Limitation de débit (§ 11) ; `cle` = SHA-256(action + IP + sel du jour) : l'IP n'est jamais stockée en clair, le sel change chaque jour. |
| `migrations` | `version`, `applique_le` | — |

Dérivés affichés : progression d'une chanson pour moi = nombre de sections à 2 sur nombre de sections ; grille « Qui maîtrise quoi » = danseurs (et en attente) × sections ; « Ce qui bouge » = union des dix derniers `videos`, `commentaires`, `presences` (source site), `niveaux` passés à 2.

## 4. Le lecteur

- Élément `<video playsinline preload="metadata" controls>` (les `controls` natifs restent disponibles sans JavaScript ; avec JavaScript ils sont masqués au profit des commandes de 56 px) ; source `/m/{id}` de la vidéo de référence de la chanson (MP4 H.264 + AAC, 720p, `+faststart`). Sans vidéo de référence : aplat grenat « Pas encore de vidéo de référence pour cette chanson » et commandes désactivées (`disabled` + phrase), les sections et niveaux restent utilisables.
- **Vitesses** 0,5 × / 0,75 × / 1 × (`video.playbackRate`), `video.preservesPitch = true` (repli `webkitPreservesPitch`) pour garder la hauteur de la musique ; la vitesse choisie est mémorisée par chanson dans `localStorage`.
- **Miroir** : classe `.lecteur--miroir` → `transform: scaleX(-1)` sur l'élément `<video>` seul (jamais sur les surimpressions) ; bouton `aria-pressed` « Miroir — activé / désactivé » ; badge « Miroir activé » sur la vidéo. Mémorisé par chanson.
- **Boucle A-B par section** : sur `timeupdate` (4 fois par seconde environ) et, si disponible, `requestVideoFrameCallback` pour plus de précision : si `currentTime >= fin_s - 0,08` alors `currentTime = debut_s` ; la boucle suit la section courante ; sans section (chanson sans découpage) le bouton Boucle est désactivé avec la phrase « Pas de section encore ».
- **Décompte 5-6-7-8** : avant chaque lecture lancée par le bouton Lecture (pas après une pause reprise par les contrôles natifs), la vidéo est positionnée sur `debut_s` de la section courante, un voile affiche 5, 6, 7, 8 en Yeseva One (120 px dans le lecteur, 260 px en mode répétition, couleur #e0ab2a), un chiffre par temps, cadence `max(380, 60000 / bpm / vitesse)` ms (au ralenti le compte ralentit comme la musique), puis `video.play()`. Le texte « Décompte : 7 » est écrit sous le chiffre et dans une zone `aria-live="polite"`. En `prefers-reduced-motion`, les chiffres apparaissent sans mise à l'échelle. Le bouton Lecture devient « Stop » pendant le décompte et « Pause » pendant la lecture. Autoplay : `play()` est appelé dans le geste de l'utilisateur ; si le navigateur refuse, le lecteur affiche « Touche Lecture à nouveau ».
- **Sections** : toucher une ligne de section positionne la vidéo au début et en fait la section courante ; « Section suivante » (mode répétition) passe à la suivante et relance le décompte si la lecture était en cours ; la barre de progression or montre la position sur la chanson entière, les bornes de la section en traits.
- **Clavier** (bureau) : Espace lecture/pause, M miroir, B boucle, 1 / 2 / 3 vitesses, N section suivante, P section précédente, Échap quitte le mode répétition ; tout est aussi accessible par Tab et Entrée sur les boutons.
- **Mode répétition** : page dédiée plein écran (`/chanson/{slug}/repeter`), `requestFullscreen` proposé sur bouton (jamais imposé), écran gardé allumé par `navigator.wakeLock` quand disponible (abandon silencieux sinon), thème sombre forcé, commandes 72/60 px, nom de la section et ses comptes en surimpression (28/20 px), statut « Miroir · 0,75 × · Boucle » écrit en haut à droite.
- **Chargement** : voile « Chargement de la vidéo… » sur `waiting`/`stalled`, retiré sur `canplay` ; si la vidéo est en cache (service worker), le voile dit « Vidéo gardée sur ton téléphone ».
- **Hors ligne** : bannière « Pas de réseau : tu peux répéter les chansons gardées sur ton téléphone » ; les changements de niveau faits hors ligne sont mis en file dans `localStorage` et renvoyés au retour du réseau (message « 2 niveaux à envoyer »), sans Background Sync (absent de Safari). Écart assumé avec la maquette : le cache est par vidéo entière, pas par section (§ 8).
- **Vidéos des cousins** : même lecteur simplifié (vitesses, miroir, pas de décompte ni de boucle) ; les commentaires horodatés positionnent la vidéo (`currentTime = temps_s`) ; le formulaire de commentaire prérenseigne « à 0:42 » avec la position courante (bouton « Prendre la position »).

## 5. Données initiales (import de `docs/whatsapp/etat.json` et `docs/distribution.md`)

`scripts/importer-etat.php` est idempotent (clé : prénom, titre de chanson, date de répétition, URL de lien). Il importe :

- **Cousins** : Alex, Catherine, Fabien, Flobert, Jessica (hors groupe), Josiane, Nadine, Patrice (admin), Stéphane (hors groupe). Un membre du groupe qui ne danse pas n'est pas importé comme danseur.
- **Chansons** (statut d'`etat.json`) : Celebration of Varisu (proposée, 8 danseurs, 2 min 48 et 140 BPM d'après le brief et `recherches/` ; film Varisu, 2023) ; Yaaro Yaarodi (proposée, Josiane, Catherine, Jessica, à créer, 5 min 47, [131 BPM d'après musicstax, non vérifié] ; film Alaipayuthey) ; Thalapathy Kacheri (proposée, Fabien, Flobert, Alex, Patrice en attente, 3 min 17, [≈ 142 BPM non vérifié] ; film Jana Nayagan, 2026) ; Innisai (décidée, Nadine, solo, à créer, Nadine coupe la musique, durée et tempo [inconnus : ≈ 97 BPM non vérifié], film [Varalaru d'après le brief / Godfather d'après le titre d'un lien : à confirmer]) ; Kanimaa (proposée, 8 danseurs, 4 min 04, [≈ 145 BPM non vérifié] ; film Retro, 2025) ; Maamadura, The Wedding Song, Sangeeth Celebration (proposées, sans danseur : encart « proposées, pas tranchées »). Les BPM non vérifiés sont importés avec `bpm_verifie = 0` et affichés « tempo à confirmer » ; l'ordre de passage reste NULL.
- **Liens** : ceux d'`etat.json` (Yaaro Yaarodi : VrindhArjun ; Innisai : visualiseur ; Kanimaa : Ahinth Vijay ft. The Officials et AnuragerZ Choreo ; Maamadura ×2 ; The Wedding Song ; Sangeeth Celebration), type `reference`, `partage_par` et date d'`etat.json`. Les tutoriels de `recherches/chansons-et-tutoriels-2026-10-12.md` sont importés avec le type `tutoriel`, leur durée et la mention miroir quand le document la donne (Mahir Shanz, Kala Samaya), jamais d'autre durée.
- **Sections et placements** : aucun (les sections de la maquette sont des hypothèses) ; la page d'une chanson affiche « Les sections ne sont pas encore posées. Patrice peut les créer dans l'administration » et le bouton Boucle désactivé.
- **Répétitions** : celles d'`etat.json` (date, lieu, statut `decidee` ou `proposee`, note), avec les réponses importées (source WhatsApp) : `oui` pour qui a dit venir, `peut_etre` pour les incertains et les votes « dispo » du sondage. Une répétition passée garde `statut` `decidee` ; Patrice la passe en `passee` avec les présents constatés et le compte rendu quand il les connaît. Le lien du sondage WhatsApp n'étant pas une URL, `lien_sondage` reste vide : « Proposer des dates » affiche l'encart de repli.
- **Jour J** : check-list initiale tirée du brief : « Tenue », « Enceinte », « Musiques coupées à la bonne durée » (modifiables) ; date du mariage [inconnue].
- **Réglages** : `jeton_hash` créé au premier `scripts/nouveau-lien.php` ; tout le reste vide ou entre crochets.
- **Questions ouvertes** d'`etat.json` : affichées telles quelles sur `/admin` (pense-bête de Patrice), jamais sur les pages des cousins.

## 6. Accès : lien secret + prénom, sans compte

- **Un seul lien** `https://[hôte][BASE_PATH]/e/{jeton}` ; `jeton` = 32 octets aléatoires (`random_bytes`) en base64url (43 caractères). La base ne garde que `jeton_hash = SHA-256(jeton)` et `jeton_version`.
- **Cookie d'accès** `cm_acces` = `version.HMAC-SHA256(secret, version)`, `HttpOnly`, `Secure` (hors Docker en HTTP local), `SameSite=Lax`, `Path=BASE_PATH`, 400 jours ; vérifié à chaque requête en temps constant (`hash_equals`). Renouveler le lien incrémente `jeton_version` : tous les cookies deviennent invalides aussitôt, chacun doit recliquer le nouveau lien. `secret` vit dans `config.php`.
- **Prénom** : cookie `cm_prenom` = `id.HMAC(secret, id:version)` (lié à `jeton_version` : renouveler le lien fait rechoisir le prénom), mêmes attributs, 400 jours ; choisi sur `/`, changeable par « Changer ». Aucun mot de passe, aucun compte : tout porteur du lien est un cousin, et chaque action porte le prénom choisi (confiance familiale, journalisée). Ce choix est celui de `CLAUDE.md` ; il implique qu'un cousin peut, par erreur ou par jeu, agir sous un autre prénom : le journal et Patrice tranchent.
- **Administration** (Patrice seulement) : parce que n'importe qui peut choisir « Patrice », les routes `/admin` exigent en plus un cookie `cm_admin` posé une fois par appareil en visitant `/admin/cle/{cle_admin}` ; `cle_admin` (32 octets) vit dans `config.php` et peut être changée à la main. Ce n'est pas un compte ni un mot de passe par personne : une seule clé, pour une seule personne. Les pages admin répondent 404 (pas 403) à qui n'a pas le cookie.
- **Sortie** : pas de déconnexion globale ; « Changer » efface le prénom, et renouveler le lien efface tout pour tout le monde.

## 7. Téléversement des vidéos

- **Qui** : tout cousin pour ses vidéos (`type = cousin`) ; Patrice pour les vidéos de référence (`type = reference`, une par chanson, remplaçable).
- **Formats acceptés** : `video/mp4`, `video/quicktime` (`.mov`), `video/webm` ; extension et type MIME vérifiés, puis signature du fichier (`ftyp` dans les 12 premiers octets pour MP4/MOV, `1A 45 DF A3` pour WebM) ; tout autre fichier est refusé en toutes lettres. Nom interne aléatoire, jamais le nom d'origine.
- **Taille maximale** : `TAILLE_MAX_UPLOAD_MO` dans `config.php`, par défaut 200 Mo [à confirmer : `upload_max_filesize` et `post_max_size` du compte Hostinger, modifiables dans hPanel, et durée d'envoi acceptable en 4G] ; affichée sur le formulaire (« 200 Mo au plus, soit environ 3 minutes en 720p ») ; refus propre si dépassée (`UPLOAD_ERR_INI_SIZE`, `UPLOAD_ERR_FORM_SIZE`), avant tout traitement.
- **Durée conseillée** : une section ou deux, pas la chanson entière ; le formulaire le dit.
- **HEVC** : les iPhone filment en HEVC par défaut (« Haute efficacité »), illisible sur beaucoup d'Android et de PC. Le serveur devine le codec en cherchant les atomes `hvc1`/`hev1` (HEVC) ou `avc1` (H.264) dans les 4 premiers et les 4 derniers Mo du fichier (le `moov` peut être à la fin) ; `codec_devine` est enregistré. Si HEVC ou inconnu : message à l'auteur « Ta vidéo est peut-être en HEVC : sur iPhone, règle Réglages › Appareil photo › Formats › Le plus compatible pour les prochaines. Si les autres ne peuvent pas la lire, Patrice la convertira. » ; sur la vidéo, un mot « format iPhone » ; dans `/admin/videos`, bouton « Remplacer par une version convertie ».
- **Aucune conversion sur le serveur** (pas de ffmpeg sur le mutualisé, et le CPU est partagé). Conversion des vidéos de référence et des HEVC sur le PC de Patrice :

```
ffmpeg -i entree.mov -c:v libx264 -preset medium -crf 23 -vf "scale=-2:720" -pix_fmt yuv420p -movflags +faststart -c:a aac -b:a 128k sortie.mp4
```

  (`+faststart` place l'index au début : lecture et positionnement immédiats ; `yuv420p` pour Safari ; miroir à la conversion si besoin : ajouter `,hflip` au filtre). Procédure dans `docs/VIDEOS.md` (phase 5).
- **Progression** : avec JavaScript, `XMLHttpRequest` et `upload.onprogress` (pourcentage, Mo envoyés, bouton Annuler) ; sans JavaScript, formulaire classique. `max_execution_time` ne compte pas pendant la réception du fichier ; `max_input_time` et `memory_limit` n'entrent pas en jeu (le fichier est déplacé par `move_uploaded_file`, jamais lu en mémoire).
- **Limites de débit** : 5 vidéos par heure et 20 par jour par prénom, 10 par heure par IP hachée ; 30 commentaires par heure par prénom.
- **Suppression** : par l'auteur ou Patrice ; le fichier est effacé du disque ; les commentaires restent liés à une ligne marquée supprimée (invisibles).
- **Quota disque** : `/admin` affiche l'espace utilisé ; `ESPACE_MAX_MO` dans `config.php` (défaut 5 000 Mo [à confirmer selon l'offre Hostinger]) ; au-delà, l'upload est refusé avec « Plus de place : demande à Patrice de faire du ménage ».

## 8. PWA

- **Manifeste** `manifest.webmanifest` : `name` « Chorés du mariage », `short_name` « Chorés », `start_url` `BASE_PATH/moi`, `scope` `BASE_PATH/`, `display: standalone`, `lang: fr`, `theme_color` #8a1538, `background_color` #fffaf3, icônes 192, 512 (`purpose: any maskable`) et `apple-touch-icon` 180 : aplats grenat avec le chiffre « 8 » en Yeseva One, dessinés par `scripts/icones.php` (GD + la police locale) — aucune image générée par IA.
- **Service worker** `sw.js` (périmètre `BASE_PATH/`) :
  - précache de l'interface à l'installation : CSS, JS, polices, icônes, `/hors-ligne` ; versionnée par un hash dans les noms de fichiers et une constante `VERSION` ; activation immédiate (`skipWaiting` + `clients.claim`), message « Nouvelle version : recharge » en bas de page ;
  - pages HTML : réseau d'abord, repli sur la dernière copie en cache de la même URL, sinon `/hors-ligne` ;
  - ressources statiques : cache d'abord ;
  - `POST` : jamais interceptés ;
  - vidéos (`/m/{id}`) : jamais mises en cache automatiquement. Bouton « Garder sur mon téléphone » sur la page de la chanson (et par vidéo de cousin) : le service worker télécharge le fichier entier (`fetch` sans `Range`, barre de progression par `ReadableStream`), le range dans le cache `videos-v1` ; les requêtes `Range` du `<video>` sont servies depuis le cache par découpage (`Response` 206 construite à partir du `Blob`). Bouton « Retirer de mon téléphone ». Liste des vidéos gardées sur `/hors-ligne` et `/moi`.
- **Quotas** : `navigator.storage.estimate()` avant tout téléchargement ; refus en toutes lettres si l'espace manque. Safari : environ 1 Go par origine, cache effacé après 7 jours sans visite pour un site non installé, `navigator.storage.persist()` demandé après le premier « Garder » ; l'écran conseille « Ajoute le site à ton écran d'accueil pour que tes vidéos restent » avec les gestes iPhone et Android.
- **Thème** : `prefers-color-scheme` par défaut, bascule « Mode sombre / Mode clair » mémorisée en cookie (`cm_theme`, pour un rendu serveur sans éclair) et appliquée par `data-theme` sur `<html>` ; le mode répétition est toujours sombre.
- Sans service worker (navigateur ancien, navigation privée) : tout fonctionne en ligne ; les boutons « Garder » sont absents.

## 9. Visuel : jetons et composants (depuis la maquette, corrigés)

- Jetons de `00-lecture-maquette.md` § 2 à 5 repris en variables CSS dans `public/css/jetons.css` : `--fond`, `--surface`, `--surface-2`, `--texte`, `--secondaire`, `--grenat`, `--sur-grenat`, `--or`, `--sur-or`, `--filet`, `--vert`, `--orange`, `--focus` ; redéfinis sous `[data-theme="sombre"]` et `@media (prefers-color-scheme: dark)` ; `--police-titre: 'Yeseva One'`, `--police-texte: 'Nunito Sans'` ; tailles nommées (`--t-entree: 42px`, `--t-salut: 38px`, `--t-ecran: 34px`, `--t-chanson: 32px`, `--t-lecteur: 28px`, `--t-h2: 24px`, `--t-barre: 20px`, `--t-corps: 17px`, `--t-petit: 15px`, `--t-mini: 14px`, `--t-decompte: 120px`, `--t-decompte-plein: 260px`) ; hauteurs `--h-44` à `--h-72` ; rayons `--r-6` à `--r-24` ; espacements `--e-4` à `--e-40`.
- **Corrections décidées** (écarts du § 10-11 de la lecture) : en sombre, le texte et le contour des boutons « contour » utilisent `--texte` (#fff7ec), `--grenat` ne sert qu'aux surfaces ; l'orange de « Choré à créer » est assombri en clair jusqu'à ≥ 4,5:1 sur `--surface` (valeur mesurée par `scripts/contraste.php` en phase 3, consignée dans PROGRESS ; #b4530a reste la couleur de contour) ; aucun texte sous 14 px : les prénoms des points de scène passent à 14 px (abrégés à trois lettres dans les petits points, prénom entier dans le `title` et la liste textuelle), « Public » à 14 px, l'état « fait / à faire » à 14 px ; « BPM » devient « temps par minute » dans l'interface (« 140 tpm » ou « tempo 140 ») ; « Stop » devient « Arrêter » ; le titre de tutoriel anglais est remplacé par le titre réel de `recherches/`.
- **Polices auto-hébergées** : Yeseva One (OFL) et Nunito Sans (OFL, 400/700/800 ou variable) en woff2 latin et latin-ext depuis les paquets `@fontsource`, licences recopiées dans `public/polices/LICENCES.md`, `font-display: swap`, budget 120 Ko.
- **CSP sans `unsafe-inline`** : aucun attribut `style`, aucun `<script>` inline, aucun `onclick`. Les positions des points de scène ne passent donc pas par un attribut `style` : sans JavaScript, la scène est une grille CSS de 21 × 21 cellules et chaque point reçoit des classes `col-5 lig-7` (pas de 5 %) générées par le gabarit ; avec JavaScript, `element.style` reste permis par la CSP (seul l'attribut HTML inline est interdit) et sert aux déplacements fins. Même règle pour la barre de progression du lecteur et des cartes (classes par tranche de 5 % sans JS, `element.style` avec).
- Composants (un gabarit PHP par composant dans `app/vues/composants/`, un bloc CSS par composant, convention BEM en français : `.lecteur`, `.lecteur__commande`, `.lecteur--miroir`) : barre haute, bouton prénom, carte répétition, carte chanson, encart (variantes : pointillé, orange, attente), fil, lecteur et commandes, ligne de section, mini-scène, grille de maîtrise (vraie `<table>` avec `<th scope>`), lien tutoriel, carte vidéo, commentaire horodaté, bannière hors ligne, mode répétition, scène de placements, bouton de présence, frise Jour J, ligne de check-list, bascule de thème, décompte, formulaire (champ, select, fichier, erreur), message de confirmation.

## 10. Hébergement : Hostinger mutualisé

- **Emplacement** : sous-dossier `https://[domaine]/chores/` (défaut, `BASE_PATH=/chores`) ou sous-domaine `https://chores.[domaine]/` (`BASE_PATH=` vide) [à choisir par Patrice]. PHP 8.3 sélectionné dans hPanel ; extensions requises : `pdo_sqlite`, `sqlite3`, `mbstring`, `fileinfo`, `gd` (icônes seulement, facultatif) [à vérifier dans hPanel › PHP › Extensions].
- **Fichiers** : `public/` est copié dans la racine web (`public_html/chores/` ou la racine du sous-domaine) ; `app/`, `scripts/`, `config.php` et `stockage/` sont copiés **hors racine web**, dans `domains/[domaine]/chores-app/` (accessible dans le gestionnaire de fichiers au-dessus de `public_html`) ; `public/chemin-app.php` contient `return '/home/[compte]/domains/[domaine]/chores-app/app';`. Si l'hébergeur n'autorise pas d'écriture hors de `public_html` [à vérifier], repli : `chores-app/` placé dans `public_html/chores-app/` avec un `.htaccess` `Require all denied` — et PROGRESS le dit.
- **`.htaccess` de `public/`** : `RewriteEngine On`, tout vers `index.php` sauf les fichiers existants ; `Options -Indexes` ; `Header always set X-Robots-Tag "noindex, nofollow, noarchive"` ; `AddType` pour `.webmanifest` et `.woff2` ; `Header set Cache-Control "public, max-age=31536000, immutable"` sur `css/`, `js/`, `polices/`, `icones/` (noms hachés) ; `sw.js` en `no-cache` ; interdiction d'accès à `*.sqlite`, `*.php` hors `index.php`, `chemin-app.php`, `.git`.
- **En-têtes envoyés par PHP sur chaque réponse** (`app/securite.php`) : `Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self'; font-src 'self'; connect-src 'self'; manifest-src 'self'; worker-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'; object-src 'none'` (+ `upgrade-insecure-requests` en production) ; `Strict-Transport-Security: max-age=31536000` (production, après vérification du certificat Hostinger) ; `X-Content-Type-Options: nosniff` ; `Referrer-Policy: no-referrer` ; `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()` ; `Cross-Origin-Opener-Policy: same-origin` ; `X-Robots-Tag` ; `<meta name="robots" content="noindex, nofollow">` dans chaque page ; `robots.txt` `Disallow: /`.
- **Déploiement** : `scripts/paquet.php` construit `dist/chores-mariage-vX.Y.Z.zip` (sans `stockage/`, `config.php`, `tests/`, `node_modules/`, `vendor/` de dev, `docs/`) ; procédure humaine dans `docs/deploy/README.md` (phase 6) : hPanel › Fichiers › envoyer et décompresser, créer `config.php` depuis `config.example.php`, créer `stockage/` (droits 750), exécuter `php scripts/migrer.php` puis `php scripts/importer-etat.php` puis `php scripts/nouveau-lien.php` en SSH (ou, sans SSH, par la page `/installer` : base neuve seulement, clé d'administration tapée dans un champ et jamais dans l'adresse, 404 dès qu'un lien existe ; phase 6), déposer les vidéos de référence converties, vérifier la liste de `docs/deploy/verification.md`. Jamais de déploiement par l'agent.
- **Sauvegardes** : `scripts/sauvegarde.php` (ou `/admin/base`, phase 6) fait `VACUUM INTO 'stockage/sauvegardes/base-AAAA-MM-JJ-HHMMSS.sqlite'` ; restauration par `scripts/restaurer.php` ou `/admin/base`, sous un témoin de maintenance, en gardant le lien en service ; Patrice télécharge `stockage/` (base + médias) par le gestionnaire de fichiers une fois par mois et avant le mariage ; procédure de restauration testée en local (phase 6).

## 11. Sécurité et vie privée

- Rien ne sort du serveur : aucune ressource externe, aucun traceur, aucun e-mail (le site n'envoie rien ; les nouvelles se lisent sur Ma page). Les vidéos des cousins ne sont jamais copiées vers un tiers ; `stockage/` et `medias/` sont ignorés par git ; les captures d'écran de recette utilisent des vidéos de test synthétiques.
- Lien secret long (256 bits), stocké haché, révocable en un clic ; jamais affiché hors `/admin/lien` ; jamais dans un referrer (`no-referrer`) ni dans l'historique après l'entrée (redirection).
- Pas de CAPTCHA ; à la place, limitation de débit (table `debit`) : `/e/{jeton}` 10 essais par minute et 60 par heure par IP hachée (puis 429 avec « Réessaie dans une minute ») ; `POST /prenom` 20 par heure ; téléversement et commentaires (§ 7) ; niveaux et présences 300 par heure par prénom. Les refus sont journalisés sans IP.
- Journal minimal (§ 3) : type, prénom, détail court, horodatage ; jamais d'IP en clair, jamais de user-agent ; purge à un an. `debit` ne garde que des hachés salés par jour.
- Entrées : toute valeur validée côté serveur (longueurs, ensembles fermés, nombres bornés), échappée à l'affichage (`htmlspecialchars` systématique par une fonction `e()`), requêtes préparées partout ; jeton CSRF par formulaire (`cm_csrf` en cookie + champ caché, double soumission signée) ; `SameSite=Lax` ; les réponses JSON portent `Content-Type: application/json; charset=utf-8` et `X-Content-Type-Options`.
- Fichiers : noms aléatoires, hors racine web, servis par PHP avec le type MIME de la base (jamais deviné à la volée), `Content-Disposition: inline`, `Accept-Ranges`. Aucun `exec`, aucun ffmpeg serveur.
- Secrets : `config.php` ignoré (`SECRET`, `CLE_ADMIN`, `BASE_PATH`, `TAILLE_MAX_UPLOAD_MO`, `ESPACE_MAX_MO`, `ENV`), `config.example.php` versionné avec des valeurs vides ; l'agent ne lit ni n'écrit `config.php` en production.
- Droit : répétitions et site privé entre cousins relèvent du cercle de famille (art. L122-5 1° du CPI) ; le site ne republie rien ailleurs ; les tutoriels restent chez YouTube.
- Revue `/security-review` aux phases 2, 5 et 6.

## 12. Accessibilité (WCAG 2.2 AA)

- Contrastes ≥ 4,5:1 sur tout texte (vérifiés par `scripts/contraste.php` sur toutes les paires autorisées, dans les deux thèmes et en mode répétition), ≥ 3:1 sur les contours et icônes ; aucun état porté par la couleur seule (« Miroir — activé », « Je maîtrise », « Présents : », ✓ ≈ · doublés d'un mot visible ou lu).
- Cibles 44 × 44 px au minimum (2.5.8), 48 courantes, 56 au lecteur, 72 en répétition ; espacement 8 px entre cibles voisines.
- Clavier : tout bouton est un `<button>`, tout lien un `<a>` ; focus visible 3 px or (2.4.11 : jamais masqué par la barre haute collante, `scroll-margin-top`) ; ordre logique ; raccourcis du lecteur documentés et désactivables (« Raccourcis clavier » dans la page) ; aucun glisser-déposer (placements par flèches et champs) ; aucun piège.
- Structure : `lang="fr"`, un `h1` par page, `<main>`, `<nav aria-label>`, lien d'évitement, `title` « Kanimaa — Chorés du mariage » ; grille « Qui maîtrise quoi » en `<table>` avec `<caption>` et `<th scope>` ; points de scène doublés d'une liste textuelle (« Jessica : côté cour, devant ») ; `<video>` avec `aria-label` et transcription de la structure (sections, comptes) en texte ; les vidéos n'ont pas de parole (musique) : pas de sous-titres requis, l'absence est dite.
- Formulaires : `<label>` visibles, `autocomplete` pertinent, erreurs en toutes lettres en haut et au champ (`aria-describedby`), aucune ressaisie (3.3.7), aucune validation par couleur seule ; messages de confirmation en page (`role="status"`).
- Mouvement : seul le décompte bouge ; `prefers-reduced-motion` supprime la mise à l'échelle ; aucune lecture automatique sans geste ; `aria-live="polite"` pour « Décompte : 7 » et pour les confirmations.
- Zoom 200 % et 400 % sans défilement horizontal (la grille de maîtrise défile dans son propre conteneur, `tabindex="0"` et `role="region"` nommé) ; texte redimensionnable ; `font-size` en px dans les jetons mais `rem` dans les composants.
- Aide (3.2.6) au même endroit sur chaque page : pied « Un souci ? Écris à Patrice » [moyen de contact à préciser].
- Tests : axe sur chaque page dans les deux thèmes (phases 3 à 6), liste manuelle dans `docs/RECETTE.md`, passes VoiceOver iPhone et TalkBack Android listées pour Patrice (phase 6).

## 13. Performance

- Page HTML ≤ 60 Ko, CSS ≤ 40 Ko, JS ≤ 40 Ko (lecteur compris), polices ≤ 120 Ko, aucune image en dehors des icônes ; vidéos chargées en `preload="metadata"` seulement ; Lighthouse mobile ≥ 90 performance, ≥ 95 accessibilité sur `/moi`, `/chanson/{slug}`, `/placements/{slug}`, `/videos`, `/jour-j`.

## 14. Tests et qualité

- `composer check` (dans le conteneur) : `php -l` sur tout, PHPUnit (`tests/phpunit/` : jeton et cookies, limitation de débit, validation des sections, import idempotent, en-têtes, contrôle d'accès aux médias, détection HEVC sur des fixtures de quelques octets), `scripts/contraste.php`, `scripts/lint-inline.php` (aucun `style=`, `<script>` inline ni `on*=` dans `app/vues/`).
- `pnpm test:e2e` (hôte) : Playwright contre http://127.0.0.1:8095, projets mobile 390 × 844 et bureau 1 280 × 900, `@axe-core/playwright` sur chaque page, thèmes clair et sombre, `prefers-reduced-motion`, zoom 200 %, interception réseau qui échoue sur toute requête hors `127.0.0.1:8095` (preuve que rien ne sort), parcours : lien → prénom → Ma page → chanson → section → niveau → grille → répétition plein écran → décompte → retour ; présence ; téléversement d'une vidéo de test (WebM fabriquée par `canvas` + `MediaRecorder` dans le navigateur de test, aucun binaire versionné) ; commentaire horodaté ; service worker (page en cache, `/hors-ligne`). `pnpm check` = `composer check` + `pnpm test:e2e`.
- Node (hôte) : `node --test tests/js/` pour les fonctions pures du lecteur (cadence du décompte, boucle, formats `0:17` et `4 min 04`).
- Captures des écrans modifiés en 390 et 1 280 px dans `docs/captures/` à chaque phase.

## 15. Hors périmètre

Compte ou mot de passe par personne ; messagerie ; notifications push ; conversion vidéo serveur ; lecture YouTube dans la page ; sondage de dates intégré (lien externe, Framadate conseillé dans `recherches/`) [à décider par Patrice] ; édition des sections par d'autres que Patrice [à décider] ; multi-mariage.
