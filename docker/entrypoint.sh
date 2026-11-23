#!/bin/sh
# SemaOPS — entrypoint del contenedor.
# 1. Forzar DB_DATABASE en .env (misma DB para web, cron y tinker).
# 2. Migraciones idempotentes (la DB vive en el volumen mediadev-data).
# 3. Cron en foreground (scheduler: uptime 5min / deep 6h).
# 4. Servidor del panel.

set -e

cd /app/laravel

# La DB por defecto del scaffold (database/database.sqlite) NO es la de
# producción: la real vive en el volumen mediadev-data. Sin esta línea el
# proceso web usa la DB de la imagen (0 usuarios) mientras cron/tinker usan
# el volumen — login rechaza con "credentials do not match" aunque sean
# válidas. Forzarla en .env hace que TODOS los procesos compartan la misma DB.
DB_FILE="${DB_DATABASE:-/var/lib/mediadev/data/mediadev.sqlite}"
if grep -q '^DB_DATABASE=' .env; then
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_FILE}|" .env
else
    printf 'DB_DATABASE=%s\n' "${DB_FILE}" >> .env
fi

# artisan serve y cron tampoco heredan las variables de la demo: van al .env igual que DB_DATABASE.
for VAR in DEMO_TOKEN DEMO_OFFLINE; do
    sed -i "/^${VAR}=/d" .env
    VAL="$(printenv "$VAR" || true)"
    [ -n "$VAL" ] && printf '%s=%s\n' "$VAR" "$VAL" >> .env
done

mkdir -p "$(dirname "$DB_FILE")" && touch "$DB_FILE"
php artisan migrate --force

# Demo sin internet: telemetría sintética en vez de checks reales.
if [ -n "${DEMO_OFFLINE:-}" ]; then
    php artisan demo:seed
fi

# Cron NO hereda las env de compose (PATH mínimo). Con DB_DATABASE ya en
# .env, schedule:run usa la misma DB que el web — solo falta php absoluto.
cat > /etc/cron.d/mediadev <<EOF
# SemaOPS — cron (generado por entrypoint con env del runtime)
* * * * * cd /app/laravel && /usr/local/bin/php artisan schedule:run >> /var/log/mediadev-scheduler.log 2>&1
EOF
chmod 0644 /etc/cron.d/mediadev
crontab /etc/cron.d/mediadev

# Cron debe correr en foreground para que el contenedor no muera.
/usr/sbin/cron -f &
CRON_PID=$!

# Señal limpia para cron al terminar.
trap 'kill $CRON_PID 2>/dev/null || true' INT TERM EXIT

# ponytail: servidor embebido de PHP; 4 workers para que kiosko + otros equipos no se bloqueen entre sí.
export PHP_CLI_SERVER_WORKERS=4
exec php artisan serve --host=0.0.0.0 --port=8080
