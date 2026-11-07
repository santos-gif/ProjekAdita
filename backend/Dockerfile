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
    # Every URL goes to the front controller, so Core/, Features/, Shared/ and database/ are never served raw.
    RewriteEngine On
    RewriteRule ^ /index.php [L]
</VirtualHost>
CONF
EOF

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Project thumbnails land here. Compose mounts a volume on this folder; an empty volume takes over the
# folder's owner (www-data) from the image, so Apache may write to it even with a read-only root filesystem.
RUN mkdir -p /var/www/app/uploads && chown www-data:www-data /var/www/app/uploads

# What may enter the image is decided by .dockerignore, not by a list here.
WORKDIR /var/www/app
COPY . .
