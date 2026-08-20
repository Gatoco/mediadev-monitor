# SDD Apply Progress — PR 1 (laravel-rewrite)

## Summary
Implemented PR 1 (Phase 1 + Phase 2) of the `laravel-rewrite` change.

## Completed Tasks
- [x] 1.1 Scaffold Laravel 12 in `laravel/` with PHP 8.3 (LA-01)
- [x] 1.2 Copy `.env.example` → `.env`, set `DB_CONNECTION=sqlite` pointing to existing file (LA-02, LA-03)
- [x] 1.3 Add `filament/filament` and required deps to `composer.json`
- [x] 2.1 Port 6 domain classes + `Collector`, `SiteRegistry`, `RestClient` verbatim to `domain/` (EP-08)
- [x] 2.2 Create repository interfaces in `domain/Port/` (EP-06)
- [x] 2.3 Create Eloquent migration replicating 5-table SQLite schema (EP-01, EP-02, EP-03, EP-04)
- [x] 2.4 Create `SiteState` enum and Eloquent models with casts (EP-05)
- [x] 2.5 Create Eloquent adapter classes in `app/Repositories/` (EP-07)
- [x] 2.6 Bind interfaces to adapters in `app/Providers/AppServiceProvider.php`

## Work Unit Evidence

| Evidence | Value |
|----------|-------|
| Focused test command | `docker run --rm -v "$PWD:/app" -w /app php:8.3-cli php vendor/phpunit/phpunit/phpunit tests/Unit --testdox` |
| Test result | OK (13 tests, 28 assertions) — CollectionReport, SiteState, Degradation |
| Runtime harness | `docker run --rm -v "$PWD:/app" -v "$PWD/../data:/data" -w /app php:8.3-cli php artisan migrate --force` |
| Runtime result | All 5 monitor tables created in `data/mediadev.sqlite`; schema verified with `PRAGMA table_info` |
| Rollback boundary | Remove `laravel/` directory entirely; vanilla `src/`, `bin/`, `web/` untouched. No data migration needed. |

## Key Decisions
- Composer platform pinned to `"php": "8.3"` because `laravel/framework ^13.17` defaults to PHP 8.4+.
- Composer updates require `--ignore-platform-req=ext-intl` since `php:8.3-cli` Docker image lacks the intl extension (Filament v4 needs it).
- `DB_DATABASE` in `.env` set to `../data/mediadev.sqlite` (relative to `laravel/` directory).
- `SiteStateCast` created because Laravel's native enum cast returns `null` for unknown values; the cast maps them to `SiteState::UNKNOWN`.
- Eloquent `Model::find()` conflict with `SiteRepository::find()` resolved by delegating through `static::query()->find($id)`.

## Deviations from Design
- None. Implementation matches design decisions (standalone `domain/` lib, repository port pattern, schema parity).

## Issues Found
- `php:8.3-cli` lacks `ext-intl` → documented workaround with `--ignore-platform-req=ext-intl`.
- SQLite file must exist before `php artisan migrate` runs when `DB_DATABASE` is a relative path.

## Remaining Tasks (Phase 3-5)
- 3.1-3.4: Artisan commands + scheduler wiring (PR 2)
- 3.5-3.6: Filament dashboard + widgets (PR 3)
- 4.1-4.10: RED tests + adapter integration tests + E2E re-target (PR 4)
- 5.1-5.5: Cleanup / rollout

## Files Changed
| File | Action | Description |
|------|--------|-------------|
| `laravel/composer.json` | Modified | Added filament/filament ^4.0, `"Domain\\": "domain/"` autoload, `"platform": {"php": "8.3"}` |
| `laravel/.env` | Modified | SQLite config pointing to `../data/mediadev.sqlite` |
| `laravel/phpunit.xml` | Modified | Added `domain/` to source include; :memory: DB for tests |
| `laravel/domain/Infra/RestClient.php` | Created | Verbatim port from `src/Infra/RestClient.php` |
| `laravel/domain/SiteRegistry/SiteState.php` | Created | 5-state enum (wp-full, wp-degraded, non-wp, down, unknown) |
| `laravel/domain/SiteRegistry/Site.php` | Created | Verbatim port |
| `laravel/domain/SiteRegistry/SiteRegistry.php` | Created | Ported; Sqlite/PDO replaced by SiteRepository port |
| `laravel/domain/Port/*Repository.php` | Created | 5 repository interfaces (Site, UptimeCheck, VersionSnapshot, SiteHealthSnapshot, ActivitySnapshot) |
| `laravel/domain/Degradation/Degradation.php` | Created | Verbatim port |
| `laravel/domain/Uptime/UptimeChecker.php` | Created | Ported; Sqlite replaced by UptimeCheckRepository port |
| `laravel/domain/Version/VersionTracker.php` | Created | Ported; Sqlite replaced by VersionSnapshotRepository port |
| `laravel/domain/SiteHealth/SiteHealthCollector.php` | Created | Ported; Sqlite replaced by SiteHealthSnapshotRepository port |
| `laravel/domain/Activity/ActivityCollector.php` | Created | Ported; Sqlite replaced by ActivitySnapshotRepository port |
| `laravel/domain/Collector/Collector.php` | Created | Ported; deps replaced by repository ports |
| `laravel/domain/Collector/SiteReport.php` | Created | Verbatim port |
| `laravel/domain/Collector/CollectionReport.php` | Created | Verbatim port |
| `laravel/app/Casts/SiteStateCast.php` | Created | Eloquent cast mapping string → SiteState enum |
| `laravel/app/Models/Site.php` | Created | Eloquent model with SiteStateCast; repository interface methods via query() |
| `laravel/app/Models/UptimeCheck.php` | Created | Eloquent model |
| `laravel/app/Models/VersionSnapshot.php` | Created | Eloquent model |
| `laravel/app/Models/SiteHealthSnapshot.php` | Created | Eloquent model |
| `laravel/app/Models/ActivitySnapshot.php` | Created | Eloquent model |
| `laravel/app/Repositories/Eloquent*Repository.php` | Created | 5 adapters mapping Eloquent ↔ port interfaces |
| `laravel/app/Providers/AppServiceProvider.php` | Modified | Singleton bindings for all 5 repository interfaces |
| `laravel/database/migrations/2025_01_15_000000_create_monitor_tables.php` | Created | 5-table migration replicating SQLite schema |
| `laravel/tests/Unit/CollectionReportTest.php` | Created | Unit tests for hasCritical() |
| `laravel/tests/Unit/SiteStateTest.php` | Created | Unit tests for enum + Site::basicAuth() |
| `laravel/tests/Unit/DegradationTest.php` | Created | Unit tests with mocked SiteRepository |

## Status
6/6 tasks complete for PR 1. Ready for next batch (PR 2: Artisan commands + scheduler wiring).
