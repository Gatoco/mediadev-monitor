-- Mediadev Monitor — E2E: base de datos por fixture WordPress.
-- Se monta en /docker-entrypoint-initdb.d/ del servicio mysql compartido.
-- Cada fixture WP usa su propia base vía WORDPRESS_DB_NAME.
--
-- NOTA: mysql:8.0 solo otorga privilegios al usuario MYSQL_USER sobre
-- MYSQL_DATABASE (wp_full por defecto). Las bases adicionales creadas aquí
-- requieren GRANT explícito al usuario 'wp' para que wp-cli y los fixtures
-- puedan conectar.

CREATE DATABASE IF NOT EXISTS wp_full;
CREATE DATABASE IF NOT EXISTS wp_outdated;
CREATE DATABASE IF NOT EXISTS wp_hardened;

-- Conceder privilegios al usuario de la aplicación sobre las 3 bases WP.
GRANT ALL PRIVILEGES ON wp_full.* TO 'wp'@'%';
GRANT ALL PRIVILEGES ON wp_outdated.* TO 'wp'@'%';
GRANT ALL PRIVILEGES ON wp_hardened.* TO 'wp'@'%';
FLUSH PRIVILEGES;
