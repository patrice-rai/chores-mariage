# À faire par Patrice

Ce que l'agent ne peut pas faire seul : l'hébergeur, les vrais téléphones, les décisions du groupe et les
fichiers à fournir. Réécrit le 13 octobre 2026 (phase 6, version 1.0.0) dans l'ordre où le faire ; les notes des
phases 2 à 5 restent en annexe, numérotées comme avant (n° 1 à 42), pour mémoire.

Le site est prêt et vérifié en local (`docs/RECETTE.md`). Rien n'a été mis en ligne : l'agent n'a eu accès ni à
Hostinger, ni à un compte, ni à un téléphone.

## A. Mettre en ligne (une heure environ, dans l'ordre)

1. **Décider l'emplacement** : sous-dossier `https://[domaine]/chores/` (défaut) ou sous-domaine
   `https://chores.[domaine]/` (conseillé si d'autres applications tournent sur le domaine). Tableau dans
   `docs/deploy/README.md` § 0.
2. **Construire le paquet et les secrets** sur ton PC : `docs/deploy/README.md` § 1
   (`docker compose exec -T web php scripts/paquet.php`, puis la commande des deux secrets).
3. **hPanel** : PHP 8.3, extensions `pdo_sqlite`, `sqlite3`, `mbstring`, `fileinfo`, options
   `upload_max_filesize` / `post_max_size` à 256M (§ 2) ; **HTTPS actif avant tout** (§ 3).
4. **Envoyer, décompresser, ranger** : `chores-app/` hors de `public_html`, `public/` devenu `public_html/chores/`
   (§ 4 et 5 ; repli § 5 bis si hPanel refuse d'écrire au-dessus de `public_html`).
5. **Écrire `public_html/chores/chemin-app.php`** (§ 6) et **`chores-app/config.php`** depuis `config.example.php`
   (§ 7 : `SECRET`, `CLE_ADMIN`, `BASE_PATH`, `URL_PUBLIQUE`, limites, `ENV = 'production'`).
6. **Droits** : `stockage/` en 750, `config.php` en 600 (§ 8).
7. **Installer** : en SSH `php scripts/migrer.php`, `php scripts/importer-etat.php`, `php scripts/nouveau-lien.php` ;
   sans SSH, la page `https://[domaine]/chores/installer` et ta `CLE_ADMIN` (§ 9).
8. **Vérifier** : `docs/deploy/verification.md` en entier (en-têtes avec `curl.exe`, fichiers protégés, `/m/` refusé
   sans cookie, adresse IP vue par le site, une vraie vidéo envoyée puis supprimée, installation sur l'écran
   d'accueil). Note dans `docs/PROGRESS.md` toute ligne qui échoue.
9. **Sauvegardes** : programmer la tâche planifiée hebdomadaire et noter dans ton agenda le téléchargement mensuel
   de `stockage/` (`docs/deploy/sauvegardes.md`).

## B. Tester sur de vrais téléphones (après la mise en ligne)

Avec un iPhone (Safari) **et** un Android (Chrome). Pour chaque ligne, note « OK » ou ce que tu as vu dans
`docs/PROGRESS.md` (section « Tests sur téléphone »). Avant : dépose au moins une vidéo de référence convertie
(`docs/VIDEOS.md` § 4) et pose deux sections sur sa chanson (`docs/ADMIN.md` § 5).

| n° | Faire | Résultat attendu |
|---|---|---|
| B1 | Ouvrir le lien reçu (copié depuis WhatsApp ou un SMS) | L'entrée s'affiche avec les neuf prénoms ; l'adresse ne montre plus `/e/…` |
| B2 | Toucher ton prénom | « Ma page » avec « Salut [prénom] », tes chorés et la prochaine répétition |
| B3 | Ouvrir une chanson, toucher Lecture, choisir 0,5 × | La vidéo de référence se lit au ralenti, **la musique garde sa hauteur** (pas de voix grave) |
| B4 | Toucher « Miroir » | L'image est retournée gauche-droite, « Miroir activé » s'affiche ; les textes ne sont pas retournés |
| B5 | Choisir une section, « Boucle » | Arrivée à la fin de la section, la vidéo revient au début de la section, sans fin |
| B6 | Lecture avec décompte | « 5, 6, 7, 8 » au tempo, lisible à deux mètres, puis la vidéo démarre seule (sinon « Touche Lecture à nouveau » : dis-le) |
| B7 | « Mode répétition », téléphone posé 3 minutes | Écran sombre, grandes commandes ; **l'écran reste allumé** pendant la lecture ; « Plein écran » marche (Android ; sur iPhone il peut manquer) |
| B8 | Marquer une section « Presque » puis « Je maîtrise » | Le mot change, la grille « Qui maîtrise quoi » et la progression suivent |
| B9 | Placements : « Me surligner », puis ↑ ou → | Ton point est entouré et bouge d'un pas ; la liste écrite dit ta nouvelle place |
| B10 | Répétitions : « Je viens », puis « Je ne peux pas » | « Ta réponse : je viens. » puis « Ta réponse : je ne peux pas. » ; ton prénom passe d'une liste à l'autre |
| B11 | Vidéos › « Ajouter ma vidéo » › filmer avec l'appareil photo (iPhone réglé sur « Le plus compatible ») | Barre de progression « 42 % · … Mo », « Annuler » marche ; la vidéo se lit dans l'album |
| B12 | Même chose avec l'iPhone réglé sur « Haute efficacité » (HEVC) | Envoi accepté avec l'avertissement « format iPhone » ; vérifie si elle se lit sur l'Android ; puis remplace-la par la version convertie (`docs/VIDEOS.md` § 5) |
| B13 | Sur une vidéo, « Commenter » à 0:03 | Le commentaire « à 0:03 » apparaît ; le toucher remet la vidéo à 0:03 |
| B14 | Installer sur l'écran d'accueil (iPhone : Partager › « Sur l'écran d'accueil » ; Android : menu › « Installer l'application ») | Icône grenat « 8 », nom « Chorés » ; le site s'ouvre sans barre d'adresse et sans redemander le prénom (sur iPhone, l'icône peut avoir son propre stockage : retouche le lien une fois si l'entrée s'affiche) |
| B15 | Sur une chanson, « Garder sur mon téléphone », puis mode avion | La page de la chanson s'ouvre et la vidéo se lit sans réseau ; les autres pages disent « Hors ligne » |
| B16 | Mode sombre du téléphone | Le site passe en sombre ; « Mode sombre » dans la barre le force dans un sens ou dans l'autre |
| B17 | Taille du texte du système au maximum (zoom 200 %) | Rien n'est coupé, rien ne déborde sur le côté ; le décompte reste entier |
| B18 | Bouton « Choisir un fichier » de « Ajouter ma vidéo » | Libellé en français (c'est le téléphone qui l'écrit) |

## C. Lecteurs d'écran (VoiceOver sur iPhone, TalkBack sur Android)

Allumer : iPhone › Réglages › Accessibilité › VoiceOver (ou triple clic sur le bouton latéral si le raccourci est
réglé) ; Android › Paramètres › Accessibilité › TalkBack. Gestes : glisser un doigt vers la droite = élément
suivant ; toucher deux fois = activer ; iPhone, rotor à deux doigts sur « Titres » pour sauter de titre en titre.

| n° | Où | Ce qui doit être dit |
|---|---|---|
| C1 | Entrée | « Chorés du mariage, titre niveau 1 », puis chaque prénom comme « bouton » |
| C2 | Ma page | Un seul titre de niveau 1 ; « Prochaine répétition » ; chaque carte de chanson dit son état en mots (« à créer », « en attente », « 2 sections sur 5 maîtrisées », « Les sections ne sont pas encore posées ») |
| C3 | Page d'une chanson, lecteur | « Lecture, bouton », « Miroir, bouton, désactivé / activé », « 0,5 fois, bouton, sélectionné » ; le décompte annonce « Décompte : 5 » … « 8 » sans couper la lecture |
| C4 | Sections | Chaque section dit son nom, ses comptes et ton niveau (« Je maîtrise ») ; changer de niveau annonce « C’est noté : je maîtrise. » suivi de la progression |
| C5 | Grille « Qui maîtrise quoi » | Lue comme un tableau : en-têtes de colonnes (sections) et de lignes (prénoms) |
| C6 | Placements | La liste écrite des places est lue (« Catherine : au centre, au milieu ») ; les points eux-mêmes ne sont pas lus deux fois |
| C7 | Ajouter ma vidéo | La progression est annoncée (« 42 % ») ; une erreur est lue en premier |
| C8 | Bascule « Mode sombre » | Son état (« activé / désactivé ») |

Note ce qui manque ou ce qui est dit de travers dans `docs/PROGRESS.md` (section « Tests sur téléphone »).

## D. Envoyer le lien et poser les premières sections

1. **Envoyer le lien** : `docs/deploy/lien.md` (texte prêt). Jessica et Stéphane ne sont pas dans le groupe WhatsApp :
   envoie-le leur à part. Poster le lien dans le groupe le donne à tous ses membres : décide si le lien passe par
   le groupe ou en messages privés.
2. **Vidéos de référence** : convertir chaque vidéo sur ton PC (`docs/VIDEOS.md` § 4) et la déposer dans
   `/admin/chansons/[chanson]`.
3. **Sections** de chaque choré (aucune n'est posée : `docs/ADMIN.md` § 5), puis **placements** (§ 6).
4. **Réglages** (`/admin/reglages`) : date et lieu du mariage, durée accordée aux danses, contact du pied de page,
   lien du sondage de dates si vous en ouvrez un.

## E. Ce qui manque encore (à saisir quand le groupe sait)

| Quoi | Où le saisir | Aujourd'hui sur le site |
|---|---|---|
| Date et lieu du mariage | `/admin/reglages` | « [date] », « [lieu] » (Jour J, réglages) |
| Moyen de te joindre (pied de page) | `/admin/reglages` › « Comment te joindre » (visible aussi sans le lien : pas de numéro si tu ne veux pas) | « Un souci ? Écris à Patrice [contact] » sur toutes les pages |
| Film et durée d'Innisai (Varalaru ou Godfather) | `/admin/chansons/innisai` | « [film à confirmer] · [durée inconnue] » |
| Tempos à confirmer (Yaaro Yaarodi 131, Thalapathy Kacheri ≈ 142, Innisai ≈ 97, Kanimaa ≈ 145) | `/admin/chansons/[chanson]` › « Taper le tempo » puis « Tempo vérifié » | « tempo à confirmer » |
| Ordre de passage, qui entre, d'où, qui lance la musique, durées coupées | `/admin/chansons/[chanson]` | « L'ordre de passage n'est pas encore fixé » |
| Check-list du Jour J (trois lignes du brief) | `/admin/jour-j` | Tenue, Enceinte, Musiques coupées |
| Chansons proposées (Maamadura, The Wedding Song, Sangeeth Celebration) | `/admin/chansons/[chanson]` › Statut | « Proposées, pas tranchées » |
| Patrice sur Thalapathy Kacheri | `/admin/chansons/thalapathy-kacheri` › Danseurs | « en attente » |
| Accord de Jessica et Stéphane pour figurer sur le site | à noter dans `docs/PROGRESS.md` | leurs prénoms sont sur l'entrée |
| Limites réelles de l'offre (taille d'envoi, espace disque, SSH, version de SQLite) | `config.php` (`TAILLE_MAX_UPLOAD_MO`, `ESPACE_MAX_MO`) et `docs/PROGRESS.md` | 200 Mo par vidéo, 5 000 Mo au total |
| Sections et placements ouverts aux créateurs de chorés ? | `docs/PROGRESS.md` (décision) | Patrice seul |

---

## Annexe — notes des phases 2 à 5 (n° 1 à 42, gardées pour mémoire)

Les lignes ci-dessous sont reprises dans les parties A à E ; elles gardent leur numéro parce que `docs/PROGRESS.md`
y renvoie.

### Questions ouvertes (reprises de la préparation)

1. **Date et lieu du mariage**, et durée accordée aux danses. Où : `/admin/reglages` (phase 4) ; d'ici là, le site affiche « [date] » et « [lieu] ».
2. **Hébergement** : sous-domaine (`chores.[domaine]`) ou sous-dossier (`[domaine]/chores`, choisi par défaut) chez Hostinger ; le domaine lui-même ; le gestionnaire de fichiers permet-il d'écrire au-dessus de `public_html` ; le SSH est-il disponible. Où : `config.php` de production (`BASE_PATH`, `URL_PUBLIQUE`) et `docs/deploy/README.md` (phase 6).
3. **Limites du compte Hostinger** : `upload_max_filesize`, `post_max_size` (hPanel › Avancé › Configuration PHP) et espace disque libre. Défauts pris : 200 Mo par vidéo, 5 000 Mo au total. Où : `config.php` de production (`TAILLE_MAX_UPLOAD_MO`, `ESPACE_MAX_MO`).
4. **Jessica et Stéphane** (hors du groupe WhatsApp) : comment leur transmettre le lien, et leur accord pour figurer sur le site. La page « Nouveau lien » de l'administration rappelle de le leur envoyer.
5. **Pilote automatique** : confirmer la pile par défaut et la marche phase par phase (phase 2 faite en autonomie).
6. **Sections et placements** : seuls Patrice les pose (défaut) ; faut-il ouvrir aux créateurs de chorés ? Réponse à noter dans `docs/PROGRESS.md`.
7. **Lien du sondage de dates** (Framadate conseillé) ou rien. Où : `/admin/reglages` (phase 4/5).
8. **Vidéos de référence** : quelle vidéo pour chaque chanson (fichier à convertir sur ton PC, procédure en phase 5), et la bonne version d'Innisai (film Varalaru ou Godfather).

### Phase 2 (socle et accès)

9. **Moyen de contact** à afficher dans le pied de page « Un souci ? Écris à Patrice [contact] » (numéro, prénom seul, ou « sur WhatsApp »). Où : à me dire ; il sera placé dans les réglages (phase 4).
10. **Premier lien secret en local** (pour voir le site) : dans un terminal ouvert dans `P:\PROJECT\chores-mariage` :
    ```
    docker compose up -d --build
    docker compose exec -T web php scripts/nouveau-lien.php
    ```
    puis ouvrir le lien affiché. Chaque nouveau lien invalide le précédent (les tests de bout en bout en créent aussi : relance la commande après `pnpm test:e2e`).
11. **Clé d'administration en local** : elle est dans `config.php` (généré par `scripts/config-dev.php`, jamais lu par l'agent) ; ouvre `config.php` toi-même, copie `CLE_ADMIN`, puis visite `http://127.0.0.1:8095/admin/cle/<la clé>` après être entré par le lien. En production, la clé sera dans le `config.php` que tu créeras sur Hostinger (phase 6).
12. **Lecteurs d'écran** : une passe VoiceOver (iPhone) et TalkBack (Android) sur l'entrée et « Ma page » ; liste détaillée en phase 6.
13. **Sous-domaine conseillé plutôt que sous-dossier** (relecture sécurité) : en sous-dossier (`[domaine]/chores`), le site partage son origine avec tout ce qui tourne sur le domaine ; une faille dans une autre application du même domaine (un WordPress dans `public_html/`, par exemple) pourrait lire les pages des cousins. Si le sous-dossier reste le choix, confirme qu'aucune autre application n'est servie sur ce domaine. Où : `config.php` de production (`BASE_PATH` vide pour un sous-domaine) et `docs/PROGRESS.md`.
14. **Journaux d'accès de l'hébergeur** : chez Hostinger, chaque visite du lien secret écrit son adresse complète dans les journaux d'accès du compte (hPanel › Statistiques ou Journaux). Ne partage jamais ces journaux ; si cela arrive, crée un nouveau lien dans `/admin/lien`. La clé d'administration y passe aussi : une fois posée sur tes appareils, tu peux la changer dans `config.php` (les appareils déjà ouverts devront revisiter la nouvelle adresse). Vérifier en phase 6 si hPanel permet de les couper ou de les purger.
15. **Adresse vue par le serveur chez Hostinger** (phase 6) : vérifier que l'adresse IP vue par PHP est bien celle du visiteur et pas celle d'un proxy ou du CDN de Hostinger ; sinon tous les cousins partageraient la même limite d'essais. Commande à prévoir dans `docs/deploy/verification.md`.

### Phase 3 (jetons, composants, lecteur)

16. **Voir le visuel** : avec le site lancé (`docker compose up -d --build`), ouvre `http://127.0.0.1:8095/gabarits` (libre en local ; en production, il faudra le cookie d'administration, lien « Voir les gabarits » sur `/admin`). La vidéo « Démo » se fabrique avec `node scripts/video-demo.mjs` (20 secondes, aucune donnée réelle) ; sans elle, le lecteur montre « Pas encore de vidéo de référence ».
17. **Téléphone réel, lecteur** (impossible à vérifier sans appareil ; le site local n’écoute que sur ce PC, donc après la mise en ligne de la phase 6) : sur iPhone (Safari) et sur Android (Chrome), ouvre `/chanson/demo/repeter` et vérifie : (a) après le décompte 5-6-7-8, la vidéo démarre seule (sinon le message « Touche Lecture à nouveau » s'affiche : dis-le-moi, c'est la politique de lecture automatique de Safari) ; (b) « Plein écran » (absent sur iPhone si le navigateur ne le permet pas) ; (c) l'écran ne s'éteint pas pendant la lecture ; (d) 0,5 × garde la hauteur de la musique (à vérifier avec une vraie vidéo de référence en phase 5). Note le résultat dans `docs/PROGRESS.md`.
18. **Lecteurs d'écran** : avec VoiceOver (iPhone) puis TalkBack (Android), sur `/gabarits` : le décompte doit dire « Décompte : 5 … 8 », les boutons Miroir et Boucle leur état, la bascule « Mode sombre » son état. Liste complète en phase 6.
19. **Tempos** : le décompte d'une chanson sans tempo part à 120 temps par minute avec la mention « tempo à régler ». Les tempos de `docs/recherches/` seront importés « à confirmer » en phase 4 ; tu pourras les corriger dans `/admin/chansons/{chanson}` (bouton « Taper le tempo »).
20. **Compression chez Hostinger** (phase 6, vérification de sécurité) : les pages ne doivent jamais partir compressées (elles portent le jeton des formulaires). Après la mise en ligne, dans un terminal : `curl -sI -H "Accept-Encoding: gzip, br" https://[domaine]/chores/lien` ; la réponse ne doit contenir aucune ligne `Content-Encoding`. Si elle en contient une, dis-le-moi (réglage LiteSpeed à couper dans hPanel).
21. **`ENV` en production** : dans le fichier de configuration de l'hébergeur, `ENV` doit valoir `'production'` dès la première mise en ligne (jamais `'dev'`). Les pages de travail (`/gabarits`) restent de toute façon fermées hors de ton PC, mais d'autres protections (cookies `Secure`, HSTS) en dépendent.

### Phase 4 (données, chansons, sections, placements)

22. **Importer les faits du groupe dans ta base locale** (déjà fait une fois par l'agent le 13 octobre ; relançable sans risque) : `docker compose exec -T web php scripts/importer-etat.php`. Mode d'emploi complet de l'administration : `docs/ADMIN.md`.
23. **Sections de chaque choré** : aucune n'est posée (les sections de la maquette étaient des exemples). Où : `/admin/chansons/<chanson>/sections` (début, fin en `0:17,5`, nombre de « 8 »). Tant qu'elles manquent, la page dit « Les sections ne sont pas encore posées » et la boucle est désactivée.
24. **Placements** : aucun. Où : `/admin/chansons/<chanson>/placements`, section par section (x de 0 à 100 de gauche à droite, y de 0 au fond à 100 devant).
25. **Tempos à confirmer** (importés de `docs/recherches/`, marqués « tempo à confirmer ») : Yaaro Yaarodi 131, Thalapathy Kacheri ≈ 142, Innisai ≈ 97 (pour la version Varalaru, elle-même à confirmer), Kanimaa ≈ 145. Seul Celebration of Varisu (140) est vérifié. Où : `/admin/chansons/<chanson>`, champ « Tempo » ou bouton « Taper le tempo », puis cocher « Tempo vérifié ».
26. **Innisai** : le film (Varalaru d'après le brief, Godfather d'après le titre d'un lien) et la durée sont inconnus ; la page écrit « [film à confirmer] · [durée inconnue] ». Où : `/admin/chansons/innisai` (Film, Durée).
27. **Vidéos de référence** : aucune déposée. Pour chaque chanson, convertis la vidéo sur ton PC (commande ffmpeg dans `docs/ADMIN.md` § 4) puis dépose-la dans `/admin/chansons/<chanson>` › « Vidéo de référence ».
28. **Ordre de passage, qui entre, d'où, qui lance la musique, durées coupées** : vides. Où : `/admin/chansons/<chanson>`.
29. **Date et lieu du mariage, durée accordée aux danses, lien du sondage, contact du pied de page** : vides (« [date] », « [lieu] », « [contact] »). Où : `/admin/reglages`.
30. **Statut des chansons** : toutes sont « proposée » sauf Innisai (« décidée »), comme dans `etat.json`. Maamadura, The Wedding Song et Sangeeth Celebration restent dans l'encart « Proposées, pas tranchées » de Ma page jusqu'à ce que le groupe tranche. Où : `/admin/chansons/<chanson>` › Statut (« écartée » les retire).
31. **Patrice sur Thalapathy Kacheri** : « en attente » tant que ce n'est pas tranché. Où : `/admin/chansons/thalapathy-kacheri` › Danseurs › Patrice.
32. **Répétitions passées** : présents constatés et compte rendu à saisir. Où (depuis la phase 5) : `/admin/repetitions` › la répétition : statut « passée », un menu « présent / absent » par cousin, compte rendu.
33. **Tests de bout en bout** : pendant `pnpm test:e2e`, le site local sert une base de test séparée (`stockage/test.sqlite`) puis revient à la tienne. Si un test est interrompu brutalement et que ton site local montre des données bizarres (« Section A », « Section B »), lance `docker compose exec -T web php scripts/e2e-outil.php fin`.
34. **Titres des tutoriels** : écrits en français à partir de `docs/recherches/` ; les noms de chaînes YouTube (« Dance Marine », « Learn How To Dance », « nk little dance »…) restent tels quels, et « nk little dance, la fin » traduit « last beat » (interprétation). Corrige un titre si besoin. Où : `/admin/chansons/<chanson>` › Liens (supprime puis rajoute le lien avec le bon titre).
35. **Contact du pied de page** : ce que tu écris dans « Comment te joindre » se lit sur toutes les pages, y compris la page vue sans le lien (« Lien à demander ») : n'y mets pas de numéro de téléphone si tu ne veux pas qu'un inconnu le voie.

### Phase 5 (répétitions, vidéos des cousins, Jour J, PWA)

36. **Message aux cousins sur le réglage iPhone** (à poster dans le groupe WhatsApp et à envoyer à Jessica et Stéphane) : texte prêt dans `docs/VIDEOS.md` § 1 (« Réglages › Appareil photo › Formats › Le plus compatible », filmer une section ou deux).
37. **Taille maximale des vidéos chez Hostinger** : le site accepte 200 Mo par vidéo et 5 000 Mo au total tant que tu ne dis rien. Dans hPanel › Avancé › Configuration PHP, relève `upload_max_filesize` et `post_max_size` (il faut les deux ≥ la taille voulue), puis règle `TAILLE_MAX_UPLOAD_MO` et `ESPACE_MAX_MO` dans le fichier de configuration de production (phase 6). Si `post_max_size` est plus petit, un envoi trop lourd affiche « Fichier trop lourd » (page 413).
38. **Lien du sondage de dates** : toujours vide (la carte dit « Le sondage est dans le groupe WhatsApp ; sinon, dis tes dispos à Patrice. »). Si tu ouvres un sondage Framadate (sans compte, `docs/recherches/outils-organisation-2026-10-12.md`), colle son adresse `https://` dans `/admin/reglages` › « Lien du sondage de dates » : le bouton « Proposer des dates » apparaît sur `/repetitions` et sur Ma page.
39. **Ordre de passage, qui entre, d'où, qui lance la musique, durées coupées** : toujours vides (la frise du Jour J dit « L'ordre de passage n'est pas encore fixé »). Où : `/admin/chansons/<chanson>` (mode d'emploi : `docs/ADMIN.md` § 11).
40. **Check-list du Jour J** : trois lignes du brief (Tenue, Enceinte, Musiques coupées à la bonne durée). À compléter par le groupe. Où : `/admin/jour-j`.
41. **Téléphones réels (après la mise en ligne de la phase 6)** : sur iPhone (Safari) et sur Android (Chrome), vérifier et noter dans `docs/PROGRESS.md` : (a) envoyer une vidéo filmée en 4G depuis « Ajouter ma vidéo » (barre de progression, « Annuler ») ; (b) une vidéo HEVC d'iPhone affiche bien l'avertissement et se lit (ou non) sur un Android ; (c) « Ajouter à l'écran d'accueil » : l'icône grenat « 8 » et le nom « Chorés » ; (d) « Garder sur mon téléphone » puis mode avion : la page de la chanson s'ouvre et la vidéo se lit ; (e) le bouton du sélecteur de fichier est en français (Playwright, réglé en anglais, montre « Choose File » sur les captures : c'est le navigateur qui l'écrit, pas le site).
42. **Vidéo HEVC réelle** : la détection HEVC n'a été essayée que sur des fichiers fabriqués de quelques octets. Dépose une vraie vidéo d'iPhone en « Haute efficacité » et vérifie qu'elle est marquée « format HEVC » dans `/admin/videos` ; puis remplace-la par sa version convertie (`docs/VIDEOS.md` § 5).
