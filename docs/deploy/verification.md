# Vérifier la mise en ligne

À faire juste après `docs/deploy/README.md`, puis après chaque mise à jour. Les commandes se tapent dans PowerShell
sur ton PC : écris bien **`curl.exe`** (dans Windows PowerShell 5, `curl` tout court est un autre outil). Remplace
`[domaine]` (et `/chores` par rien si tu as choisi un sous-domaine). Coche chaque ligne ; si une ligne échoue, ne
diffuse pas le lien avant de l'avoir corrigée.

## 1. HTTPS

- [ ] `curl.exe -sI http://[domaine]/chores/lien` → `HTTP/1.1 301` (ou 302) avec `Location: https://…`.
- [ ] `https://[domaine]/chores/lien` s'ouvre dans le navigateur avec le cadenas, sans avertissement.

## 2. En-têtes de sécurité

```
curl.exe -sI -H "Accept-Encoding: gzip, br" https://[domaine]/chores/lien
```

La réponse doit contenir, une seule fois chacun :

- [ ] `Content-Security-Policy: default-src 'self'; …; upgrade-insecure-requests` (sans `unsafe-inline`) ;
- [ ] `Strict-Transport-Security: max-age=31536000` ;
- [ ] `X-Content-Type-Options: nosniff` ;
- [ ] `Referrer-Policy: no-referrer` ;
- [ ] `Permissions-Policy: camera=(), microphone=(), …` ;
- [ ] `Cross-Origin-Opener-Policy: same-origin` et `X-Frame-Options: DENY` ;
- [ ] `X-Robots-Tag: noindex, nofollow, noarchive` ;
- [ ] `Cache-Control: no-store` ;
- [ ] **aucune** ligne `Content-Encoding` (les pages ne doivent jamais partir compressées : elles portent le jeton
  des formulaires). S'il y en a une, c'est la compression de LiteSpeed : dis-le dans `docs/PROGRESS.md`.
- [ ] aucune ligne `X-Powered-By`.

Sur un fichier statique et sur une page inconnue, mêmes en-têtes de sécurité, sauf `Strict-Transport-Security`,
`upgrade-insecure-requests` et `Cache-Control: no-store` sur le fichier statique (il est servi par le serveur, pas par
PHP) :

```
curl.exe -sI https://[domaine]/chores/css/base.css
curl.exe -sI https://[domaine]/chores/page-qui-n-existe-pas
```

- [ ] `base.css` : 200 avec `Content-Security-Policy`, `X-Content-Type-Options` et
  `Cache-Control: public, max-age=31536000, immutable` ;
- [ ] page inconnue : 404 avec `Content-Security-Policy`.

## 3. Rien d'indexable, rien de secret servi

```
curl.exe -s https://[domaine]/chores/robots.txt
curl.exe -s https://[domaine]/chores/lien | findstr robots
curl.exe -sI https://[domaine]/chores/chemin-app.php
curl.exe -sI https://[domaine]/chores/.htaccess
curl.exe -sI https://[domaine]/chores-app/config.php
curl.exe -sI https://[domaine]/chores-app/VERSION
curl.exe -sI https://[domaine]/chores-app/stockage/base.sqlite
curl.exe -sI https://[domaine]/chores-mariage-v1.0.0.zip
```

- [ ] `robots.txt` contient `Disallow: /` ;
- [ ] la page contient `<meta name="robots" content="noindex, nofollow">` ;
- [ ] `chemin-app.php` et `.htaccess` : 403 ou 404 ;
- [ ] les trois adresses `chores-app/…` : 404 (dossier hors racine web) ou 403 (repli 5 bis). **Jamais 200.**
- [ ] le zip : 404 (supprimé après l’extraction).

## 4. Accès

```
curl.exe -sI https://[domaine]/chores/moi
curl.exe -sI https://[domaine]/chores/m/1
curl.exe -sI https://[domaine]/chores/admin
curl.exe -sI https://[domaine]/chores/installer
```

- [ ] `/moi` et `/m/1` : 302 vers `/chores/lien` (rien sans le lien) ;
- [ ] `/admin` : 404 ;
- [ ] `/installer` : 404 (installation faite).
- [ ] Dans une fenêtre de navigation privée, le lien secret ouvre l'entrée avec les neuf prénoms ; Ma page s'affiche
  après avoir touché un prénom.
- [ ] Dans les outils du navigateur (F12 › « Application » › « Cookies »), `cm_acces`, `cm_prenom` et `cm_admin` sont
  `HttpOnly`, `Secure`, `SameSite=Lax`, chemin `/chores`.
- [ ] Une vidéo de référence se lit dans la page d'une chanson ; son adresse (`/chores/m/…`) ouverte dans une autre
  fenêtre privée, sans le lien, renvoie vers « Lien à demander ».

## 5. Adresse vue par le site (limitation des essais)

Le site compte les essais par adresse IP (sans la garder en clair). Vérifie qu'il voit bien la tienne et pas celle
d'un relais de Hostinger :

- [ ] depuis ton téléphone en 4G (Wi-Fi coupé), ouvre 11 fois de suite `https://[domaine]/chores/e/faux` : la 11ᵉ
  fois, « Doucement ! Trop d’essais d’un coup » s'affiche ;
- [ ] aussitôt après, depuis ton PC (autre connexion), le vrai lien marche encore.

Si le PC est bloqué lui aussi, tous les cousins partagent la même limite : note-le dans `docs/PROGRESS.md`.

## 6. Envoi d'une vraie vidéo et installation

- [ ] Depuis un téléphone : « Vidéos » › « Ajouter ma vidéo », une vidéo de 20 à 30 secondes : la barre de progression
  avance, la vidéo apparaît dans l'album et se lit ; supprime-la ensuite.
- [ ] `/admin` › « Espace » augmente puis redescend après la suppression.
- [ ] Ajout à l'écran d'accueil (iPhone : Partager › « Sur l'écran d'accueil » ; Android : menu › « Installer
  l'application ») : l'icône grenat « 8 » et le nom « Chorés ».
- [ ] La suite des tests sur téléphone : `docs/A-FAIRE-PAR-PATRICE.md`, partie B.

## 7. Journaux de l'hébergeur

- [ ] hPanel › journaux d'accès **[à vérifier]** : l'adresse du lien secret (`/chores/e/…`) et de la clé
  (`/chores/admin/cle/…`) y figurent. Ne partage jamais ces journaux ; si cela arrive, crée un nouveau lien
  (`docs/deploy/lien.md`).
