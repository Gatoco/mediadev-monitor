<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mediadev Monitor — Legacy config bridge
    |--------------------------------------------------------------------------
    |
    | The vanilla app reads sites and auth from repo-root config/*.php files
    | (gitignored, seeded by bin/e2e-assert.sh). Laravel reads its own config
    | from the .env, so this bridge keeps the legacy files as the source of
    | truth for the monitored sites. The file is resolved relative to the
    | project root (one level above this laravel/ directory).
    |
    */

    'sites_file' => dirname(__DIR__, 2) . '/config/sites.php',

    'auth_file' => dirname(__DIR__, 2) . '/config/auth.php',

    /*
    | Directory for runtime caches (e.g. wp-latest-version.cache.json).
    | Replaces the vanilla `data/mediadev.sqlite.cache` convention.
    */

    'cache_dir' => storage_path('app/mediadev'),
];
