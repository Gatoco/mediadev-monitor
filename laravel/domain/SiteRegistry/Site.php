<?php

declare(strict_types=1);

namespace Domain\SiteRegistry;

final class Site
{
    public function __construct(
        public readonly int $id,
        public readonly string $url,
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $wpUser,
        public readonly ?string $apToken,
        public readonly int $consecutiveFailures,
        public readonly SiteState $state,
    ) {
    }

    /**
     * Devuelve el string Basic Auth para curl cuando el sitio tiene AP, o null si no.
     * Formato WP Application Passwords: "wp_user:token" (sin espacios en el token).
     */
    public function basicAuth(): ?string
    {
        if ($this->apToken === null || $this->apToken === '') {
            return null;
        }
        $user = $this->wpUser ?? 'admin';
        $token = str_replace(' ', '', $this->apToken);
        return $user . ':' . $token;
    }
}
