#!/bin/sh
# Mediadev Monitor — wp-cli oneshot: crea usuario + Application Password.
#
# Se ejecuta dentro de un contenedor `wordpress:cli` (mediadev-wp-cli-<fixture>).
# Es idempotente: re-ejecutar no falla y no crea duplicados.
#
# Variables:
#   FIXTURE_NAME   nombre del fixture (ej: wp-full) → escribe /ap-tokens/<name>.token
#   WP_URL         URL interna del fixture (ej: http://wp-full)
#
# Entrada (stdout): el Application Password token (para pegar en config/sites.php).
# Salida (stderr): log de progreso. El token se persiste en /ap-tokens/.

set -euo pipefail

WP_USER="monitor"
WP_EMAIL="monitor@example.com"
WP_ROLE="administrator"
WP_PASS="monitorpass"
AP_NAME="mediadev-monitor"

# Parámetros del fixture (defaults para compatibilidad con el stack antiguo).
FIXTURE_NAME="${FIXTURE_NAME:-wordpress}"
WP_URL="${WP_URL:-http://wordpress}"

echo "» wp-bootstrap (${FIXTURE_NAME}): verificando instalación de WP..." >&2

# Esperar a que wp-config.php esté presente (volumen compartido con el fixture).
i=0
while [ "$i" -lt 45 ]; do
    if [ -f /var/www/html/wp-config.php ]; then
        break
    fi
    echo "  wp-config.php aún no presente, esperando 2s..." >&2
    sleep 2
    i=$((i + 1))
done

if [ ! -f /var/www/html/wp-config.php ]; then
    echo "ERROR: wp-config.php no encontrado tras 90s" >&2
    exit 1
fi

# Si WP no está instalado (tablas no creadas), correr la instalación.
# Usar la URL interna del contenedor del fixture sin puerto (el puerto 80 es
# default; con :80 WP entra en redirect loop).
if ! wp core is-installed 2>/dev/null; then
    echo "  WP no instalado — ejecutando wp core install..." >&2
    wp core install \
        --url="${WP_URL}" \
        --title="Mediadev Monitor E2E (${FIXTURE_NAME})" \
        --admin_user="admin" \
        --admin_password="adminpass" \
        --admin_email="admin@example.com" >&2
    echo "  WP core instalado (siteurl=${WP_URL})" >&2
fi

echo "» wp-bootstrap: asegurando usuario '${WP_USER}'..." >&2
if wp user get "${WP_USER}" >/dev/null 2>&1; then
    echo "  usuario '${WP_USER}' ya existe — reutilizando" >&2
else
    wp user create "${WP_USER}" "${WP_EMAIL}" \
        --role="${WP_ROLE}" \
        --user_pass="${WP_PASS}" \
        --display_name="Monitor E2E" >&2
    echo "  usuario '${WP_USER}' creado (role=${WP_ROLE})" >&2
fi

# Application Passwords: listar existentes y reutilizar si ya hay una con el
# mismo nombre. `wp user application-password list` existe en wp-cli >= 2.5.
echo "» wp-bootstrap: asegurando Application Password '${AP_NAME}'..." >&2

# Si ya existe el token en el volumen, reutilizarlo (idempotencia total).
TOKEN_FILE="/ap-tokens/${FIXTURE_NAME}.token"
if [ -f "${TOKEN_FILE}" ] && [ -s "${TOKEN_FILE}" ]; then
    EXISTING_TOKEN=$(tr -d '[:space:]' < "${TOKEN_FILE}")
    if [ -n "${EXISTING_TOKEN}" ]; then
        echo "  token ya persistido en ${TOKEN_FILE} — reutilizando (idempotente)" >&2
        echo "${EXISTING_TOKEN}"
        exit 0
    fi
fi

# Intentar listar APs existentes para el usuario. Si ya hay alguna con nuestro
# nombre, la eliminamos y creamos una nueva (el password hasheado no se puede ver).
EXISTING=$(wp user application-password list "${WP_USER}" --format=json 2>&1 || echo "[]")

# Extraer todos los UUIDs de APs con el mismo nombre y eliminarlos.
if command -v jq >/dev/null 2>&1; then
    APP_IDS=$(printf '%s' "${EXISTING}" | jq -r '.[] | select(.name=="'"${AP_NAME}"'") | .uuid' 2>/dev/null)
else
    # Fallback sin jq: grep burdo. El uuid es un UUID v4.
    # `|| true` evita que set -e abort si no hay coincidencias (grep rc=1).
    APP_IDS=$(printf '%s' "${EXISTING}" | grep -oE '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}' 2>/dev/null || true)
fi

if [ -n "${APP_IDS}" ]; then
    echo "${APP_IDS}" | while IFS= read -r APP_ID; do
        if [ -n "${APP_ID}" ]; then
            echo "  eliminando AP vieja (uuid=${APP_ID})..." >&2
            wp user application-password delete "${WP_USER}" "${APP_ID}" --yes >/dev/null 2>&1 || true
        fi
    done
fi

# Crear la AP y capturar el password (solo se muestra una vez).
# --porcelain imprime solo el password a stdout.
TOKEN=$(wp user application-password create "${WP_USER}" "${AP_NAME}" --porcelain 2>/dev/null) || true

if [ -z "${TOKEN}" ]; then
    echo "ERROR: no se pudo crear el Application Password" >&2
    exit 1
fi

# Limpiar espacios (WP devuelve el token con espacios cada 4 chars).
CLEAN_TOKEN=$(printf '%s' "${TOKEN}" | tr -d ' ')

# Persistir el token en el volumen compartido para e2e-assert.sh.
mkdir -p /ap-tokens
printf '%s\n' "${CLEAN_TOKEN}" > "${TOKEN_FILE}"
echo "» wp-bootstrap: OK (token en ${TOKEN_FILE})" >&2
echo "${CLEAN_TOKEN}"
