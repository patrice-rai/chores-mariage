# Plan — Phase 4 : données, chansons, sections, placements et suivi

Écrit le 13 octobre 2026 avant le code, en mode autonome (tentative 1). Sources : `docs/prompts/phase-04.md`, spec § 2, 3, 5, 6, 7, 11, 12 ; maquette (`Chorés du mariage.dc.html` : écrans 2 Ma page, 3 Page d'une chanson, 5 Placements) ; faits : `docs/whatsapp/etat.json`, `docs/distribution.md`, `docs/recherches/chansons-et-tutoriels-2026-10-12.md`. Tag `avant-phase-4` posé.

## 1. Migration `app/migrations/002-donnees.sql`

Tables de la spec § 3, sans aucun `DROP` : `chansons`, `liens`, `distributions`, `sections`, `placements`, `niveaux`, `repetitions`, `presences`, `videos`, `commentaires`, `checklist`.

- Contraintes `CHECK` sur tous les ensembles fermés (`statut`, `type`, `reponse`, `niveau` 0-2, `x`/`y` 0-100, `fin_s > debut_s`, longueurs) ; clés uniques (`distributions (chanson_id, cousin_id)`, `placements (section_id, cousin_id)`, `niveaux (cousin_id, section_id)`, `presences (repetition_id, cousin_id)`, `liens (chanson_id, url)`, `repetitions (date)`, `chansons (slug)`, `chansons (titre)`).
- Clés étrangères : `ON DELETE CASCADE` des sections vers niveaux et placements, des chansons vers distributions/sections/liens ; `videos.chanson_id` sans cascade (une vidéo ne disparaît jamais en silence).
- Le chevauchement des sections et `fin ≤ durée` sont contrôlés par l'application (message en toutes lettres) et par un déclencheur de dernier recours (refus d'une section qui chevauche).
- `chansons.video_ref_id` → `videos(id)` `ON DELETE SET NULL`.
- Index : `sections (chanson_id, ordre)`, `niveaux (section_id)`, `videos (chanson_id)`, `presences (repetition_id)`.

## 2. Import `scripts/importer-etat.php` → `app/import.php`

