FROM php:8.2-apache

# Extensions PHP (pdo_mysql)
RUN set -eux; \
    apt-get update && apt-get install -y --no-install-recommends libzip-dev zip unzip; \
    docker-php-ext-install mysqli pdo_mysql; \
    a2enmod rewrite auth_basic authn_file authz_user; \
    rm -rf /var/lib/apt/lists/*

# Confs Apache (remplace tes volumes)
COPY apache/limits.conf /etc/apache2/conf-enabled/limits.conf
COPY apache/servername.conf /etc/apache2/conf-enabled/servername.conf
COPY apache/app.conf /etc/apache2/conf-enabled/app.conf

# Code (remplace .:/var/www/html)
WORKDIR /var/www/html
COPY . /var/www/html

# S'assurer que le script d'initialisation est exécutable
RUN chmod +x /var/www/html/upload-data-railway.php

# Apache doit écouter sur $PORT (imposé par Railway)
ENV PORT=8080
RUN sed -ri 's!Listen 80!Listen ${PORT}!g' /etc/apache2/ports.conf \
 && sed -ri 's!<VirtualHost \*:80>!<VirtualHost \*:${PORT}>!g' /etc/apache2/sites-available/000-default.conf

# Entrypoint : lance le script d'init DB puis Apache
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080
CMD ["/entrypoint.sh"]
