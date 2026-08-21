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

---

# PR 2 (laravel-rewrite)

## Summary
Implemented the Artisan collector commands, scheduler wiring, and the PR2 RED tests (tasks 3.1–3.4 and 4.1–4.5).

## Completed Tasks
- [x] 3.1 `app/Console/Commands/CollectorUptimeCommand.php` — `collector:uptime`; exits 0/1/2; `name  state` output
- [x] 3.2 `app/Console/Commands/CollectorDeepCommand.php` — `collector:deep`; exits 0/1/2; `name  state` output
- [x] 3.3 `app/Console/Commands/CheckAllCommand.php` — `monitor:check all`; exits 0/1/2; grep-compatible `name  state` output
- [x] 3.4 `routes/console.php` — `Schedule::command(...)->everyFiveMinutes()` (uptime) and `->everySixHours()` (deep) (LA-06/LA-07)
- [x] 4.1 `CollectionReport::hasCritical()` true for DOWN state — confirmed green in `tests/Unit/CollectionReportTest.php`
- [x] 4.2 `hasCritical()` true for `versions.severity === 'red'` — confirmed green in same file
- [x] 4.3 artisan command exits 2 when config missing — `tests/Feature/CollectorCommandTest.php`
- [x] 4.4 scheduler triggers uptime at 5-min boundary — `tests/Feature/SchedulerTest.php`
- [x] 4.5 scheduler triggers deep at 6-hour boundary — `tests/Feature/SchedulerTest.php`

## Exact Docker Commands Used
```
# 1. Install deps (host has no PHP toolchain)
docker run --rm -v "${PWD}:/app" -w /app composer:latest install --ignore-platform-req=ext-intl

# 2. Create local .env + key (gitignored; host lacked .env) and run the full suite
docker run --rm -v "${PWD}:/app" -w /app php:8.3-cli sh -c "cp .env.example .env && php artisan key:generate && php artisan test"

# 3. (Optional) real-run smoke of each command
docker run --rm -v "${PWD}:/app" -w /app php:8.3-cli php artisan collector:uptime
docker run --rm -v "${PWD}:/app" -w /app php:8.3-cli php artisan collector:deep
docker run --rm -v "${PWD}:/app" -w /app php:8.3-cli php artisan monitor:check all
```

## Test Output Summary
`php artisan test` → **21 passed (36 assertions)**.
- Unit: `CollectionReportTest` (4.1, 4.2), `SiteStateTest`, `DegradationTest`, `ExampleTest`
- Feature: `CollectorCommandTest` (4.3 — 3 cases: uptime/deep/check-all exit 2 on empty config), `SchedulerTest` (4.4 + 4.5 — 4 cases: due at boundary, not due off-boundary), `ExampleTest`

## Smoke Test (real run, example site unreachable → DOWN)
```
collector:uptime  → "mediadev  down"   exit 1
collector:deep    → "mediadev  down"   exit 1
monitor:check all → "mediadev  down"   exit 1
```
`name  state` format preserved; DOWN is critical → exit 1. With empty config all three exit 2 (verified by tests). 0/1/2 parity intact.

## Rollback Boundary
All changes confined to `laravel/`:
- `app/Console/Commands/BaseCollectorCommand.php` (new) — shared 0/1/2 + `name  state` logic
- `app/Console/Commands/CollectorUptimeCommand.php` (new)
- `app/Console/Commands/CollectorDeepCommand.php` (new)
- `app/Console/Commands/CheckAllCommand.php` (new)
- `app/Providers/AppServiceProvider.php` (modified) — bound `RestClient`, `SiteRegistry`, `Collector` (storage_path cache dir)
- `routes/console.php` (modified) — scheduler calls
- `config/sites.php` (new) — canonical site registry
- `tests/Feature/CollectorCommandTest.php` (new)
- `tests/Feature/SchedulerTest.php` (new)
- `app/Models/UptimeCheck.php`, `VersionSnapshot.php`, `SiteHealthSnapshot.php`, `ActivitySnapshot.php` (modified — see Deviations)
- `.env` (local, gitignored)

Revert these files to undo PR2. Vanilla `src/`, `bin/`, `web/` untouched.

## Deviations from Design / Notes
- **config/sites.php was missing**: the task listed it as a PR1 artifact to read, but it did not exist in the repo. Created it as the canonical site source per the design data flow (`config/sites.php ──▶ SiteRegistry::syncFromConfig`). It holds one example entry; expand for the real fleet.
- **`.env` was absent on host** (gitignored, not persisted from PR1). Recreated from `.env.example` and set `DB_DATABASE=../data/mediadev.sqlite` to match PR1's stated intent. Local-only, gitignored.
- **SCHEMA FIX (deviation beyond strict PR2 task list, but required for the commands to function):** the 4 snapshot Eloquent models kept Eloquent's default `public $timestamps = true`, but the PR1 migration creates `uptime_checks`/`version_snapshots`/`site_health_snapshots`/`activity_snapshots` with a `ts` column and **no** `created_at`/`updated_at`. Every collector `save()` therefore failed with `table ... has no column named updated_at`, forcing exit 2 on every real run. Fixed by setting `public $timestamps = false;` on the 4 models so they match the schema. This is a PR1 migration/model mismatch, not a PR2 task; flag for the PR1 owner to confirm/adjust (e.g. add timestamp columns instead).
- **Scheduler testing**: this Laravel 12 build has no `Schedule::fake()` / `Schedule::assertRan` (not present in vendor). Tested cadence via the real `Schedule` instance + `dueEvents()` under `travelTo()`, asserting the due command string contains the signature at the boundary and is absent off-boundary. Equivalent coverage, no network/DB dependency.
- Collector `cacheDir` for `VersionTracker` (`wp-latest-version.cache.json`) set to `storage_path('app')`, matching PR1's `Storage` convention.

## Status
PR2 complete. Tasks 3.1–3.4 and 4.1–4.5 implemented and verified (21 tests pass). Commands emit `name  state` and preserve the 0/1/2 exit-code contract. One pre-existing PR1 schema/timestamp defect was worked around (model fix) to make real runs succeed; recommend the PR1 owner confirm the persistence-layer fix.
