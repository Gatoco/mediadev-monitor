# Tasks: Laravel 12 + Filament v4 Rewrite

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1000 (21 new, 6 mod, 9 del, scaffold, migrations, 5 specs) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1: Foundation+Domain+Persistence → PR 2: Artisan+Scheduler → PR 3: Filament → PR 4: E2E+Cleanup |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Scaffold Laravel + port domain + Eloquent persistence | PR 1 | `php artisan migrate --dry-run` + `phpunit domain/` | N/A (pure PHP / SQLite) | `laravel/` + `domain/` removable without touching vanilla app |
| 2 | Artisan commands + scheduler | PR 2 | `php artisan collector:uptime` returns 0 against fixtures | Laravel scheduler integration test | Commands + `routes/console.php` only |
| 3 | Filament dashboard + widgets | PR 3 | Filament List page renders 28 sites with ≤6 queries | Filament panel test with query logger | `app/Filament/` directory |
| 4 | E2E re-target + cleanup | PR 4 | `bin/e2e-assert.sh` passes 12/12 | Docker compose full stack | Deleted `bin/*`, `web/*`, `src/*`; vanilla files restorable from git |

## Phase 1: Foundation / Infra

- [x] 1.1 Scaffold Laravel 12 in `laravel/` with PHP 8.3 (LA-01)
- [x] 1.2 Copy `.env.example` → `.env`, set `DB_CONNECTION=sqlite` pointing to existing file (LA-02, LA-03)
- [x] 1.3 Add `filament/filament` and required deps to `composer.json`

## Phase 2: Core Implementation

- [x] 2.1 Port 6 domain classes + `Collector`, `SiteRegistry`, `RestClient` verbatim to `domain/` (EP-08)
- [x] 2.2 Create repository interfaces in `domain/Port/` (EP-06)
- [x] 2.3 Create Eloquent migration replicating 5-table SQLite schema (EP-01, EP-02, EP-03, EP-04)
- [x] 2.4 Create `SiteState` enum and Eloquent models with casts (EP-05)
- [x] 2.5 Create Eloquent adapter classes in `app/Repositories/` (EP-07)
- [x] 2.6 Bind interfaces to adapters in `app/Providers/AppServiceProvider.php`

## Phase 3: Integration / Wiring

- [x] 3.1 Create `app/Console/Commands/CollectorUptimeCommand.php` exiting 0/1/2 with `name  state` output (AC-01, AC-04..AC-07)
- [x] 3.2 Create `app/Console/Commands/CollectorDeepCommand.php` exiting 0/1/2 with `name  state` output (AC-02, AC-04..AC-07)
- [x] 3.3 Create `app/Console/Commands/CheckAllCommand.php` exiting 0/1/2 with grep-compatible output (AC-03..AC-09)
- [x] 3.4 Wire scheduler in `routes/console.php`: uptime every 5min, deep every 6h (LA-06, LA-07)
- [ ] 3.5 Create `app/Filament/Resources/SiteResource.php` with List/View/Edit, no Create (FD-01, FD-02, FD-08)
- [ ] 3.6 Create `app/Filament/Widgets/*Widget.php` with eager-loaded queries (FD-04, FD-05)

## Phase 4: Testing / RED Tests

- [x] 4.1 RED test: `CollectionReport::hasCritical()` returns true for DOWN state (AC-05, AC-09)
- [x] 4.2 RED test: `hasCritical()` returns true for `versions.severity === 'red'` (AC-05, AC-09)
- [x] 4.3 RED test: artisan command exits 2 on missing config (AC-06)
- [x] 4.4 RED test: scheduler triggers uptime at 5-min boundary (LA-06)
- [x] 4.5 RED test: scheduler triggers deep at 6-hour boundary (LA-06)
- [ ] 4.6 RED test: `e2e-assert.sh` invokes artisan, not `bin/*` (AC-10, EV-A1)
- [ ] 4.7 Run domain unit tests with mocked repositories
- [ ] 4.8 Run Eloquent adapter integration tests against SQLite
- [ ] 4.9 Run artisan command exit-code tests
- [ ] 4.10 Re-target `bin/e2e-assert.sh` to artisan commands (EV-A1)

## Phase 5: Cleanup / Rollout

- [ ] 5.1 Delete `bin/collector.php` and `bin/mediadev` (AC-10)
- [ ] 5.2 Delete `web/*.php`, `src/Dashboard/`, `src/Cli/`, `src/Auth/`, `src/Infra/` (FD-06, FD-07)
- [ ] 5.3 Update `Dockerfile` to build Laravel in `/app/laravel` (LA-08)
- [ ] 5.4 Update `docker-compose.yml` to mount `laravel/` as `/app/laravel`
- [ ] 5.5 Update `crontab` to single line `* * * * * cd /app/laravel && php artisan schedule:run`
