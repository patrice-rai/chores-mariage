---
paths:
  - 'app/**'
  - 'public/**'
  - 'scripts/**'
---

# Vie privée et sécurité (spec § 6, 10, 11)

- Rien ne sort du serveur : aucune ressource externe (police, script, image, iframe, CDN, mesure d'audience), aucun e-mail. Les liens YouTube sont sortants (`rel="noopener noreferrer"`), jamais intégrés.
- Les vidéos des cousins vivent dans `stockage/medias/` (hors racine web, ignoré par git), servies par `/m/{id}` après contrôle du cookie ; jamais copiées ailleurs ; aucune conversion ni `exec` sur le serveur.
- Accès : un seul lien secret (SHA-256 en base), cookies signés HMAC (`cm_acces`, `cm_prenom`, `cm_admin`, `cm_csrf`), comparaison en temps constant (`hash_equals`), `HttpOnly`, `SameSite=Lax`, `Secure` hors développement, `Path = BASE_PATH`.
- Administration : 404 sans `cm_admin` ; aucun compte, aucun mot de passe par personne.
- CSRF sur tout `POST` ; requêtes préparées partout ; `e()` sur toute valeur affichée ; validation serveur de toute entrée (longueurs, ensembles fermés, bornes).
- En-têtes de `app/securite.php` sur toute réponse PHP, et de `public/.htaccess` sur les fichiers statiques ; `noindex` partout.
- Journal : type, prénom, détail court, date UTC ; jamais d'IP, jamais de user-agent ; purge à un an. Table `debit` : hachés salés par jour seulement.
- Secrets : `config.php` jamais lu, écrit ni affiché par l'agent (seul `scripts/config-dev.php` le génère en local) ; aucun secret dans un message, un journal, une capture ou un test.
