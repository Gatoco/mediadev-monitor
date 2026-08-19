<?php

/**
 * Mediadev Monitor — Dashboard: lee SQLite y arma la vista con semáforo.
 */

declare(strict_types=1);

namespace MediadevMonitor\Dashboard;

use MediadevMonitor\Infra\Config;
use MediadevMonitor\Infra\Sqlite;
use PDO;

final class Dashboard
{
    private PDO $pdo;

    public function __construct(Config $config)
    {
        $this->pdo = (new Sqlite($config->dbPath()))->pdo();
    }

    /** @return array<int, array<string, mixed>> */
    public function overview(): array
    {
        // ponytail: N+1 (3 queries por sitio) — SQLite local con ~28 sitios, imperceptible.
        return array_map(fn (array $row) => [
            'id' => (int) $row['id'],
            'url' => $row['url'],
            'name' => $row['name'],
            'type' => $row['type'],
            'state' => $row['current_state'],
            'semaphore' => $this->semaphore($row['current_state']),
            'consecutive_failures' => (int) $row['consecutive_failures'],
            'last_uptime' => $this->lastUptime((int) $row['id']),
            'last_version' => $this->lastVersionSummary((int) $row['id']),
            'health_score' => $this->lastHealthScore((int) $row['id']),
        ], $this->pdo->query('SELECT * FROM sites ORDER BY name')->fetchAll());
    }

    /** @return array<string, mixed> */
    public function siteDetail(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sites WHERE id = ?');
        $stmt->execute([$id]);
        $site = $stmt->fetch();
        if ($site === false) {
            return [];
        }

        return [
            'site' => $site,
            'semaphore' => $this->semaphore($site['current_state']),
            'uptime_history' => $this->uptimeHistory($id),
            'last_version' => $this->lastVersion($id),
            'last_health' => $this->lastHealth($id),
            'last_activity' => $this->lastActivity($id),
        ];
    }

    private function semaphore(string $state): string
    {
        // Literales a propósito: SiteState enum vive en SiteRegistry.php (mismo
        // archivo que Site) y el autoloader PSR-4 no lo resuelve por nombre aquí;
        // instanciar SiteRegistry solo por esto sería más código que comparar strings.
        return match ($state) {
            'down' => 'red',
            'non-wp', 'unknown', 'wp-degraded' => 'yellow',
            default => 'green',
        };
    }

    private function lastUptime(int $siteId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT status, response_ms, tls_state, ts FROM uptime_checks WHERE site_id = ? ORDER BY ts DESC LIMIT 1'
        );
        $stmt->execute([$siteId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function lastSeverity(int $siteId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT severity FROM version_snapshots WHERE site_id = ? ORDER BY ts DESC LIMIT 1'
        );
        $stmt->execute([$siteId]);
        $row = $stmt->fetch();
        return $row['severity'] ?? null;
    }

    /** Resumen compacto para la fila del overview: core + conteo de updates pendientes. */
    private function lastVersionSummary(int $siteId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT core_version, pending_json, severity FROM version_snapshots WHERE site_id = ? ORDER BY ts DESC LIMIT 1'
        );
        $stmt->execute([$siteId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $pending = json_decode($row['pending_json'] ?? '[]', true);
        return [
            'core' => $row['core_version'] ?? null,
            'severity' => $row['severity'] ?? null,
            'pending_plugins' => count($pending['plugins'] ?? []),
            'pending_themes' => count($pending['themes'] ?? []),
        ];
    }

    private function lastHealthScore(int $siteId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT score FROM site_health_snapshots WHERE site_id = ? ORDER BY ts DESC LIMIT 1'
        );
        $stmt->execute([$siteId]);
        $row = $stmt->fetch();
        return $row === false ? null : (int) $row['score'];
    }

    /** @return array<int, array<string, mixed>> */
    private function uptimeHistory(int $siteId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT status, response_ms, tls_state, ts FROM uptime_checks WHERE site_id = ? ORDER BY ts DESC LIMIT ?'
        );
        $stmt->execute([$siteId, $limit]);
        return $stmt->fetchAll();
    }

    private function lastVersion(int $siteId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT core_version, plugins_json, themes_json, pending_json, severity, ts
             FROM version_snapshots WHERE site_id = ? ORDER BY ts DESC LIMIT 1'
        );
        $stmt->execute([$siteId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function lastHealth(int $siteId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tests_json, score, ts FROM site_health_snapshots WHERE site_id = ? ORDER BY ts DESC LIMIT 1'
        );
        $stmt->execute([$siteId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function lastActivity(int $siteId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT posts_json, ts FROM activity_snapshots WHERE site_id = ? ORDER BY ts DESC LIMIT 1'
        );
        $stmt->execute([$siteId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
