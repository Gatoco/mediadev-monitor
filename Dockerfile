# Mediadev Monitor — Dockerfile (Laravel 12 + Filament v4)

FROM php:8.3-cli

# Extensiones necesarias: pdo_sqlite para Eloquent, intl para Filament v4
# (paginación usa Number::format — sin intl el panel lanza excepción).
RUN apt-get update && apt-get install -y --no-install-recommends \
        cron \
        curl \
        unzip \
        libicu-dev \
        libsqlite3-dev \
        pkg-config \
    && docker-php-ext-install intl pdo pdo_sqlite \
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

# Instalar dependencias y generar APP_KEY
RUN composer install --no-dev --no-interaction --optimize-autoloader \
    && php artisan key:generate

# Scheduler: una sola línea en /etc/cron.d → schedule:run (uptime 5min, deep 6h)
COPY crontab /etc/cron.d/mediadev
RUN chmod 0644 /etc/cron.d/mediadev \
    && crontab /etc/cron.d/mediadev

# Panel Filament
EXPOSE 8080
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
