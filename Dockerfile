FROM php:8.3-apache
RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite headers
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html
