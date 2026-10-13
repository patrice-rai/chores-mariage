# Plan — Phase 5 : répétitions, vidéos des cousins, Jour J, PWA

Écrit le 13 octobre 2026, avant tout code (mode autonome : ce plan remplace le mode plan ; les décisions prises sans
validation sont recopiées dans `docs/PROGRESS.md`). Sources : `docs/prompts/phase-05.md`, spec § 2, 3, 4, 7, 8, 11,
12, maquette 1n, 1o, 1p, 1q (lue dans le prototype : cartes de répétition, album de vidéos, frise, check-list).

## 0. Ce qui existe déjà (phases 2 à 4)

Tables `repetitions`, `presences` (avec `present_constate`), `videos`, `commentaires`, `checklist` ; composants
`presence`, `carte-repetition`, `carte-video`, `commentaire`, `frise`, `ligne-checklist`, `banniere-hors-ligne`,
`nouvelle-version` ; `controler_video()`, `deviner_codec()`, `ranger_video()`, `servir_fichier()` (206/416),
`/m/{id}` ; débit `debit_autorise()` (fenêtres minute, heure, jour) ; base de test séparée pour Playwright.

## 1. Migration `003-groupe.sql` (aucun DROP, aucune table modifiée)

- `constats (repetition_id, cousin_id, present 0/1)` : présents et absents constatés par Patrice, **distincts des
  réponses** (un cousin qui n'a pas répondu peut avoir été présent ; la colonne `presences.present_constate` exigerait
  une réponse inventée). La colonne reste inutilisée.
- Index `commentaires (video_id, temps_s)`, `videos (type, supprime_le, cree_le)`, `presences (source, maj_le)`.
- `version_schema` = 3.

## 2. Répétitions

