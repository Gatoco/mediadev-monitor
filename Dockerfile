# SemaOPS — Dockerfile (Laravel 12)

FROM php:8.3-cli

# Extensiones necesarias: pdo_sqlite para Eloquent.
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

WORKDIR /app/laravel

# Código
COPY laravel/ .

# OQ3: inyectar el cache de última versión estable de WP para que
# VersionTracker::latestStableWp() devuelva 7.0.4 (en lugar del fallback 6.6.2).
# El port resuelve la ruta relativa a domain/Version/ → /app/laravel.
COPY docker/fixture-wp/wp-latest-version.cache.json /app/laravel/wp-latest-version.cache.json

# .env mínimo para el build (key:generate + cache de config)
COPY laravel/.env.example /app/laravel/.env

# Instalar dependencias y generar APP_KEY.
# Composer: timeout alto; --prefer-dist para paquetes zip (más rápido y
# menos frágil detrás de NAT); network=host en el build porque la bridge
# por defecto de este host no tiene salida a internet durante el build.
ENV COMPOSER_PROCESS_TIMEOUT=1200 \
    COMPOSER_ALLOW_SUPERUSER=1

RUN composer install --no-dev --no-interaction --optimize-autoloader \
        --prefer-dist \
    && php artisan key:generate

# Scheduler: una sola línea en /etc/cron.d → schedule:run (uptime 5min, deep 6h)
COPY crontab /etc/cron.d/mediadev
RUN chmod 0644 /etc/cron.d/mediadev \
    && crontab /etc/cron.d/mediadev

# Entrypoint: migraciones idempotentes sobre la DB del volumen + cron + panel.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Panel web
EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
