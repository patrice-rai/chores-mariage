# Administration — mode d'emploi pour Patrice

Écrit le 13 octobre 2026 (phase 4). L'administration est réservée à l'appareil sur lequel tu as ouvert l'adresse de ta clé (`/admin/cle/<ta clé>`, voir `docs/A-FAIRE-PAR-PATRICE.md` n° 11) ; sans ce cookie, toutes les pages `/admin` répondent « Page introuvable ». Tout marche sans JavaScript ; avec, deux aides en plus : « Taper le tempo » et « Depuis le lecteur ».

## 1. Mettre les faits du groupe dans le site (une fois)

Dans un terminal ouvert dans `P:\PROJECT\chores-mariage` :

```
docker compose up -d --build
docker compose exec -T web php scripts/importer-etat.php
```

L'import lit `docs/whatsapp/etat.json` et `docs/distribution.md` : neuf cousins, huit chansons (avec danseurs, durées, films et tempos sourcés), les liens partagés dans le groupe et les tutoriels de `docs/recherches/`, les répétitions avec les réponses données dans WhatsApp, la check-list (Tenue, Enceinte, Musiques coupées à la bonne durée). Aucune section, aucun placement, aucune vidéo. Tu peux le relancer : il n'ajoute que ce qui manque et ne réécrit jamais ce que tu as corrigé ici. Si les deux fichiers se contredisent (pas les mêmes danseurs), il s'arrête sans rien garder et le dit.

## 2. Créer (ou renouveler) le lien secret

`/admin` › « Nouveau lien » › « Créer un nouveau lien ». Le lien n'est montré qu'une fois : copie le texte prêt à coller dans WhatsApp, et envoie-le aussi à Jessica et Stéphane. L'ancien lien ne marche plus aussitôt, pour personne. En ligne de commande : `docker compose exec -T web php scripts/nouveau-lien.php`.

## 3. Une chanson : fiche, tempo, danseurs

`/admin` › « Chansons, sections et placements » › le titre de la chanson.