- `GET /repetitions` : « Proposer des dates » (lien `lien_sondage`, sinon encart de repli déjà écrit) ; **à venir**
  (date ≥ aujourd'hui, statut ≠ passée ; décidées puis proposées puis annulées, par date) avec Je viens / Je ne peux pas
  sur les décidées et les proposées, « Ta réponse : je viens. », qui a répondu en toutes lettres, « Personne n'a
  encore répondu. Sois la première ou le premier. » ; une annulée à venir dit « Annulée » sans boutons ; **passées** :
  présents constatés (mot « Présents : » en vert), absents, « Pas noté » ; sans aucun constat, les réponses
  (« Avaient dit venir », « Sans réponse ») ; compte rendu ou « Pas encore de compte rendu ».
- `POST /repetitions/{id}/presence` : `reponse` ∈ {oui, non} ; refus 409 en toutes lettres si la répétition est
  passée ou annulée ; idempotent (upsert, journal seulement si la réponse change) ; source `site` (remplace un
  « peut-être » importé du sondage) ; débit « écriture » (300/h par prénom) ; champ `retour` ∈ {moi, repetitions}
  → redirection 303 avec confirmation `role="status"` (« C'est noté : tu viens samedi [date]. »).
- Ma page : la carte reçoit les deux boutons (répétition décidée, et option en tête du sondage).
- « Ce qui bouge » : présences données sur le site (`source = site`), vidéos, commentaires, niveaux « je maîtrise »,
  10 derniers.
- Admin `/admin/repetitions` (liste, ajout : date, heure, lieu, statut, on travaille, note) et
  `/admin/repetitions/{id}` (mêmes champs + compte rendu + un menu par cousin « pas noté / présent / absent »,
  réponses rappelées à côté). Date en double refusée en toutes lettres. Pas de suppression (statut « annulée »).

## 3. Vidéos des cousins (spec § 7)

- `GET /videos` : « Ajouter ma vidéo » (56 px grenat), « Ces vidéos restent entre nous. », un album par chanson
  (ordre de passage puis titre ; « 2 vidéos »), cartes vidéo (vignette en aplat, auteur · titre, date, note,
  « format iPhone », commentaires horodatés). État vide écrit.
- `GET /videos/ajouter[?chanson=slug]` : chanson (select, chansons non écartées), titre (80, obligatoire), note (300),
  « Filmée en miroir », fichier (`accept="video/mp4,video/quicktime,video/webm"`, `MAX_FILE_SIZE`), limites écrites
  (« 200 Mo au plus, soit environ 3 minutes en 720p »), conseil « une section ou deux », rappel iPhone
  « Le plus compatible ». Bouton « Envoyer » 56 px.
- `POST /videos` : contrôle des champs puis `controler_video()` (extension + MIME + signature ; `UPLOAD_ERR_INI_SIZE`,
  `UPLOAD_ERR_FORM_SIZE`, `UPLOAD_ERR_PARTIAL` en toutes lettres), espace `ESPACE_MAX_MO` (« Plus de place : demande
  à Patrice de faire du ménage »), débit compté **sur les envois valides** seulement (5/h et 20/j par prénom, 10/h
  par IP hachée : un fichier refusé ne fait pas perdre un essai), nom aléatoire, `move_uploaded_file`, journal.
  HEVC ou codec inconnu : accepté, avertissement à l'auteur sur la page de la vidéo, mot « format iPhone » partout.
  Erreur → formulaire rendu de nouveau (422), valeurs gardées, phrase « choisis de nouveau le fichier » (un navigateur
  ne garde jamais un fichier). Avec `Accept: application/json` : `{url}` ou `{erreurs}`.
- Progression JavaScript (`public/js/televersement.js`) : `XMLHttpRequest`, `upload.onprogress`, barre `<progress>`
  et texte « 42 % · 31 Mo sur 74 Mo », Annuler (`abort` → « Envoi annulé : rien n'a été gardé. »), contrôle de taille
  avant l'envoi, 413 et réseau coupé en toutes lettres, erreurs du serveur affichées dans le résumé.
- `GET /videos/{id}[?t=secondes]` : lecteur simple (`<video controls>`, `#t=` sans JavaScript ; avec JavaScript
  vitesses 0,5/0,75/1 et Miroir, état écrit), miroir d'office si « filmée en miroir » est coché (dit en toutes
  lettres), commentaires triés par instant (deux au même instant : par date d'écriture), « à 0:03 » positionne la vidéo,
  formulaire « à {temps} » + « Prendre la position » + texte 200, suppression de la vidéo et des commentaires par
  l'auteur ou par Patrice (cookie d'administration, car n'importe qui peut choisir « Patrice ») avec confirmation en
  page (`?supprimer=1`, `?supprimer-commentaire=N` → bouton POST + « Non, garder »).
- `POST /videos/{id}/commentaires` (temps facultatif `m:ss`, texte 1-200, 30/h par prénom), `POST
  /videos/{id}/commentaires/{c}/supprimer`, `POST /videos/{id}/supprimer` (fichier effacé, ligne marquée
  `supprime_le`), `POST /videos/{id}/duree` (auteur seulement, une fois, 0 à 36 000 s, JSON).
- Adresse du fichier : `/m/{id}?v={8 premiers caractères du nom interne}` : une vidéo **remplacée** par Patrice garde
  sa page et ses commentaires, et les caches (navigateur, service worker) ne servent jamais l'ancienne.
- Admin `/admin/videos` : toutes les vidéos (cousins et références), taille, codec, espace ; Supprimer (confirmation
  en page) ; « Remplacer par une version convertie » (MP4 ou WebM, HEVC refusé, même ligne, nouveau fichier).
- Page d'une chanson : section « Vidéos des cousins » alimentée + « Ajouter ma vidéo » (chanson présélectionnée).
- `docs/VIDEOS.md` : réglage iPhone, commande ffmpeg (+faststart, `hflip`), poids attendus, remplacement.

## 4. Jour J

- `GET /jour-j` : « Mariage le [date] » (réglage `date_mariage`, `[lieu]`), « Mode scène — placements en grand »
  (`/placements/{première chanson à plusieurs de l'ordre}?scene=1&depuis=jour-j`, Quitter → `/jour-j`), frise des
  chansons qui ont un `ordre_passage` (durée coupée si connue, sinon « durée entière 4 min 04 » ou « [durée
  inconnue] » ; « [à compléter] »), ou « L'ordre de passage n'est pas encore fixé » ; check-list partagée.
- `POST /jour-j/check/{id}` : `fait` ∈ {0, 1} (état voulu, pas une bascule : deux cousins qui cochent en même temps
  ne s'annulent pas), journal `check`, débit « écriture ».
- Admin `/admin/jour-j` : ajouter, renommer, monter, descendre, supprimer (confirmation en page) ; liens vers les fiches
  des chansons pour l'ordre et « qui entre ».

## 5. PWA (spec § 8)

- `public/manifest.webmanifest` statique, adresses **relatives** (`start_url: "moi"`, `scope: "./"`) : juste quel que
  soit `BASE_PATH` ; couleurs = jetons (test PHPUnit). Icônes 192, 512 (`any maskable`, chiffre dans la zone sûre) et
  180 par `scripts/icones.php` (GD + FreeType, la woff2 Yeseva One locale, couleurs lues dans `jetons.css`).
- `GET /sw.js` servi par PHP (`app/sw.js` comme modèle) : `VERSION` = empreinte des fichiers précachés, liste exacte
  des adresses `?v=` de l'interface + `/hors-ligne`, `Cache-Control: no-cache`. Stratégies : `POST` jamais touchés ;
  navigation réseau d'abord → copie de la même adresse → `/hors-ligne` ; statiques cache d'abord ; `/m/{id}` : cache
  `videos-v1` si la vidéo est gardée (réponses 206 découpées dans le `Blob`), sinon réseau sans rien garder ;
  `/admin`, `/api`, `/e/` jamais gardés. Une navigation qui aboutit sur `/lien` (lien renouvelé) ou sur l'entrée
  (prénom changé) **efface les pages gardées** (et `/lien` efface aussi les vidéos).
- `public/js/pwa.js` : « Garder sur mon téléphone » / « Retirer de mon téléphone » (page d'une chanson et chaque
  vidéo de cousin) : `storage.estimate()` avant (refus « Pas assez de place sur ton téléphone : il faut 74 Mo, il en
  reste 20. »), téléchargement entier par `ReadableStream` avec progression, `persist()` après le premier, page
  gardée avec ; conseil « Ajoute le site à ton écran d'accueil » (gestes iPhone et Android). Boutons absents sans
  service worker (rendus `hidden`, révélés seulement quand un service worker contrôle la page).
- `GET /hors-ligne` (public, précachée) : texte, liste des vidéos gardées (lue dans Cache Storage par JS) ; sans JS ou
  sans rien de gardé : phrase écrite.
- File des niveaux hors ligne (`localStorage`, rejouée à `online` et au chargement, « 2 niveaux à envoyer »).
- Encart « Nouvelle version : recharge » à `controllerchange` quand une version précédente contrôlait la page.

## 6. Cas limites

| Cas | Comportement |
| --- | --- |
| Fichier trop gros (`upload_max_filesize`, `MAX_FILE_SIZE`, au-delà de `post_max_size`) | « La vidéo est trop lourde : 200 Mo au plus. » / page 413 ; avec JS, refus avant l'envoi |
| Mauvais format, `.txt` renommé `.mp4` | « Ce fichier n'est pas une vidéo acceptée : envoie un fichier MP4, MOV, WEBM. » |
| HEVC | Accepté, avertissement iPhone, « format iPhone », Patrice remplace par une version convertie |
| Upload interrompu (`UPLOAD_ERR_PARTIAL`, réseau coupé, Annuler) | Rien n'est gardé ; message en toutes lettres |
| Deux commentaires au même instant | Les deux sont gardés, triés par instant puis par date d'écriture |
| Répétition annulée | Ni boutons ni réponse acceptée (409) ; « Annulée » écrit |
| Quota Safari dépassé / `QuotaExceededError` | Refus avant ; si `cache.put` échoue : « Pas assez de place… », rien de gardé à moitié |
| Service worker périmé | `VERSION` change avec les fichiers ; anciens caches effacés à l'activation ; encart « Nouvelle version » |
| Lien renouvelé / prénom changé | Pages gardées effacées par le service worker |
| Vidéo supprimée pendant qu'une page est ouverte | `/m/{id}` 404, page de la vidéo 404, commentaires invisibles |

## 7. Tests

- PHPUnit : signatures, `.txt` renommé, HEVC/H.264 sur fixtures, quota, débit des téléversements (6ᵉ refusé), refus
  sans compter, suppression qui efface le fichier, droits de suppression (auteur, Patrice avec clé, autre cousin
  refusé), commentaires bornés et triés, présence idempotente et refusée sur une annulée, constats, check-list par
  état voulu, Jour J vide puis ordonné, `/sw.js` (version, liste, en-têtes), manifeste (jetons), `/hors-ligne` sans
  cookie, « Ce qui bouge ».
- Playwright (390 et 1 280, axe, deux thèmes) : Je viens puis Je ne peux pas ; téléversement d'un WebM fabriqué dans le
  navigateur de test par `canvas` + `MediaRecorder` avec progression, lecture, commentaire « à 0:03 » qui positionne la
  vidéo, suppression ; refus d'un texte ; Jour J sans ordre puis avec ordre posé en admin ; check-list ; service worker :
  `/moi` servie hors ligne, `/hors-ligne` pour une page inconnue, vidéo gardée lue hors ligne ; interception réseau.
- Captures `docs/captures/phase-05/`.

## 8. Revues

`securite-reviewer` (téléversement, médias, service worker, en-têtes, débit, journal), `a11y-reviewer` (pages et
composants), revue équivalente à `/security-review` (le skill exige `origin/HEAD`, dépôt distant interdit : décision 14
de la phase 2), revue de code ; corrections jusqu'à zéro bloquant.
