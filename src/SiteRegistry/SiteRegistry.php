<?php

/**
 * Mediadev Monitor — SiteRegistry: registro de sitios.
 */

declare(strict_types=1);

namespace MediadevMonitor\SiteRegistry;

use MediadevMonitor\Infra\Config;
use MediadevMonitor\Infra\Sqlite;
use PDO;

enum SiteState: string
{
    case WP_FULL = 'wp-full';
    case WP_DEGRADED = 'wp-degraded';
    case NON_WP = 'non-wp';
    case DOWN = 'down';
    case UNKNOWN = 'unknown';
}

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

final class SiteRegistry
{
    private PDO $pdo;

    public function __construct(private Config $config)
    {
        $this->pdo = (new Sqlite($config->dbPath()))->pdo();
        $this->syncFromConfig();
    }

    /** Sincroniza config/sites.php con la tabla sites (upsert por URL). */
    private function syncFromConfig(): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sites (url, name, type, wp_user, ap_token)
             VALUES (:url, :name, :type, :wp_user, :token)
             ON CONFLICT(url) DO UPDATE SET
                name = excluded.name,
                type = excluded.type,
                wp_user = excluded.wp_user,
                ap_token = excluded.ap_token,
                updated_at = datetime(\'now\')'
        );

        foreach ($this->config->sites() as $site) {
            $stmt->execute([
                'url' => $site['url'],
                'name' => $site['name'],
                'type' => $site['type'],
                'wp_user' => $site['wp_user'] ?? null,
                'token' => $site['token'] ?? null,
            ]);
        }
    }

    /** @return Site[] */
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT * FROM sites ORDER BY name')->fetchAll();
        return array_map(fn (array $r) => $this->hydrate($r), $rows);
    }

    public function find(int $id): ?Site
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sites WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByUrl(string $url): ?Site
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sites WHERE url = ?');
        $stmt->execute([$url]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function setState(int $id, SiteState $state, int $consecutiveFailures): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE sites SET current_state = ?, consecutive_failures = ?, updated_at = datetime(\'now\') WHERE id = ?'
        );
        $stmt->execute([$state->value, $consecutiveFailures, $id]);
    }

    private function hydrate(array $row): Site
    {
        return new Site(
            id: (int) $row['id'],
            url: $row['url'],
            name: $row['name'],
            type: $row['type'],
            wpUser: $row['wp_user'] ?? null,
            apToken: $row['ap_token'] ?: null,
            consecutiveFailures: (int) $row['consecutive_failures'],
            state: SiteState::tryFrom($row['current_state']) ?? SiteState::UNKNOWN,
        );
    }
}
