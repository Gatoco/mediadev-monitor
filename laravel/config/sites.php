<?php

/**
 * Mediadev Monitor — canonical site registry.
 *
 * Source of truth for monitored sites. The artisan collectors read this
 * config, upsert the rows into the `sites` table (SiteRegistry::syncFromConfig),
 * and then run the domain Collector over the registry.
 *
 * Shape (mirrors Domain\Port\SiteRepository::syncFromConfig):
 *   url     string  — site base URL
 *   name    string  — short label printed as the first column of `name  state`
 *   type    string  — auto | wp | non-wp (auto-detected by Degradation)
 *   wp_user string|null — WordPress user for Application Password auth
 *   token   string|null — WordPress Application Password token
 */

return [
    'sites' => [
        [
            'url' => 'https://mediadev.example.com',
            'name' => 'mediadev',
            'type' => 'auto',
            'wp_user' => null,
            'token' => null,
        ],
    ],
];
