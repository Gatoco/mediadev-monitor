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

    /** @return array<int, array{url:string, name:string, type:string, token:?string}> */
    public function sites(): array
    {
        $file = $this->root . '/config/sites.php';
        if (!is_file($file)) {
            return [];
        }
        return require $file;
    }

    /** @return array{username:string, password_hash:string} */
    public function auth(): array
    {
        $file = $this->root . '/config/auth.php';
        if (!is_file($file)) {
            return ['username' => '', 'password_hash' => ''];
        }
        return require $file;
    }
}
