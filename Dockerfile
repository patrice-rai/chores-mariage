# Image de développement local du site « Chorés du mariage » (jamais déployée : la production est l'hébergement
# mutualisé Hostinger de Patrice). PHP 8.3 + Apache, racine web sur public/, comme public_html chez Hostinger.
# Images épinglées par empreinte (relevées le 2026-10-13, déjà présentes sur le poste).
FROM php:8.3-apache@sha256:7a33fa6c555763fa384c727ab7377c4807f628daec0660eda705957a905f707f

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip git; \
    docker-php-ext-configure gd --with-jpeg --with-freetype; \
    docker-php-ext-install -j"$(nproc)" gd opcache; \
    apt-get clean; \
    php -r 'exit(extension_loaded("pdo_sqlite") && extension_loaded("mbstring") && extension_loaded("fileinfo") && extension_loaded("gd") ? 0 : 1);'

# Composer (outillage de test seulement : PHPUnit), copié de l'image officielle.
COPY --from=composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac /usr/bin/composer /usr/bin/composer

RUN set -eux; \
    a2enmod rewrite headers; \
    a2disconf other-vhosts-access-log

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-chores.ini
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/demarrer.sh /usr/local/bin/chores-demarrer

WORKDIR /var/www/app
CMD ["sh", "/usr/local/bin/chores-demarrer"]
