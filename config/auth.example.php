<?php

/**
 * Mediadev Monitor — autenticación del dashboard (plantilla).
 *
 * Copia este archivo a config/auth.php y define usuario + hash de contraseña.
 * config/auth.php está en .gitignore — NUNCA subir hashes al repositorio.
 *
 * Generar el hash: php -r "echo password_hash('tu-clave', PASSWORD_DEFAULT);"
 */

return [
    'username' => 'admin',
    'password_hash' => '', // password_hash() result
];
