# Vidéos — réglages, conversion et remplacement

Écrit en phase 5 (13 octobre 2026). Le site n'envoie aucune vidéo ailleurs et ne convertit rien sur le serveur (l'hébergement mutualisé n'a pas ffmpeg et partage son processeur). Les vidéos restent dans `stockage/medias/`, hors de la racine web, servies seulement aux porteurs du lien.

## 1. Sur iPhone : « Le plus compatible »

Les iPhone filment par défaut en HEVC (« Haute efficacité »). Beaucoup de téléphones Android et de PC ne savent pas lire ce format. Avant de filmer :

> Réglages › Appareil photo › Formats › **Le plus compatible**

Message à transmettre aux cousins (WhatsApp, et directement à Jessica et Stéphane) :

> Pour vos vidéos de répétition sur le site : sur iPhone, allez dans Réglages › Appareil photo › Formats et choisissez « Le plus compatible ». Filmez une section ou deux, pas la chanson entière. Si vous oubliez, envoyez quand même : Patrice convertira.

Le site devine le format à l'envoi (atomes `hvc1`/`hev1` ou `avc1` dans les 4 premiers et 4 derniers Mo du fichier) : une vidéo HEVC est acceptée, son auteur lit un avertissement, et tout le monde voit le mot « format iPhone ».

## 2. Limites

- Formats : MP4, MOV, WebM (extension, type annoncé et signature du fichier vérifiés ; un texte renommé en `.mp4` est refusé).
- Taille : `TAILLE_MAX_UPLOAD_MO` dans le fichier de configuration (200 Mo par défaut, à confirmer selon `upload_max_filesize` et `post_max_size` de l'hébergeur, A-FAIRE n° 3).
- Espace total : `ESPACE_MAX_MO` (5 000 Mo par défaut) ; au-delà : « Plus de place : demande à Patrice de faire du ménage ».
- Débit : 5 vidéos par heure et 20 par jour par prénom, 10 par heure par adresse ; 30 commentaires par heure par prénom.

## 3. Poids attendus (ordres de grandeur, à vérifier sur vos téléphones)

| Source | Une minute environ |
| --- | --- |
| iPhone 1080p 30 i/s « Le plus compatible » (H.264) | 100 à 130 Mo |
| iPhone 1080p « Haute efficacité » (HEVC) | 60 à 80 Mo |
| Android 1080p (H.264) | 100 à 130 Mo |
| Après la conversion ci-dessous (720p, CRF 23) | 15 à 40 Mo |

D'où le conseil « une section ou deux » : 30 secondes filmées en 1080p tiennent sous 70 Mo ; une chanson entière dépasse souvent 200 Mo.

## 4. Convertir sur ton PC (ffmpeg)

Installe ffmpeg (https://ffmpeg.org, version « essentials » pour Windows), puis, dans un terminal ouvert dans le dossier de la vidéo :

```
ffmpeg -i entree.mov -c:v libx264 -preset medium -crf 23 -vf "scale=-2:720" -pix_fmt yuv420p -movflags +faststart -c:a aac -b:a 128k sortie.mp4
```

- `-crf 23` : bonne qualité pour un poids raisonnable (26 pour plus léger) ;
- `scale=-2:720` : 720 pixels de haut, largeur proportionnelle ;
- `yuv420p` : lisible par Safari ;
- `+faststart` : l'index au début du fichier, lecture et positionnement immédiats.

**En miroir** (pour un fichier qu'on veut toujours lire inversé) : ajoute `,hflip` au filtre :

```
ffmpeg -i entree.mov -c:v libx264 -preset medium -crf 23 -vf "scale=-2:720,hflip" -pix_fmt yuv420p -movflags +faststart -c:a aac -b:a 128k sortie-miroir.mp4
```

Le lecteur du site met aussi en miroir à la demande (bouton « Miroir ») : la conversion en miroir n'est utile que pour un fichier qu'on veut toujours lire ainsi.

## 5. Remplacer une vidéo d'un cousin par sa version convertie

1. Ouvre la vidéo sur le site, clic droit sur la vidéo › « Enregistrer la vidéo sous… » (sur ton PC).
2. Convertis-la avec la commande du § 4.
3. `/admin` › « Vidéos » › sous la vidéo : « Remplacer par une version convertie », choisis `sortie.mp4`, « Remplacer ».

Le site refuse une version encore en HEVC. La page, les commentaires et la durée restent ; l'ancien fichier est effacé ; l'adresse du fichier change (aucun téléphone ne garde l'ancienne version).

## 6. Vidéos de référence

Une par chanson, déposée dans `/admin/chansons/<chanson>` › « Vidéo de référence » (MP4 H.264 ou WebM ; HEVC refusé). Voir `docs/ADMIN.md` § 4.

## 7. « Garder sur mon téléphone »

Sous la vidéo de référence d'une chanson et sous chaque vidéo de cousin, quand le navigateur le permet (service worker actif) : la vidéo est téléchargée entière dans le téléphone et se lit ensuite sans réseau ; « Retirer de mon téléphone » la supprime. Le site vérifie la place libre avant (refus écrit sinon). Sur iPhone, Safari peut effacer ces vidéos après quelques jours sans visite si le site n'est pas ajouté à l'écran d'accueil : le bouton l'explique (Partager › « Sur l'écran d'accueil »). Quand le lien secret est renouvelé, les vidéos et les pages gardées sont effacées du téléphone à la visite suivante.
