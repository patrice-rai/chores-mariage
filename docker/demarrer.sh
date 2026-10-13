#!/bin/sh
# Démarrage du conteneur de développement : dossier de stockage (volume nommé), configuration locale générée si
# elle manque (secrets aléatoires, jamais affichés), migrations, puis Apache au premier plan.
set -eu
cd /var/www/app
mkdir -p stockage/medias stockage/sauvegardes
chown -R www-data:www-data stockage
php scripts/config-dev.php
su -s /bin/sh www-data -c 'php scripts/migrer.php'
exec apache2-foreground
