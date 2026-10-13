# Mettre le site en ligne chez Hostinger — pas à pas

Version 1.0.0. Pour Patrice, compte Hostinger mutualisé, en une heure environ. Rien ici n'a été fait par l'agent : il
n'a eu accès ni à Hostinger ni à un serveur. Le paquet a été essayé en local dans une arborescence qui imite celle
de Hostinger (résultat dans `docs/RECETTE.md`, « Essai du paquet »). Les libellés de hPanel cités ici sont ceux
connus au moment de l'écriture ; s'ils ont changé, cherche le mot entre guillemets dans la barre de recherche de
hPanel. Ce qui reste à vérifier chez toi est marqué **[à vérifier]**.

Dans les commandes, remplace :

- `[domaine]` par ton nom de domaine (par exemple `exemple.fr`) ;
- `[compte]` par ton identifiant d'hébergement (`u123456789`, visible en haut du gestionnaire de fichiers ou dans
  hPanel › « Avancé » › « Accès SSH »).

## 0. Choisir l'emplacement (une fois)

| | Sous-dossier (défaut) | Sous-domaine (conseillé par la relecture sécurité) |
|---|---|---|
| Adresse | `https://[domaine]/chores/` | `https://chores.[domaine]/` |
| `BASE_PATH` | `'/chores'` | `''` (vide) |
| Racine web du site | `public_html/chores/` | le dossier créé par hPanel pour le sous-domaine **[à vérifier]** |

Le sous-domaine isole le site de tout ce qui tourne déjà sur le domaine (un WordPress dans `public_html/`, par
exemple). Si tu gardes le sous-dossier, vérifie qu'aucune autre application n'est servie sur ce domaine.

L'arborescence visée (sous-dossier) :

```
/home/[compte]/domains/[domaine]/
├── public_html/
│   └── chores/              ← contenu de public/ du paquet (racine web du site)
│       ├── .htaccess
│       ├── index.php
│       └── chemin-app.php   ← à écrire (étape 6)
└── chores-app/              ← le reste du paquet, HORS racine web
    ├── app/  scripts/  donnees/  docs/deploy/
    ├── config.example.php  config.php (étape 7)
    └── stockage/            ← créé à l'étape 8 : base, vidéos, sauvegardes
```

## 1. Préparer le paquet et les secrets (sur ton PC)

Dans un terminal PowerShell ouvert dans `P:\PROJECT\chores-mariage`, le site local lancé (`docker compose up -d`) :

```
docker compose exec -T web php scripts/paquet.php
```

Le paquet est `dist\chores-mariage-v1.0.0.zip` (la commande affiche sa taille et son empreinte SHA-256).

Puis génère les deux secrets (garde-les dans un endroit sûr, ne les envoie à personne) :

```
docker compose exec -T web php -r "echo 'SECRET    : ', bin2hex(random_bytes(32)), PHP_EOL, 'CLE_ADMIN : ', rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='), PHP_EOL;"
```

## 2. PHP 8.3 et ses extensions (hPanel)

1. hPanel › « Sites web » › ton domaine › « Avancé » › « Configuration PHP » › onglet « Version PHP » : choisir
   **PHP 8.3**, enregistrer.
