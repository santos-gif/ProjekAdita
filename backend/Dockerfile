# syntax=docker/dockerfile:1
FROM php:8.2-apache

RUN tr -d '\r' <<'EOF' | sh -e
set -e
docker-php-ext-install pdo_mysql
a2enmod rewrite
a2dismod -f autoindex
sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf
printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/app.conf
a2enconf app
cat > /etc/apache2/sites-available/000-default.conf <<'CONF'
<VirtualHost *:8080>
    DocumentRoot /var/www/app
    <Directory /var/www/app>
        Options -Indexes -FollowSymLinks
        AllowOverride None
        Require all denied
        <Files "index.php">
            Require all granted
        </Files>
    </Directory>
    # Every URL goes to the front controller, so Core/, Features/ and database/ are never served raw.
    RewriteEngine On
    RewriteRule ^ /index.php [L]
</VirtualHost>
CONF
EOF

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY index.php /var/www/app/index.php
COPY Core /var/www/app/Core
COPY Features /var/www/app/Features
