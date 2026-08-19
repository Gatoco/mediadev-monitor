# Mediadev Monitor — non-wp: sitio estático (nginx) que NO es WordPress.
#
# Emula un sitio no-WP real: `/` devuelve 200, `/wp-json/` devuelve 404
# (RF-10). Degradation::classify() detecta 404 en /wp-json/ → NON_WP.

FROM nginx:alpine

COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY index.html /usr/share/nginx/html/index.html
