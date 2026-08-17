# Mediadev Monitor — Dockerfile

FROM php:8.3-cli

# Extensiones necesarias
RUN docker-php-ext-install pdo pdo_sqlite \
    && apt-get update && apt-get install -y --no-install-recommends \
        cron \
        curl \
        unzip \
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
