<?php

/**
 * Mediadev Monitor — Infra: SQLite (PDO) con migración idempotente.
 *
 * Crea las 7 tablas del esquema en el primer uso.
 * Ruta configurable via MEDIADEV_DB_PATH.
 */

declare(strict_types=1);

namespace MediadevMonitor\Infra;

use PDO;

final class Sqlite
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->migrate();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function migrate(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS sites (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    url                  TEXT NOT NULL UNIQUE,
    name                 TEXT NOT NULL,
    type                 TEXT NOT NULL DEFAULT 'auto',
    ap_token             TEXT,
    consecutive_failures INTEGER NOT NULL DEFAULT 0,
    current_state        TEXT NOT NULL DEFAULT 'unknown',
    created_at           TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at           TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS uptime_checks (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id      INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    ts           TEXT NOT NULL DEFAULT (datetime('now')),
    status       INTEGER,
    response_ms  INTEGER,
    tls_state    TEXT
);

CREATE TABLE IF NOT EXISTS version_snapshots (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id      INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    ts           TEXT NOT NULL DEFAULT (datetime('now')),
    core_version TEXT,
    plugins_json TEXT,
    themes_json  TEXT,
    pending_json TEXT,
    severity     TEXT NOT NULL DEFAULT 'green'
);

CREATE TABLE IF NOT EXISTS site_health_snapshots (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id   INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    ts        TEXT NOT NULL DEFAULT (datetime('now')),
    tests_json TEXT,
    score     INTEGER
);

CREATE TABLE IF NOT EXISTS activity_snapshots (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id    INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    ts         TEXT NOT NULL DEFAULT (datetime('now')),
    posts_json TEXT
);

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS session (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token      TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL
);
SQL);
    }
}