- Fonction `importer_etat(PDO, string $etatJson, string $distributionMd)` ; le script lit `docs/whatsapp/etat.json` et `docs/distribution.md` (chemins en argument possibles, pour la phase 6).
- Idempotent, **sans écraser ce que Patrice a modifié** : chanson repérée par son slug (titre d'`etat.json` normalisé, « Inisai » → « Innisai », titre YouTube cité par la note), insérée si absente, jamais réécrite ensuite ; distributions, liens (clé : URL), répétitions (clé : date), présences importées (source `whatsapp`) en `INSERT OR IGNORE` ; check-list semée une seule fois (réglage `import_checklist`) ; contacts WhatsApp des cousins mis à jour ; questions ouvertes rangées dans le réglage `questions_ouvertes` (JSON) pour `/admin`.
- Faits sourcés tenus dans le code avec leur source (aucun inventé) : durées (2:48, 5:47, 3:17, 4:04 ; Innisai NULL), films (Varisu, Alaipayuthey, Jana Nayagan, Retro ; Innisai NULL ; Jigarthanda Double X, Vishwanath and Sons, Dragon d'après `distribution.md`), tempos (140 vérifié ; 131, 142, 97, 145 non vérifiés), `a_creer` pour Yaaro Yaarodi et Innisai (`recherches/` « À créer », `distribution.md` « qui crée les chorégraphies 2 et 4 »), note « Nadine coupe la musique » ; titres et partageurs des liens d'`etat.json` tirés des notes ; tutoriels de `recherches/` (lignes dont le type contient « tutoriel », durée de la ligne, « en miroir » quand la ligne le dit). Un test vérifie que chaque URL et chaque durée importées figurent dans les documents sources, et que les danseurs d'`etat.json` sont ceux de `distribution.md`.
- Répétitions : celles d'`etat.json` (`decidee` ou `proposee`, lieu, horaire et note quand ils sont donnés), présences importées `oui` (a dit venir) ou `peut_etre` (incertain, vote « dispo » au sondage WhatsApp).
- Aucune section, aucun placement, aucune vidéo, ordre de passage NULL.

## 3. Requêtes `app/donnees.php`

Chansons d'un cousin (danse ou en attente, ordre de passage puis titre), chanson par slug, distribution, sections, niveaux, grille, placements, liens, prochaine répétition (prochaine `decidee` datée d'aujourd'hui ou après, sinon `proposee` future la plus votée — égalité : la plus proche — avec « date pas encore fixée » et le nombre de dispos, sinon « Pas de répétition prévue »), « Ce qui bouge » (niveaux passés à « je maîtrise », dix derniers), espace de stockage. Validation des sections (`valider_section`) et lecture des temps `mm:ss,d`.

## 4. Pages des cousins (`app/controleurs/cousins.php`)

- `GET /moi` : Salut, carte « Prochaine répétition » en lecture (lien « Voir les répétitions »), « Tes chorés » (carte-chanson : à créer, décidée, en attente, progression), encart « Proposées, pas tranchées » (chansons `proposee` sans danseur), « Ce qui bouge », navigation Placements / Répétitions / Vidéos des cousins / Jour J, cas « Tu n'es sur aucune choré pour l'instant. ».
- `GET /chanson/{slug}` : en-tête (film ou « [film à confirmer] », durée ou « [durée inconnue] », « 140 tpm », « tempo à confirmer » ou « tempo à régler », danseurs, « Patrice en attente ») ; encart orange « Qui s'en occupe ? » si à créer (`POST /chanson/{slug}/createur`, danseurs seulement, journalisé) ; lecteur (vidéo `/m/{id}` ou aplat) ; sections et mes niveaux (`POST /chanson/{slug}/section/{id}/niveau`, redirection ou JSON ; formulaires pour les danseurs et en attente seulement) ou « Les sections ne sont pas encore posées. Patrice peut les créer dans l'administration. » ; « Ma place » (mini-scène par section, la courante visible ; JavaScript suit la section choisie) ; « Qui maîtrise quoi » ; tutoriels et références ; « Vidéos des cousins » vide ; deux colonnes au bureau (`page-chanson`).
- `GET /chanson/{slug}/repeter` (niveau prénom, vraies sections) ; la démonstration passe à `GET /gabarits/repeter`.
- `GET /api/etat?chanson=slug` : sections, mes niveaux, version du lien (JSON, `no-store`).
- `GET /placements` (redirige vers la première de mes chorés à plusieurs, sinon la première à plusieurs) et `GET /placements/{slug}` : chips des chansons à plusieurs danseurs, scène (points placés, liste « non placé »), flèches vers la suivante, « Me surligner », ↑ ↓ ← → (`POST /placements/{section}/moi`, pas de 5, borné 0-100, journalisé, refusé si `placements_verrouilles`) ; bureau : toutes les sections côte à côte (mini-scènes) ; `?scene=1` mode scène (points 68 px, Quitter → Ma page).
- `GET /repetitions` en lecture (à venir, passées, réponses importées) ; `GET /videos`, `GET /jour-j` : page « Bientôt » (phase 5).
- `GET /m/{id}` (niveau accès) : vidéo non supprimée, fichier dans `stockage/medias/`, type de la base, plages, `Cache-Control: private, max-age=31536000, immutable`.
- Débit : niveaux, créateur, placements : 300 par heure par prénom.

## 5. Administration (`app/controleurs/admin-donnees.php`)

`/admin` (lien, entrées, dernières actions, espace de `stockage/`, questions ouvertes, liens vers tout), `/admin/reglages` (date et lieu du mariage, durée accordée, lien du sondage https, texte d'entrée, contact du pied de page, verrou des placements), `/admin/chansons`, `/admin/chansons/{slug}` (fiche, « Taper le tempo », danseurs par select danse / en attente / non, liens ajout / suppression, vidéo de référence), `POST /admin/chansons/{slug}/reference` (MP4 ou WebM, MIME + extension + signature, taille ≤ `TAILLE_MAX_UPLOAD_MO`, espace ≤ `ESPACE_MAX_MO`, codec deviné dans les 4 premiers et 4 derniers Mo, refus HEVC en toutes lettres, nom aléatoire, ancienne référence marquée supprimée et effacée), `/admin/chansons/{slug}/sections` (liste ordonnée, ajout, modification, monter, descendre, suppression avec confirmation, « Depuis le lecteur » en JavaScript, validations), `/admin/chansons/{slug}/placements` (une section à la fois, x/y par danseur, flèches, « Copier la section précédente », « Retirer »), `/admin/journal` (200 lignes). Tout marche sans JavaScript ; `public/js/admin.js` ajoute « Taper le tempo » et « Depuis le lecteur ».

## 6. JavaScript

- `public/js/chanson.js` : niveaux envoyés par `fetch` (Accept JSON), boutons, grille et progression mis à jour sans rechargement, message `role="status"` ; « Ma place » suit l'évènement `lecteur:section` émis par `lecteur.js`.
- `lecteur.js` : émet `lecteur:section`.

## 7. Tests

- Base de test séparée pour les tests de bout en bout : `scripts/e2e-outil.php debut` pose le témoin `stockage/e2e.actif` (lu seulement en `ENV = dev`) qui fait servir `stockage/test.sqlite` ; chaque `preparer` recrée la base de test (migrations + import) ; `fin` (teardown global) efface la base de test, ses vidéos et le témoin.
- PHPUnit : import (deux passages, contenu exact : 9 cousins, 8 chansons, 7 répétitions, 0 section ; sources), validations des sections, niveaux bornés, `/m/{id}` sans cookie et id inconnu, `206`, admin 404 sans clé, refus HEVC (fixture de quelques octets), téléversement de référence, pages des cousins, placements.
- Playwright : parcours Jessica (3 chorés) → Kanimaa (sections pas posées) ; admin : Section A et Section B, puis cousin : niveau « Je maîtrise », grille, « 1 section sur 2 maîtrisée » ; Nadine (solo décidé à créer) ; Patrice (Kacheri en attente) ; placements visibles par un autre cousin ; mode répétition sur vraies sections avec la vidéo de test déposée comme référence ; axe 390 et 1 280, deux thèmes ; captures `docs/captures/phase-04/`.

## 8. Documentation

`docs/ADMIN.md`, `docs/A-FAIRE-PAR-PATRICE.md`, `docs/PROGRESS.md` (phase 4).