- **Durée** en minutes et secondes (`4:04`), **ordre de passage** (1 à 99), qui entre, d'où, qui lance la musique, durée coupée : vides tant que le groupe n'a pas décidé (le site écrit alors « [durée inconnue] », « [à compléter] »…).
- **Tempo** : écris-le, ou touche « Taper le tempo » à chaque temps en écoutant la musique (la moyenne des 8 derniers intervalles s'écrit dans le champ). Coche « Tempo vérifié » quand tu es sûr : la page passe de « tempo à confirmer (145 tpm ?) » à « 145 tpm ». Sans tempo, le décompte part à 120 avec « tempo à régler ».
- **Danseurs** : pour chaque cousin, « ne danse pas », « danse » ou « en attente » (a demandé, pas de réponse : il voit la choré et peut marquer ses niveaux, avec la mention « en attente »).
- **Choré à créer** : affiche l'encart « Qui s'en occupe ? » ; ce sont les danseurs qui choisissent sur la page de la chanson.
- **Liens** : adresse en `https://` seulement (lien sortant : rien n'est intégré au site), titre, durée, type (référence partagée, officiel, tutoriel), filmé en miroir.

## 4. Déposer une vidéo de référence

Le serveur ne convertit rien. Convertis d'abord la vidéo sur ton PC avec ffmpeg (720p, H.264, AAC, index au début) :

```
ffmpeg -i entree.mov -c:v libx264 -preset medium -crf 23 -vf "scale=-2:720" -pix_fmt yuv420p -movflags +faststart -c:a aac -b:a 128k sortie.mp4
```

(Si la vidéo est filmée de face et que tu la veux en miroir : `-vf "scale=-2:720,hflip"`.) Puis, sur la fiche de la chanson, « Vidéo de référence » › choisis `sortie.mp4` › « Déposer la vidéo ». Taille maximale : `TAILLE_MAX_UPLOAD_MO` (200 Mo par défaut). Une vidéo en HEVC (format « Haute efficacité » des iPhone) est refusée en toutes lettres : reconvertis-la avec la commande ci-dessus. Déposer une nouvelle vidéo remplace l'ancienne (effacée) ; « Retirer la vidéo de référence » revient à « Pas encore de vidéo de référence ».

## 5. Poser les sections

Fiche › « Sections ». Chaque section : nom (40 caractères), début et fin en `minutes:secondes,dixièmes` (`0:17,5`), nombre de « 8 », note. Avec la vidéo de référence affichée en haut de la page, mets-la en pause au bon endroit et touche « Début depuis le lecteur » ou « Fin depuis le lecteur ». Le site refuse en toutes lettres une fin avant le début, une fin après la durée de la chanson et deux sections qui se chevauchent. « Monter » et « Descendre » changent l'ordre ; « Supprimer » demande une confirmation et efface aussi les niveaux et placements de la section.

## 6. Poser les placements

Fiche › « Placements ». Une section à la fois (menu « Section » › « Afficher »). Pour chaque danseur, `x` va de 0 (côté jardin, à gauche) à 100 (côté cour, à droite) et `y` de 0 (au fond) à 100 (devant, près du public) ; les deux vides : « non placé ». Les flèches ← → ↑ ↓ enregistrent tout le formulaire et déplacent d'un pas de 5 (un danseur non placé part du centre). « Copier la section précédente » reprend toutes les positions de la section d'avant. Les cousins voient tout sur `/placements` et peuvent bouger leur propre point d'un pas ; coche « Placements fixés » dans les réglages pour l'interdire.

## 7. Réglages

`/admin/reglages` : date (AAAA-MM-JJ) et lieu du mariage, durée accordée aux danses, lien du sondage de dates (`https://`, Framadate par exemple ; vide : « Pas de sondage en cours : dis tes dispos à Patrice »), texte d'accueil de l'entrée, comment te joindre (pied de page « Un souci ? Écris à Patrice … »), placements fixés.

## 8. Journal

`/admin/journal` : les 200 dernières actions (entrées, prénoms, niveaux, placements, « qui s'en occupe », présences, vidéos, commentaires, check-list, administration), sans adresse IP ni appareil. Les questions ouvertes relevées dans le groupe WhatsApp sont sur `/admin` (pense-bête : les cousins ne les voient pas).

## 9. Répétitions (phase 5)

`/admin` › « Répétitions ». La liste montre toutes les répétitions (la plus lointaine d'abord) avec le nombre de réponses.

- **Ajouter** : date (AAAA-MM-JJ), heure (texte libre, par exemple « 14 h – 18 h »), lieu, statut, « on travaille », note. Une seule répétition par jour : une date en double est refusée en toutes lettres.
- **Statuts** : « proposée » (une date possible : les cousins disent s'ils sont dispos), « décidée » (elle a lieu : elle devient la carte « Prochaine répétition » de Ma page), « passée » (elle rejoint les répétitions passées), « annulée » (plus personne ne peut répondre ; la carte dit « Annulée »).
- **Après coup** : ouvre la répétition, écris le compte rendu et, pour chaque cousin, choisis « pas noté », « présent » ou « absent ». C'est distinct de ce que chacun a répondu (la réponse est rappelée sous chaque menu). Les cousins lisent alors « Présents : … · Absents : … » et le compte rendu.
- Les cousins répondent « Je viens » ou « Je ne peux pas » sur Ma page et sur `/repetitions` ; un vote « dispo » importé du sondage WhatsApp est remplacé par leur réponse sur le site.

## 10. Vidéos des cousins (phase 5)

`/admin` › « Vidéos » : toutes les vidéos (des cousins et de référence) avec leur taille, leur format deviné (H.264, HEVC, non reconnu) et l'espace utilisé.

- **Supprimer** : confirmation en page, le fichier est effacé du serveur (pas de retour en arrière). Un cousin supprime lui-même ses vidéos et ses commentaires ; toi, tu peux tout supprimer depuis n'importe quelle page de vidéo **sur un appareil où tu as ouvert l'administration** (le simple prénom « Patrice » ne suffit pas : n'importe qui peut le choisir).
- **Une vidéo marquée HEVC ou « non reconnu »** (« format iPhone » pour les cousins) est peut-être illisible sur Android et sur PC : enregistre-la depuis sa page (clic droit sur la vidéo › « Enregistrer la vidéo sous… »), convertis-la sur ton PC (commande dans `docs/VIDEOS.md`), puis « Remplacer par une version convertie ». La page de la vidéo, ses commentaires et sa durée restent ; l'adresse du fichier change, donc aucun téléphone ne garde l'ancienne version.

## 11. Jour J (phase 5)

- **Ordre de passage** : dans la fiche de chaque chanson (`/admin/chansons/<chanson>`) : « Ordre de passage » (1, 2, 3…), « Qui entre », « D'où », « Qui lance la musique », « Durée coupée ». Les chansons sans ordre n'apparaissent pas dans la frise ; sans aucun ordre, les cousins lisent « L'ordre de passage n'est pas encore fixé ».
- **Date, lieu, durée accordée** : `/admin/reglages` (« [date] » et « [lieu] » tant qu'ils sont vides).
- **Check-list** : `/admin` › « Jour J » : ajouter, renommer, monter, descendre, supprimer (confirmation en page). Les cousins touchent une ligne pour la marquer « fait » (avec leur prénom) ou de nouveau « à faire ».
- **Mode scène** : le bouton du Jour J ouvre les placements de la première chanson à plusieurs de l'ordre de passage, en grand ; « Quitter » revient au Jour J.

## 12. Base et sauvegardes (phase 6)

`/admin` › « Base et sauvegardes » (`/admin/base`, page qui reste utilisable même quand une nouvelle version attend ses mises à jour) :

- **Version du site** et état de la base : « Base : à jour », ou « N mise(s) à jour en attente » avec le bouton « Mettre la base à jour » (après une nouvelle version mise en ligne sans SSH ; fais d'abord une sauvegarde). Mode d'emploi : `docs/deploy/mise-a-jour.md`.
- **Faire une sauvegarde maintenant** : copie cohérente de la base dans le dossier des sauvegardes de l'hébergeur (`base-AAAA-MM-JJ-HHMMSS.sqlite`), à télécharger par le gestionnaire de fichiers de hPanel ; les 12 plus récentes sont gardées. Le site ne propose jamais de téléchargement lui-même.
- **Restaurer** : sur la ligne d'une sauvegarde, puis « Oui, restaurer » : la base actuelle est copiée à côté, la sauvegarde la remplace, le lien en service est gardé (`docs/deploy/sauvegardes.md` § 3).
- **Installation** : sur une base neuve, `/installer` (clé d'administration dans un champ) prépare la base, importe les faits du groupe et crée le premier lien ; ensuite cette adresse n'existe plus (`docs/deploy/README.md` § 9).
