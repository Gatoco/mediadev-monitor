# Docker Deployment Specification

**Capability:** docker-deployment · **Change:** phase4-docker-e2e · **Type:** NEW
**Keywords:** RFC 2119 — MUST, SHALL, SHOULD, MAY

## Purpose

A reproducible local stack (monitor + WordPress + MySQL) for end-to-end validation before dropping in the real 28 sites.

## Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| DR1 | The compose file MUST define three services: `monitor`, `wordpress`, `mysql`. | MUST |
| DR2 | `docker compose build` MUST succeed on a clean host, producing a `php:8.3-cli` monitor image with `pdo_sqlite`, `cron`, `curl`, Composer. | MUST |
| DR3 | `docker compose up -d` MUST bring all three services to healthy state without manual intervention. | MUST |
| DR4 | The monitor container MUST serve the dashboard on host port `8080` after boot. | MUST |
| DR5 | `config/sites.php` and `config/auth.php` MUST be mounted read-only into the monitor container. | MUST |
| DR6 | The SQLite database MUST live on the named volume `mediadev-data` and MUST persist across `down && up`. | MUST |
| DR7 | Manual `php bin/collector.php uptime|deep` MUST run successfully inside the monitor container and update SQLite. | MUST |
| DR8 | The `wordpress` service MUST be reachable from the monitor on a stable internal hostname so a registered site classifies as `wp-full`. | MUST |
| DR9 | The `mysql` service SHOULD expose a healthcheck and `wordpress` SHOULD wait for it. | SHOULD |
| DR10 | The monitor MAY pin PHP/WP/MySQL versions for reproducibility. | MAY |

## Scenarios

### DR1 — Three-service composition
**Given** a clean checkout with `docker-compose.yml`
**When** `docker compose config --services` is run
**Then** the output MUST list exactly `monitor`, `wordpress`, `mysql`.

### DR2 — Clean build
**Given** no cached monitor image
**When** `docker compose build monitor` is run
**Then** the build MUST exit 0 and the resulting image MUST contain `pdo_sqlite`, `cron`, `curl`, and `composer`.

### DR3 — Up brings the stack healthy
**Given** a successful build
**When** `docker compose up -d` is run
**Then** all three containers MUST reach running/healthy state within 120s and `docker compose ps` MUST show none exited.

### DR4 — Dashboard reachable
**Given** the stack is up
**When** `curl -sS -o /dev/null -w '%{http_code}' http://localhost:8080/login.php` is run
**Then** the HTTP status MUST be `200`.

### DR5 — Read-only config mount
**Given** the monitor container is running
**When** `docker compose exec monitor sh -c 'touch /app/config/sites.php'` is attempted
**Then** the command MUST fail (read-only filesystem).

### DR6 — Persistence across restart
**Given** the stack is up and at least one `uptime` collector run has written rows to SQLite
**When** `docker compose down` followed by `docker compose up -d` is run
**Then** the SQLite database MUST still contain the previously written rows.

### DR7 — Manual collector runs inside container
**Given** the monitor container is running
**When** `docker compose exec monitor php bin/collector.php uptime` is run
**Then** the command MUST exit 0 and a new row MUST be visible in the SQLite `uptime_snapshots` table.

### DR8 — Local WP classified as wp-full
**Given** the stack is up and `config/sites.php` registers the local `wordpress` service with a valid Application Password
**When** a `deep` collector run is executed inside the monitor container
**Then** the local WP site MUST be classified as `wp-full` in the registry.

### DR9 (edge) — MySQL not yet ready
**Given** the `mysql` service is still initializing
**When** the `wordpress` service starts
**Then** the `wordpress` container MUST wait or retry until `mysql` is healthy (SHOULD not crash-loop).

### DR10 (edge) — Fresh volume on first boot
**Given** the named volume `mediadev-data` does not exist
**When** `docker compose up -d` is run for the first time
**Then** the monitor container MUST create the SQLite file at `MEDIADEV_DB_PATH` on first collector run without error.