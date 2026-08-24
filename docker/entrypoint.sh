#!/bin/sh
# Mediadev Monitor — entrypoint del contenedor.
# 1. Migraciones idempotentes (la DB vive en el volumen mediadev-data).
# 2. Cron en foreground (scheduler: uptime 5min / deep 6h).
# 3. Servidor del panel Filament.

set -e

cd /app/laravel

php artisan migrate --force

# Cron NO hereda las env de compose (PATH mínimo, sin variables de entorno).
# Si DB_DATABASE viene de compose, se inyecta inline en la línea del cron;
# sin esto schedule:run resolvería la DB por defecto de la imagen
# (database/database.sqlite) y escribiría fuera del volumen mediadev-data.
CRON_CMD="cd /app/laravel && /usr/local/bin/php artisan schedule:run"
if [ -n "${DB_DATABASE:-}" ]; then
    CRON_CMD="cd /app/laravel && DB_DATABASE=${DB_DATABASE} /usr/local/bin/php artisan schedule:run"
fi

cat > /etc/cron.d/mediadev <<EOF
# Mediadev Monitor — cron (generado por entrypoint con env del runtime)
* * * * * ${CRON_CMD} >> /var/log/mediadev-scheduler.log 2>&1
EOF
chmod 0644 /etc/cron.d/mediadev
crontab /etc/cron.d/mediadev

# Cron debe correr en foreground para que el contenedor no muera.
/usr/sbin/cron -f &
CRON_PID=$!

# Señal limpia para cron al terminar.
trap 'kill $CRON_PID 2>/dev/null || true' INT TERM EXIT

exec php artisan serve --host=0.0.0.0 --port=8080
