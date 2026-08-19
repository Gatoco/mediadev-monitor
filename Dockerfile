# Mediadev Monitor — Dockerfile

FROM php:8.3-cli

# Extensiones necesarias
# libsqlite3-dev + pkg-config son necesarios para compilar pdo_sqlite en php:8.3-cli.
RUN apt-get update && apt-get install -y --no-install-recommends \
        cron \
        curl \
        unzip \
        libsqlite3-dev \
        pkg-config \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Código
COPY composer.json ./
COPY src/ src/
COPY bin/ bin/
COPY config/sites.example.php config/sites.example.php
COPY config/auth.example.php config/auth.example.php
COPY web/ web/

# OQ3: inyectar el cache de última versión estable de WP para que
# VersionTracker::latestStableWp() devuelva 7.0.4 (en lugar del fallback 6.6.2).
# El volumen mediadev-data cubre /var/lib/mediadev/data, así que colocamos el
# cache en /app (no sombreado) y VersionTracker lo consulta como segunda opción.
COPY docker/fixture-wp/wp-latest-version.cache.json /app/wp-latest-version.cache.json

# Instalar dependencias (vendor/autoload.php)
RUN composer install --no-dev --no-interaction --optimize-autoloader

# Datos (volume)
RUN mkdir -p /var/lib/mediadev/data
ENV MEDIADEV_DB_PATH=/var/lib/mediadev/data/mediadev.sqlite

# Cron: uptime cada 5 min, deep cada 6h
COPY crontab /etc/cron.d/mediadev
RUN chmod 0644 /etc/cron.d/mediadev \
    && crontab /etc/cron.d/mediadev

# Dashboard
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app/web"]
