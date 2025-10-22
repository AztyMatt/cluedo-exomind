FROM php:8.2-apache

# Install only PHP extensions (no MySQL client needed)
RUN docker-php-ext-install mysqli pdo_mysql && \
    a2enmod rewrite
