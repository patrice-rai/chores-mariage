# Sauvegardes et restauration

Tout ce qui compte vit dans `chores-app/stockage/` : la base `base.sqlite` (cousins, niveaux, placements,
répétitions, commentaires, journal) et `medias/` (vidéos de référence et vidéos des cousins). `config.php` (les
secrets) est à garder aussi, à part. La restauration a été essayée en local sur une base vide, par le script et par
l'administration (résultats dans `docs/RECETTE.md`, « Sauvegarde et restauration »).

## 1. Faire une sauvegarde de la base

Trois façons, au choix ; chacune écrit `stockage/sauvegardes/base-AAAA-MM-JJ-HHMMSS.sqlite` sans arrêter le site
(SQLite `VACUUM INTO` : copie cohérente, même pendant qu'un cousin écrit).

- **Administration** : `/admin` › « Base et sauvegardes » › « Faire une sauvegarde maintenant ». Les 12 plus
  récentes sont gardées, les plus anciennes effacées.
- **SSH** : `cd ~/domains/[domaine]/chores-app && php scripts/sauvegarde.php` (rien n'est effacé ; ajoute
  `--garder=12` pour ne garder que les 12 plus récentes).
- **Tâche planifiée** (sans y penser) : hPanel › « Avancé » › « Tâches Cron » **[à vérifier]**, une tâche par
  semaine (par exemple le dimanche à 4 h) avec la commande :

  ```
  php /home/[compte]/domains/[domaine]/chores-app/scripts/sauvegarde.php --garder=12
  ```

  Si hPanel demande le chemin de PHP : `/usr/bin/php` ou `/opt/alt/php83/usr/bin/php` **[à vérifier]**.

Les sauvegardes gardent aussi le journal : une sauvegarde de plus d'un an garde des lignes que le site a purgées
depuis. Le dossier `sauvegardes/avant-restauration/` (copies faites avant chaque restauration) garde les 5 plus
récentes.

## 2. Télécharger `stockage/` (une fois par mois, et la veille du mariage)

Les sauvegardes restent chez Hostinger : une copie sur ton PC protège d'une erreur de manipulation ou d'un souci de
compte.

1. Fais d'abord une sauvegarde (§ 1).
2. Gestionnaire de fichiers › `chores-app/` › clic droit sur `stockage` › « Compresser » (zip) ; puis clic droit
   sur l'archive › « Télécharger » ; puis supprime l'archive du serveur.
3. Range le zip sur ton PC hors de tout dossier synchronisé publiquement ; il contient les vidéos des cousins.
4. Garde aussi une copie de `chores-app/config.php` (sans elle, les cookies de tout le monde tombent à la
   restauration : chacun retouche le lien).

Dans l'archive, seul `sauvegardes/base-…sqlite` est garanti cohérent : `base.sqlite` lui-même peut être copié en
pleine écriture.

## 3. Restaurer la base

La restauration remplace **la base seulement** ; les vidéos ne bougent pas (une vidéo dont la ligne n'existe plus
dans la base restaurée n'apparaît plus, elle reste sur le disque). Pendant les quelques secondes de l'opération, le
site répond « Petite pause : le site est en maintenance » au lieu d'ouvrir la base. **Le lien secret en service est
gardé** : personne n'est déconnecté, même si la sauvegarde date d'avant un « Nouveau lien » (seule exception : si
la base remplacée était vide ou illisible, c'est le lien de la sauvegarde qui remarche ; le script le dit).
Ce qui a été supprimé depuis la sauvegarde (commentaire, vidéo) revient ; une vidéo envoyée depuis reste sur le
disque sans apparaître.

### Depuis l'administration (conseillé, sans SSH)

`/admin` › « Base et sauvegardes » › « Restaurer » sur la ligne voulue, puis « Oui, restaurer ». La base actuelle
est d'abord copiée dans `sauvegardes/avant-restauration/` ; une sauvegarde abîmée est refusée et la base actuelle ne
bouge pas. Pour restaurer une sauvegarde gardée sur ton PC, envoie-la d'abord dans `chores-app/stockage/sauvegardes/`
**sans changer son nom** (`base-AAAA-MM-JJ-HHMMSS.sqlite`) : elle apparaît dans la liste. Restaure-la aussitôt,
**sans faire de sauvegarde entre-temps** : comme elle est plus ancienne que les autres, elle serait la première
effacée si la liste dépasse 12.

Si l'écran dit « une mise à jour de la base a échoué ensuite », la sauvegarde est en place : touche « Mettre la base
à jour » sur la même page (ou `php scripts/migrer.php` en SSH).

### Avec le SSH

```
cd ~/domains/[domaine]/chores-app
php scripts/restaurer.php stockage/sauvegardes/base-AAAA-MM-JJ-HHMMSS.sqlite --oui
```

Le script fait la même chose que l'administration et écrit « Base restaurée depuis … (9 cousins). », puis dit si le
lien en service est gardé.

### En dernier recours, à la main (gestionnaire de fichiers)

Seulement si l'administration ne s'ouvre plus. Dans `chores-app/stockage/`, dans cet ordre :

1. Crée un fichier vide nommé `maintenance.actif` : le site répond « maintenance » et n'ouvre plus la base.
2. Envoie la sauvegarde dans `stockage/` sous le nom `base-a-remettre.sqlite`.
3. Renomme `base.sqlite` en `base-remplacee.sqlite`, et, s'ils existent, `base.sqlite-wal` en
   `base-remplacee.sqlite-wal` et `base.sqlite-shm` en `base-remplacee.sqlite-shm` (ils vont ensemble).
4. Renomme `base-a-remettre.sqlite` en `base.sqlite`.
5. Supprime `maintenance.actif`, puis ouvre `/admin` › « Base et sauvegardes » : si des mises à jour sont en
   attente, touche « Mettre la base à jour ».

À la main, le lien n'est pas gardé : si la sauvegarde date d'avant un « Nouveau lien », c'est l'ancien lien qui
redevient bon (cherche-le dans WhatsApp), puis crée un nouveau lien (`docs/deploy/lien.md`).

### Après une restauration

Les niveaux, présences et commentaires postés depuis la sauvegarde sont perdus : préviens le groupe.

## 4. Repartir de zéro (dernier recours)

Renomme `stockage/` en `stockage-ancien/`, crée un `stockage/` vide (droits 750), puis refais l'étape 9 de
`docs/deploy/README.md` (migrations, import, premier lien, ou `/installer`).
