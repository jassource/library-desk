FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

# Backend API + frontend served from the same site
COPY backend/ /var/www/html/
COPY index.html STYLE.CSS extra.css script.js /var/www/html/

EXPOSE 80
