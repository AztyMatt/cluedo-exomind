FROM php:8.2-apache

# Install PHP extensions at build time (uses mysqlnd, no external libs needed)
RUN set -eux; \
    docker-php-ext-install mysqli pdo_mysql; \
    a2enmod rewrite
