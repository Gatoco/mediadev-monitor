# Laravel App Specification

> Capability: `laravel-app` — NEW full spec. Laravel 12 + Filament v4 scaffold, config/env, auth, Scheduler wiring.

## Purpose

MUST establish a Laravel 12 application (PHP 8.3) that owns infra concerns: config/env loading, authentication, task scheduling, and the Filament v4 admin panel, replacing vanilla-PHP `web/*.php`, `src/Auth/`, and `crontab`.

## Requirements

| ID | Requirement |
|----|-------------|
| LA-01 | The app MUST run on Laravel 12 with PHP >= 8.3. |
| LA-02 | A `.env` file MUST define `APP_NAME`, `APP_URL`, `DB_CONNECTION=sqlite`, and `DB_DATABASE` pointing to the existing SQLite path. |
| LA-03 | The existing SQLite file MUST be readable without migration; no data migration is required. |
| LA-04 | `APP_KEY` MUST be generated on install. |
| LA-05 | Laravel/Filament auth MUST replace `src/Auth/Auth.php`; a single admin user MUST authenticate via the Filament login screen. |
| LA-06 | The Scheduler MUST register `uptime` every 5 minutes and `deep` every 6 hours via a single cron entry (`* * * * * cd /app && php artisan schedule:run`). |
| LA-07 | The scheduler tasks MUST invoke the same domain collectors (`UptimeChecker`, `Collector::runOne deep`) that `bin/collector.php` previously invoked. |
| LA-08 | `wp-latest-version.cache.json` Docker build injection MUST remain resolvable at the same relative path within the container image (OQ3). |

## Scenarios

### LA-01 / LA-02 — Env bootstrap
**Given** a fresh clone with `.env.example` copied to `.env`
**When** `php artisan` runs
**Then** the framework MUST boot without config errors and SQLite MUST connect.

### LA-03 — SQLite portability
**Given** the existing `mediadev.sqlite` from the vanilla app
**When** Laravel boots
**Then** Eloquent MUST read the same schema; existing data MUST remain intact.

### LA-05 — Filament login
**Given** an admin user seeded
**When** the user visits `/admin`
**Then** Filament MUST render the login form; valid credentials MUST grant access to the dashboard.

### LA-06 / LA-07 — Scheduler wiring
**Given** the Laravel scheduler is registered
**When** `php artisan schedule:run` is invoked at a 5-minute boundary
**Then** the `uptime` task MUST execute; at a 6-hour boundary, the `deep` task MUST execute.

### LA-08 — WP cache injection survives
**Given** the Docker image contains `/app/wp-latest-version.cache.json`
**When** `VersionTracker::latestStableWp()` queries the image cache
**Then** the injected version MUST be returned, identical to vanilla behavior.

## Edge Cases

- Missing `.env` MUST fail fast with a clear error; MUST NOT fall back to an empty database.
- If the SQLite file is read-only, the app MUST surface the filesystem error at boot.