2. Onglet « Extensions PHP » : cocher (ou vérifier cochées) `pdo_sqlite`, `sqlite3`, `mbstring`, `fileinfo` ; `gd`
   est facultatif (il ne sert qu'à refaire les icônes, déjà fournies). Enregistrer.
3. Onglet « Options PHP » : `upload_max_filesize` = **256M**, `post_max_size` = **256M** (les deux au moins à la taille
   maximale d'une vidéo, 200 Mo par défaut), `max_input_time` = **600**, `memory_limit` = **256M**. Enregistrer.
   Si l'offre ne permet pas ces valeurs, note la plus grande permise : elle servira à l'étape 7
   (`TAILLE_MAX_UPLOAD_MO`) **[à vérifier : limites de ton offre]**.

## 3. HTTPS d'abord

hPanel › « Sécurité » › « SSL » : le certificat gratuit du domaine (et du sous-domaine si tu l'as choisi) doit être
« Actif ». Active « Forcer HTTPS » si l'option est proposée. Vérifie dans un navigateur que
`https://[domaine]/` s'ouvre sans avertissement **avant** de continuer : la configuration de l'étape 7 met
`ENV = 'production'`, qui envoie HSTS (le navigateur refusera ensuite le site en `http://` pendant un an).

HSTS vaut pour tout l'hôte qui sert le site : en sous-dossier, c'est **tout `[domaine]`** (les autres pages du
domaine aussi ne s'ouvriront plus qu'en `https://`) ; en sous-domaine, seulement `chores.[domaine]`.

Si hPanel ne propose pas « Forcer HTTPS », ajoute ces deux lignes **au début** de `public_html/chores/.htaccess`
(étape 5), juste après `RewriteEngine On`, puis vérifie qu'aucune boucle de redirection n'apparaît
**[à vérifier]** :

```
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

## 4. Le sous-dossier (ou le sous-domaine)

- Sous-dossier : hPanel › « Fichiers » › « Gestionnaire de fichiers » ; dans `public_html/`, rien à créer (le dossier
  `chores` arrive à l'étape 5).
- Sous-domaine : hPanel › « Domaines » › « Sous-domaines » › créer `chores` ; note le dossier que hPanel lui donne
  **[à vérifier]**. Ce dossier remplace `public_html/chores/` dans la suite, et `BASE_PATH` sera vide.

## 5. Envoyer et décompresser le paquet

Dans le gestionnaire de fichiers :

1. Ouvre `/home/[compte]/domains/[domaine]/` (le dossier **qui contient** `public_html`). **[à vérifier : tu peux y
   créer un dossier.]** Si ce n'est pas permis, suis le repli de l'étape 5 bis.
2. « Envoyer » `chores-mariage-v1.0.0.zip`, puis clic droit › « Extraire » ici : un dossier
   `chores-mariage-v1.0.0/` apparaît.
3. Renomme `chores-mariage-v1.0.0` en **`chores-app`**.
4. Déplace `chores-app/public` dans `public_html/`, puis renomme-le **`chores`** (sous-domaine : déplace son
   **contenu** dans le dossier du sous-domaine, fichier caché `.htaccess` compris ; active « Afficher les fichiers
   cachés » dans les réglages du gestionnaire).
5. Supprime le fichier `.zip` envoyé.

### 5 bis. Repli : impossible d'écrire hors de `public_html`

Envoie et extrais le zip dans `public_html/`, puis **supprime le zip aussitôt** (tant qu'il est là, il se
télécharge par `https://[domaine]/chores-mariage-v1.0.0.zip`, avec le code et les faits du groupe). Mets
`chores-app/` **dans** `public_html/` (à côté de `chores/`). Le fichier `chores-app/.htaccess` du paquet refuse alors
tout accès web à ce dossier ; vérifie-le tout de suite : `curl.exe -sI https://[domaine]/chores-app/VERSION` doit
répondre **403**. S'il répond 200, le serveur ignore ce fichier : demande au support de Hostinger comment interdire un
dossier avant d'aller plus loin, et supprime `chores-app/donnees/` juste après l'étape 9 (import fait). Dans
`chemin-app.php` (étape 6), le chemin devient `…/public_html/chores-app/app`.

### 5 ter. Variante la plus simple : un site Hostinger dédié, tout dans `public_html`

Choisie le 13 octobre 2026 pour le site `[site]` (un site Hostinger qui n'héberge
que les chorés). Le paquet entier est déposé dans `public_html/` et un `.htaccess` racine fait le tri :

1. Extrais le paquet directement dans `public_html/` (ou envoie le dossier préparé sur le PC :
   `dist/hostinger-darkorchid/public_html`, qui contient déjà tout ce qui suit). Supprime le fichier d'accueil
   que Hostinger y met par défaut (`default.php` ou `index.html`).
2. Remplace le `.htaccess` de la racine du paquet (il refuse tout) par `docs/deploy/htaccess-public_html`. Il sert le
   site depuis `public/` sans que « /public » apparaisse dans l'adresse, interdit `app/`, `scripts/`, `donnees/`,
   `docs/`, `stockage/`, `config.php`, `VERSION` et les fichiers cachés (403), et force HTTPS.
3. `chemin-app.php` est inutile : `public/index.php` trouve `../app` tout seul.
4. `config.php` et le dossier `stockage/` (droits 750) sont à la racine de `public_html`, à côté de `app/` ;
   `BASE_PATH` vide, `URL_PUBLIQUE` = l'adresse du site. Les étapes 6 et 5 bis ne s'appliquent pas ; les étapes 2, 3,
   7, 8 et 9 restent.
5. Vérifie tout de suite après le dépôt, avec `curl.exe -sI` : `https://[site]/config.php`, `/app/bootstrap.php`,
   `/stockage/`, `/donnees/etat.json` et `/VERSION` répondent **403** ; `https://[site]/installer` répond 200 ;
   `http://[site]/` renvoie en 301 vers `https://`. Une adresse tapée « /public/… » tombe sur la page 404 du site.

Testé en local le 13 octobre 2026 avec l'image Docker du projet, racine web sur le dossier entier : mêmes codes.

## 6. `chemin-app.php` : dire au site où est son code

Dans `public_html/chores/`, crée le fichier `chemin-app.php` (« Nouveau fichier ») avec exactement :

```php
<?php return '/home/[compte]/domains/[domaine]/chores-app/app';
```

Le chemin complet de `chores-app` s'affiche en haut du gestionnaire de fichiers quand tu es dedans. Le
`.htaccess` du site refuse tout accès web à ce fichier.

## 7. `config.php` : la configuration secrète

Dans `chores-app/`, copie `config.example.php` en **`config.php`**, ouvre-le et remplis :

```php
return [
    'SECRET' => '…les 64 caractères de l’étape 1…',
    'CLE_ADMIN' => '…les 43 caractères de l’étape 1…',
    'BASE_PATH' => '/chores',              // '' pour un sous-domaine
    'URL_PUBLIQUE' => 'https://[domaine]', // 'https://chores.[domaine]' pour un sous-domaine
    'TAILLE_MAX_UPLOAD_MO' => 200,         // ≤ upload_max_filesize et post_max_size de l’étape 2
    'ESPACE_MAX_MO' => 5000,               // place que tu donnes aux vidéos
    'ENV' => 'production',                 // jamais 'dev' en ligne
];
```

## 8. Droits

Dans le gestionnaire de fichiers (clic droit › « Permissions ») :

- crée le dossier `chores-app/stockage/` et donne-lui **750** (le site y écrit la base, les vidéos et les
  sauvegardes ; il crée lui-même `medias/` et `sauvegardes/`) ;
- `chores-app/config.php` : **600** (lisible par PHP seul ; si le site affiche ensuite « Oups, petit faux pas »,
  essaie 640) **[à vérifier]** ;
- le reste garde les droits du gestionnaire (dossiers 755, fichiers 644).

## 9. Préparer la base, importer les faits du groupe, créer le premier lien

### Avec le SSH (hPanel › « Avancé » › « Accès SSH » : activer, puis se connecter avec la commande affichée)

```
cd ~/domains/[domaine]/chores-app
php -v                                  # doit dire PHP 8.3 ; sinon : /opt/alt/php83/usr/bin/php [à vérifier]
php scripts/migrer.php                  # « Migrations appliquées : 001-socle, 002-donnees, 003-groupe »
php scripts/importer-etat.php           # « Import fait. Ajouts : 8 chanson(s), 23 lien(s), 7 répétition(s)… »
php scripts/nouveau-lien.php            # affiche le lien secret : copie-le
```

### Sans SSH : la page d'installation

Ouvre `https://[domaine]/chores/installer`, colle `CLE_ADMIN` dans le champ « Clé d'administration » et touche
« Installer le site ». La page prépare la base, importe les faits du groupe (`donnees/`), crée le premier lien, le
montre une seule fois et ouvre l'administration sur cet appareil. Dès qu'un lien existe, `/installer` répond
« Page introuvable ».

## 10. Ouvrir l'administration et déposer les vidéos de référence

1. Touche le lien secret, choisis ton prénom.
2. Sur chaque appareil où tu veux l'administration (inutile sur celui qui a servi à `/installer`), visite une fois
   `https://[domaine]/chores/admin/cle/[CLE_ADMIN]`.
3. Convertis chaque vidéo de référence sur ton PC (`docs/VIDEOS.md` § 4 : H.264 720p, `-crf 23`, `+faststart`), puis
   dépose-la dans `/admin/chansons/[chanson]` › « Vidéo de référence ».
4. Pose les sections, les placements, la date et le lieu (`docs/ADMIN.md`).

## 11. Vérifier

Suis `docs/deploy/verification.md` (en-têtes avec `curl.exe`, `noindex`, lien, `/m/` refusé sans cookie, vraie vidéo,
installation sur l'écran d'accueil), puis les tests sur téléphone de `docs/A-FAIRE-PAR-PATRICE.md`.

## 12. Ensuite

- Envoyer le lien : `docs/deploy/lien.md` (texte prêt, Jessica et Stéphane hors WhatsApp).
- Sauvegardes : `docs/deploy/sauvegardes.md` (tâche planifiée et téléchargement mensuel).
- Version suivante : `docs/deploy/mise-a-jour.md` (sans perdre `stockage/`).

## En cas de souci

| Symptôme | Piste |
|---|---|
| « Oups, petit faux pas » sur toutes les pages | `config.php` illisible (droits) ou incomplet (`SECRET` < 32 caractères), ou `stockage/` sans droit d'écriture ; hPanel › « Avancé » › « Journaux d'erreurs PHP » **[à vérifier]** dit lequel (lignes `[chores]`). |
| Page blanche ou erreur 500 du serveur | `chemin-app.php` faux (le code n'est pas trouvé), PHP n'est pas en 8.3, ou `pdo_sqlite` manque (étapes 2 et 6). |
| « Petite pause : le site est en maintenance » qui ne part pas | Une restauration a été interrompue : supprime `chores-app/stockage/maintenance.actif` (`docs/deploy/sauvegardes.md`). |
| « Page introuvable » sur `/chores/installer` | Un lien existe déjà (installation faite), ou `CLE_ADMIN` fait moins de 32 caractères. |
| Pages sans mise en forme | `public_html/chores/.htaccess` manque (fichier caché non déplacé) ou `BASE_PATH` ne correspond pas à l'adresse. |
| « Fichier trop lourd » | `post_max_size` ou `upload_max_filesize` plus petits que `TAILLE_MAX_UPLOAD_MO` (étape 2). |
