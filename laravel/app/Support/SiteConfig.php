<?php

namespace App\Support;

use RuntimeException;

/**
 * Loads the legacy repo-root config files (config/sites.php, config/auth.php)
 * that the vanilla app used as the source of truth. Keeps bin/e2e-assert.sh
 * seeding workflow working unchanged: it writes config/sites.php and Laravel
 * reads it from here.
 */
final class SiteConfig
{
    /**
     * @return array<int, array{url:string, name:string, type:string, wp_user:?string, token:?string}>
     *
     * @throws RuntimeException when the sites file exists but is invalid
     *                          (parity with the vanilla exit-code 2 contract).
     */
    public static function sites(): array
    {
        $file = config('mediadev.sites_file');

        if (!is_file($file)) {
            return [];
        }

        try {
            $sites = require $file;
        } catch (\ParseError $e) {
            throw new RuntimeException("config/sites.php inválido: {$e->getMessage()}", 2);
        }

        return is_array($sites) ? $sites : [];
    }

    /** @return array{username:string, password_hash:string} */
    public static function auth(): array
    {
        $file = config('mediadev.auth_file');

        if (!is_file($file)) {
            return ['username' => '', 'password_hash' => ''];
        }

        try {
            $auth = require $file;
        } catch (\ParseError $e) {
            throw new RuntimeException("config/auth.php inválido: {$e->getMessage()}", 2);
        }

        return is_array($auth) ? $auth : ['username' => '', 'password_hash' => ''];
    }
}
