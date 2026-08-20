<?php

declare(strict_types=1);

namespace Domain\SiteRegistry;

enum SiteState: string
{
    case WP_FULL = 'wp-full';
    case WP_DEGRADED = 'wp-degraded';
    case NON_WP = 'non-wp';
    case DOWN = 'down';
    case UNKNOWN = 'unknown';
}
