FROM php:8.4-apache

RUN docker-php-ext-install pdo_pgsql

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
