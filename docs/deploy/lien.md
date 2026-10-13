# Le lien secret : l'envoyer, le changer

Un seul lien pour tout le monde. Il n'est gardé nulle part en clair (la base n'en garde que l'empreinte) : il
s'affiche une seule fois, au moment où il est créé.

## 1. Créer (ou changer) le lien

`/admin` › « Nouveau lien » › « Créer un nouveau lien » (ou `php scripts/nouveau-lien.php` en SSH).

Ce qui se passe aussitôt :

- l'ancien lien ne marche plus, pour personne ;
- tout le monde (toi compris) doit toucher le nouveau lien une fois, puis rechoisir son prénom ;
- les téléphones qui reviennent avec l'ancien accès voient « Lien à demander » et oublient ce qu'ils gardaient du
  site (pages et vidéos gardées hors ligne) ;
- les données (niveaux, placements, vidéos, commentaires) ne bougent pas.

Change le lien s'il a circulé hors des cousins, si un téléphone est perdu, ou si les journaux de l'hébergeur ont
été partagés.

## 2. Le texte à envoyer

La page « Nouveau lien créé » propose un texte prêt à coller ; le voici, pour mémoire :

> Voici le nouveau lien des Chorés du mariage : https://[domaine]/chores/e/… — touche-le une fois, puis choisis ton
> prénom. Garde-le entre cousins, ne le partage pas ailleurs.

Pour une première fois, tu peux ajouter :

> Sur iPhone : Partager › « Sur l'écran d'accueil » ; sur Android : menu › « Installer l'application ». Avant de
> filmer une vidéo pour le groupe, règle ton iPhone sur « Le plus compatible » (Réglages › Appareil photo ›
> Formats).

(Le réglage iPhone est détaillé dans `docs/VIDEOS.md` § 1.)

## 3. À qui l'envoyer

- Le groupe WhatsApp « Danse Mariage » : Alex, Catherine, Fabien, Flobert, Josiane, Nadine, Patrice. Poster le
  lien dans le groupe le donne à tous ses membres. Si ce n'est pas voulu, envoie le lien à chacun en message privé
  **[à décider par toi]**.
- **Jessica et Stéphane ne sont pas dans le groupe WhatsApp** : envoie-le leur à part, par le moyen que tu utilises
  avec eux **[à choisir par toi]**, et confirme qu'ils sont d'accord pour figurer sur le site
  (`docs/A-FAIRE-PAR-PATRICE.md`).

Le site n'envoie rien lui-même (ni e-mail, ni SMS, ni notification).

## 4. La clé d'administration

Distincte du lien : elle est dans `config.php` (`CLE_ADMIN`) et s'utilise une fois par appareil en visitant
`https://[domaine]/chores/admin/cle/[CLE_ADMIN]` (après être entré par le lien). Elle passe dans les journaux
d'accès de l'hébergeur : une fois posée sur tes appareils, tu peux la changer dans `config.php` (nouvelle valeur
générée comme à l'étape 1 de `docs/deploy/README.md`) ; tous les appareils devront revisiter la nouvelle adresse.
