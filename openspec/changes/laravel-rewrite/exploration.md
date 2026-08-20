# Exploration: Laravel 12 + Filament v4 Rewrite

> Change: `laravel-rewrite` (NEW). Decision already made by the user: create a NEW Laravel 12 + Filament v4 app and port the monitoring DOMAIN logic. This exploration confirms the porting strategy, DB access pattern, and risks.

## Current State

The repo is a **PHP 8.1 vanilla (no-framework)** app, PSR-4 namespace `MediadevMonitor\`, ~1850 lines PHP across `src/`, `web/`, `bin/`. SQLite via raw PDO. It monitors WordPress sites (uptime, versions, site-health, activity) and renders a server-side dashboard.

**Domain code (Laravel-agnostic, must be preserved as-is):**
- `src/Degradation/Degradation.php` — classifies WP/non-wp/down via `/wp-json` probe (Enfoque B).
- `src/Uptime/UptimeChecker.php` — HTTP check + TLS + 3-strike threshold (`THRESHOLD = 3`).
- `src/Version/VersionTracker.php` — core version detect (HTML meta / X-Powered-By / feed) + severity (red/yellow/green) + `latestStableWp()` cache.
- `src/SiteHealth/SiteHealthCollector.php` — health score from `/wp-site-health/v1/tests`.
- `src/Activity/ActivityCollector.php` — tokenless-first (Q#3 resolved, AC-01..AC-10).
- `src/Infra/RestClient.php` — curl wrapper with retry/backoff, `RestResponse` value object.

**Infra to be replaced by Laravel:**
- `src/Infra/Sqlite.php` — raw PDO, 5 tables, idempotent `CREATE TABLE IF NOT EXISTS` migration.
- `src/Infra/Config.php` — manual `config/sites.php` + `config/auth.php` (gitignored) + `MEDIADEV_DB_PATH` env.
- `src/Auth/Auth.php` — native PHP session, single-user, `password_verify`.
- `web/*.php` — plain PHP + CSS: `index.php`, `login.php`, `logout.php`, `layout.php`, `site.php`, `style.css`.

**To be re-wrapped into Laravel:**
- `src/Collector/Collector.php` + `src/SiteRegistry/SiteRegistry.php` — `Site`/`SiteState` enums (5 states: `wp-full`, `wp-degraded`, `non-wp`, `down`, `unknown`), `CollectionReport::hasCritical()`.
- `bin/collector.php` + `bin/mediadev` — CLI, exit codes 0/1/2.
- `src/Dashboard/Dashboard.php` + `src/Cli/Reporter.php` — dashboard queries + terminal reporter.

## Affected Areas

- `src/Infra/Sqlite.php` — replaced by Eloquent models + migrations (5 tables).
- `src/Infra/Config.php` — replaced by Laravel config/env.
- `src/Auth/Auth.php` — replaced by Laravel/Filament auth.
- `web/*.php` — replaced by Filament Resources (SiteResource, dashboard widgets).
- `src/Collector/Collector.php`, `src/SiteRegistry/SiteRegistry.php` — re-wrapped as Laravel service + artisan commands.
- `bin/collector.php`, `bin/mediadev` — replaced by artisan commands + Scheduler.
- `src/Dashboard/Dashboard.php`, `src/Cli/Reporter.php` — replaced by Filament widgets/stats.
- `Dockerfile`, `docker-compose.yml`, `crontab` — replaced by Laravel scheduler + new container.
- `bin/e2e-assert.sh`, `bin/wp-bootstrap.sh` — E2E harness must be re-targeted to the new app.
- `composer.json` — new Laravel 12 + Filament v4 dependencies.

## Approaches

### 1. **Port domain as a standalone library + repository pattern (RECOMMENDED)**
Preserve the 6 domain classes verbatim (Laravel-agnostic, no `PDO`/`Sqlite` imports). Introduce a **persistence port** (repository interfaces) that the domain depends on; Eloquent models implement those ports. Laravel app consumes the domain via a `MediadevMonitor\` domain layer; artisan commands + Filament resources are thin adapters.

- Pros:
  - Domain logic (classification, 3-strike, severity, tokenless-first) is preserved byte-for-byte → lowest regression risk, E2E fixtures still pass.
  - Clean hexagonal boundary: domain never touches Eloquent/PDO; swap persistence freely.
  - The 6 preserved classes are already pure (they only need `RestClient` + a persistence port); the only coupling to break is `$sqlite->pdo()`.
  - Matches the user's explicit decision (option 2: port the domain).
- Cons:
  - Requires introducing repository interfaces + an adapter layer (some upfront design).
  - Domain classes currently receive `Sqlite` and call `$sqlite->pdo()` directly — must be refactored to accept a repository/port instead.
- Effort: Medium

### 2. **Full rewrite into Laravel app/Http style**
Rewrite everything as Laravel models, services, controllers, Filament resources — discard the clean domain layer and inline logic into Laravel idioms.

- Pros:
  - Idiomatic Laravel/Filament; no adapter layer.
  - Faster to write for a Laravel-native dev.
- Cons:
  - Loses the tested domain layer → high regression risk on classification/severity/3-strike/tokenless logic.
  - E2E fixture expectations (wp-full/wp-degraded/non-wp/down, exit codes) would need re-verification from scratch.
  - Contradicts the user's explicit "port the domain" decision.
- Effort: High

## Recommendation

**Approach 1** — port the 6 domain classes as a standalone Laravel-agnostic library, introduce repository interfaces as the persistence port, and have Eloquent models implement them. This preserves the verified domain behavior (the E2E fixture matrix and exit-code contract depend on it) while giving Laravel/Filament the infra (auth, config, scheduler, ORM, UI). The only structural change to domain code is replacing the `Sqlite`/`PDO` dependency with a repository port — a mechanical, low-risk refactor.

## Risks

- **Exit codes contract (0/1/2)**: `bin/collector.php` and `bin/mediadev` return 0=OK, 1=critical (down or severity red), 2=usage/config error. `e2e-assert.sh` asserts these (`assert_exit_codes`). Artisan commands must reproduce this exactly, and `hasCritical()` semantics (DOWN or `versions.severity === 'red'`) must be preserved.
- **SQLite → Eloquent migration compatibility**: current schema uses `TEXT` columns for JSON (`plugins_json`, `themes_json`, `pending_json`, `tests_json`, `posts_json`) and `TEXT NOT NULL DEFAULT (datetime('now'))` timestamps. Eloquent migrations must keep these as text/JSON-compatible columns and preserve the `datetime('now')` default semantics so existing data (if any) and the E2E flow behave identically. `current_state`/`type` are string enums — map to `SiteState` enum cleanly.
- **cron → Scheduler**: `crontab` runs `collector.php uptime` every 5 min and `deep` every 6h. Must become Laravel Scheduler entries (`schedule:run` via a single cron line in the container). The 5-min/6h cadence and the uptime-vs-deep mode split must be preserved.
- **Docker compose changes**: the `monitor` service (php:8.3-cli + cron + `php -S`) must become a Laravel app container (php-fpm/octane + scheduler + queue if needed). The E2E fixture stack (5 fixtures + mysql + wp-cli oneshots, `net-sites`/`net-monitor` networks, `ap-tokens` volume) must keep working — the monitor must still reach fixtures by hostname and expose the dashboard.
- **E2E harness re-targeting**: `bin/e2e-assert.sh` greps `collector.php deep` output format (`name  state`) and `bin/mediadev check all` output. The new artisan commands must emit a compatible machine-readable output (or the assert script must be updated in lockstep). `bin/wp-bootstrap.sh` (AP token bootstrap) is fixture-side and likely unchanged.
- **`latestStableWp()` cache path**: `VersionTracker` reads a cache file at `<root>/wp-latest-version.cache.json` (injected in the Docker build for E2E, OQ3). The new app must preserve this cache-file mechanism so the E2E `wp-outdated` fixture still resolves the injected stable version.
- **Dashboard N+1**: `Dashboard::overview()` does 3 queries per site (documented ponytail N+1). Filament widgets should use eager loading / single queries to avoid the same pattern at scale (28 sites).

## Ready for Proposal

**Yes.** The user's decision (new Laravel 12 + Filament v4 app, port the domain) is confirmed viable. The orchestrator should tell the user: Approach 1 (domain library + repository port) is recommended to preserve the verified classification/severity/3-strike/tokenless logic and the E2E exit-code contract; the rewrite is a new app in a new directory (or a fresh branch), with the 6 domain classes ported as-is and only the `Sqlite`/`PDO` dependency replaced by a repository port. Proposal should define the new app scaffold, the repository interfaces, the artisan command mapping (with exit-code parity), the Scheduler cadence, and the E2E re-targeting.
