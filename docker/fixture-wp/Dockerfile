# Mediadev Monitor — fixture-wp: imagen base para los 3 fixtures WordPress.
#
# Base: imagen oficial WordPress + Apache + PHP 8.3.
# El comportamiento del fixture se elige en runtime con la variable FIXTURE_TYPE:
#   full      → WordPress estándar (nada especial)
#   outdated  → emite <meta name="generator" content="WordPress 6.8.8">
#   hardened  → 401 en /wp/v2/users y /wp/v2/posts sin token; site-health 404
#
# Los mu-plugins se copian en la imagen; al montar el volumen /var/www/html vacío,
# Docker los copia automáticamente (populate-on-mount).

FROM wordpress:php8.3-apache

COPY fixture-mu.php /var/www/html/wp-content/mu-plugins/fixture-mu.php
COPY enable-app-passwords.php /var/www/html/wp-content/mu-plugins/enable-app-passwords.php
