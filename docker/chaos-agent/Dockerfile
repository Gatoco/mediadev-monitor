# SemaOPS — chaos-agent: API mínima sobre el socket de Docker (solo perfil e2e).
FROM php:8.3-cli

COPY agent.php /app/agent.php

EXPOSE 8090
CMD ["php", "-S", "0.0.0.0:8090", "/app/agent.php"]
