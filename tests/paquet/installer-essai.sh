#!/bin/sh
# Essai du paquet, dans le conteneur jetable chores-mariage-paquet : on suit docs/deploy/README.md comme le ferait
# Patrice dans le gestionnaire de fichiers de Hostinger (étapes 5 à 8), avec le zip seul (rien d'autre du dépôt,
# sauf scripts/config-dev.php pour générer la configuration d'essai sans afficher ses secrets).
set -eu
H=/home/u000000000/domains/exemple.test
mkdir -p "$H/public_html"
cd "$H"
# Étape 5 : décompresser, renommer en chores-app, déplacer public/ dans public_html/ sous le nom chores.
unzip -q /tmp/essai/paquet.zip
mv chores-mariage-v*/ chores-app
mv chores-app/public public_html/chores
# Étape 6 : chemin-app.php, qui pointe hors de la racine web.
printf "<?php return '%s';\n" "$H/chores-app/app" > public_html/chores/chemin-app.php
# Étape 7 : configuration (secrets aléatoires, jamais affichés), BASE_PATH=/chores, ENV=production.
php /tmp/essai/config-dev.php --dossier="$H/chores-app" --base=/chores --url=https://127.0.0.1:8096 --env=production
# Étape 8 : stockage/ en 750, configuration en 600 ; tout appartient au compte qui fait tourner PHP.
mkdir chores-app/stockage
# Repli 5 bis imité : un dossier d'application sous la racine web, protégé par le .htaccess du paquet.
mkdir public_html/repli-essai
cp chores-app/.htaccess chores-app/VERSION public_html/repli-essai/
chown -R www-data:www-data /home/u000000000
chmod 750 chores-app/stockage
chmod 600 chores-app/config.php
# HTTPS de l'essai : certificat auto-signé de deux jours, pour 127.0.0.1.
mkdir -p /etc/ssl/essai
openssl req -x509 -newkey rsa:2048 -nodes -days 2 -subj "/CN=127.0.0.1" -addext "subjectAltName=IP:127.0.0.1" \
    -keyout /etc/ssl/essai/cle.pem -out /etc/ssl/essai/cert.pem 2>/dev/null
cp /tmp/essai/vhost-essai.conf /etc/apache2/sites-available/000-default.conf
a2enmod ssl >/dev/null
echo "Arborescence prête."
