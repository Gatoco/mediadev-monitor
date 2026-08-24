# Mediadev Monitor — portfolio-emulator: 28 sitios reales, 1 contenedor.
#
# Emula los sitios del portafolio de MediaDev (verificado contra mediadev.cl,
# 2026-08-24) respondiendo según el Host header. El monitor los consulta por
# su nombre real (http://<sitio>:80) gracias a los network aliases del compose.

FROM php:8.3-cli

COPY router.php /app/router.php

# El monitor consulta http://<sitio>:80 — escuchar en 80, no en el default.
EXPOSE 80
CMD ["php", "-S", "0.0.0.0:80", "/app/router.php"]
