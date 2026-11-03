FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

COPY api.php config.php index.html STYLE.CSS extra.css script.js /var/www/html/

EXPOSE 80
