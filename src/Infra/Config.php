<?php

/**
 * Mediadev Monitor — Infra: Configuración.
 *
 * Carga config/sites.php y config/auth.php (gitignored) con
 * overrides por variables de entorno (MEDIADEV_DB_PATH).
 */

declare(strict_types=1);

namespace MediadevMonitor\Infra;

final class Config
{
    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? dirname(__DIR__, 2);
    }

    public function dbPath(): string
    {
        return getenv('MEDIADEV_DB_PATH')
            ?: $this->root . '/data/mediadev.sqlite';
    }

    /**
     * @return array<int, array{url:string, name:string, type:string, wp_user:?string, token:?string}>
     * @throws \RuntimeException si config/sites.php existe pero es inválido (EV-05 → exit 2)
     */
    public function sites(): array
    {
        $file = $this->root . '/config/sites.php';
        if (!is_file($file)) {
            return [];
        }
        try {
            $sites = require $file;
        } catch (\ParseError $e) {
            throw new \RuntimeException("config/sites.php inválido: {$e->getMessage()}", 2);
        }
        return $sites;
    }

    /** @return array{username:string, password_hash:string} */
    public function auth(): array
    {
        $file = $this->root . '/config/auth.php';
        if (!is_file($file)) {
            return ['username' => '', 'password_hash' => ''];
        }
        try {
            $auth = require $file;
        } catch (\ParseError $e) {
            throw new \RuntimeException("config/auth.php inválido: {$e->getMessage()}", 2);
        }
        return $auth;
    }
}
