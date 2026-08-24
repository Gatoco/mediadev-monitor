# SDD Apply Progress — PR 2 (laravel-rewrite: artisan commands + scheduler)

## Summary
Implemented PR 2 (Phase 3.1-3.4 + RED tests 4.1-4.5) of the `laravel-rewrite` change: the artisan command surface replacing `bin/collector.php` and `bin/mediadev`, the legacy config bridge, the scheduler wiring, and the exit-code/scheduler test suite. Builds on PR 1 (already committed on `feat/laravel-rewrite`).

## Completed Tasks
- [x] 1.1-1.3, 2.1-2.6 — PR 1 (previous batch; see earlier progress)
- [x] 3.1 `collector:uptime` — exit 0/1/2, `name  state` output (AC-01, AC-04..AC-07)
- [x] 3.2 `collector:deep` — exit 0/1/2, `name  state` output (AC-02, AC-04..AC-07)
- [x] 3.3 `monitor:check {target} [--list]` — exit 0/1/2, Reporter table output (AC-03..AC-09)
- [x] 3.4 Scheduler in `routes/console.php` — uptime `*/5 * * * *`, deep `0 */6 * * *` (LA-06, LA-07)
- [x] 4.1 RED test: `hasCritical()` → DOWN (existing CollectionReportTest covers; re-verified)
- [x] 4.2 RED test: `hasCritical()` → severity red (existing CollectionReportTest covers; re-verified)
- [x] 4.3 RED test: artisan exits 2 on config error (`monitor:check` unknown target; missing config path returns empty sites — see note)
- [x] 4.4 RED test: scheduler registers uptime at 5-min boundary
- [x] 4.5 RED test: scheduler registers deep at 6-hour boundary
- [x] 3.5 `SiteResource` — List/View/Edit (no Create), state badge color-coded (FD-01, FD-02, FD-08)
- [x] 3.6 Widgets — `SiteStatsOverview` (4 stats) + `RecentChecksTable` (eager-loaded) (FD-04, FD-05)

## PR 3 Evidence (Filament dashboard)

| Evidence | Value |
|----------|-------|
| Focused test command | `docker run --rm -v "$PWD/laravel:/app" -w /app php:8.3-cli-intl php vendor/phpunit/phpunit/phpunit tests/Feature/FilamentDashboardTest.php` |
| Test result | OK (3 tests) — login renders, sites list ≤10 queries, dashboard renders |
| N+1 measurement | 6 queries for 28 sites (budget 10) — no N+1 |
| Full suite | OK (27 tests, 46 assertions) |
| Runtime harness | `php artisan serve` + curl: `/admin/login` 200, `/admin/sites` 302 (auth) |
| Rollback boundary | `laravel/app/Filament/`, `AdminPanelProvider`, `bootstrap/providers.php` — removable without touching commands/domain |

## PR 3 Key Decisions / Deviations
- Filament v4 API confirmed from local vendor: `Filament\Schemas\Schema` for form/infolist (not `Filament\Forms\Form`), actions in `Filament\Actions\ViewAction/EditAction` (not `Filament\Tables\Actions`), `StatsOverviewWidget\Stat::make()`, non-static `$pollingInterval`.
- `User` implements `FilamentUser::canAccessPanel()` → required, otherwise Filament returns 403 in non-local envs (LA-05).
- `ext-intl` is REQUIRED at runtime by Filament (pagination `Number::format`): built local `php:8.3-cli-intl` test image; the real Dockerfile (PR 4) must install `libicu-dev` + `docker-php-ext-install intl`.
- `navigationIcon` property type: `protected static string | BackedEnum | null` — the `BackedEnum` type must be imported or the class fails to load.
- Swapped the scaffold's `#[Fillable]` attribute on `User` for classic `$fillable` — the attribute form didn't work in this scaffold (MassAssignmentException in tests).
- Widget `SiteStatsOverview` counts `down` from DB state and `red` severity via `whereHas('latestVersion')` — single queries, no N+1.

## Status
13/19 tasks complete (PR 1: 6, PR 2: 9, PR 3: 2 + evidence). Next: PR 4 (E2E re-target + Docker intl + cleanup + docs).

## Work Unit Evidence

| Evidence | Value |
|----------|-------|
| Focused test command | `docker run --rm -v "$PWD/laravel:/app" -w /app php:8.3-cli php vendor/phpunit/phpunit/phpunit tests/Feature --testdox` |
| Test result | OK (11 tests, 13 assertions) — exit codes + scheduler cadence |
| Full suite | OK (24 tests, 41 assertions) — Unit + Feature |
| Runtime harness | `docker run --rm -v "$PWD/laravel:/app" -v "$PWD/data:/data" -w /app -e DB_DATABASE=../data/mediadev.sqlite php:8.3-cli php artisan collector:uptime` |
| Runtime result | Exit 0 against the real `data/mediadev.sqlite` (0 sites, empty config) |
| Rollback boundary | `laravel/app/Console/`, `laravel/app/Support/`, `laravel/config/mediadev.php`, `laravel/routes/console.php`, `laravel/tests/Feature/CommandExitCodeTest.php`, `SchedulerTest.php`, plus `$timestamps=false` on 4 snapshot models — vanilla `bin/*` untouched |

## Key Decisions
- `bin/collector.php` and `bin/mediadev` are NOT deleted in this PR — cutover happens in PR 4 alongside the E2E re-target. Keeps the branch bisectable.
- `config/mediadev.php` bridge keeps `config/sites.php` + `config/auth.php` as the source of truth (legacy seed workflow untouched); `cache_dir` → `storage/app/mediadev` replaces the vanilla `data/mediadev.sqlite.cache` convention.
- `SiteConfig` (app/Support) parses legacy files with the same `ParseError → RuntimeException(2)` contract as the vanilla `Config::sites()`.
- `Reporter` ported verbatim to `app/Console/Output/Reporter` (only namespace change).
- `Collector` bound as a singleton in `AppServiceProvider`, which also calls `syncFromConfig(SiteConfig::sites())` — same sync-per-boot semantics as the vanilla constructor.
- `Site::current_state` cast: `SiteStateCast` maps unknown strings to `SiteState::UNKNOWN` (PR 1) — preserved.
- Eloquent `Site` model has `$timestamps = true` (table has the columns); the 4 snapshot models set `$timestamps = false` (legacy tables lack the columns — see Issues).

## Deviations from Design
- `monitor:check` gains a `--list` flag (mirrors `bin/mediadev list`), which the design table didn't list; AC-03 only required `check all`. Minor scope addition for CLI parity.
- Command classes register via auto-discovery in `app/Console/Commands/` (Laravel 12 default), no manual registration needed — matches design's file list.
- Cache dir: design.md's `data flow` says `VersionTracker` reads `<root>/wp-latest-version.cache.json`; the port keeps that path resolution (`dirname(__DIR__, 2)`), but the runtime cache (24h TTL) now goes to `storage/app/mediadev` — avoids writing into `data/` (vanilla convention was `data/mediadev.sqlite.cache`). The injected build-time cache still resolves via `dirname()`.

## Issues Found
- **Eloquent timestamp bug (PR 1 latent):** snapshot tables replicate the legacy schema WITHOUT `created_at`/`updated_at`, but the 4 snapshot models default to `$timestamps = true` → `insert` fails with "table uptime_checks has no column named updated_at". Fixed by `public $timestamps = false` on `UptimeCheck`, `VersionSnapshot`, `SiteHealthSnapshot`, `ActivitySnapshot`. This was the reason `collector:uptime` exited 2 during integration testing. Root cause: schema parity (EP-02) vs. Eloquent defaults mismatch — the migration is correct; the models were wrong.
- `DB_DATABASE=:memory:` in phpunit.xml requires `RefreshDatabase` on Feature tests that touch the DB (already the pattern).
- `php:8.3-cli` lacks `ext-intl` → composer operations need `--ignore-platform-req=ext-intl` (known from PR 1).

## Remaining Tasks (PR 3 + PR 4)
- 3.5 SiteResource + 3.6 Widgets (Filament, PR 3)
- 4.6-4.9 RED/integration tests for e2e-assert re-target (PR 4)
- 4.10 Re-target `bin/e2e-assert.sh` to artisan (PR 4)
- 5.1-5.5 Dockerfile/compose/crontab cleanup + deletion of vanilla `bin/*`, `web/*`, `src/*` (PR 4)

## Files Changed
| File | Action | Description |
|------|--------|-------------|
| `laravel/config/mediadev.php` | Created | Bridge config: legacy sites/auth file paths + cache_dir |
| `laravel/app/Support/SiteConfig.php` | Created | Parses legacy config/sites.php + auth.php with exit-2 parity |
| `laravel/app/Console/Output/Reporter.php` | Created | Ported vanilla Reporter (table + ANSI) |
| `laravel/app/Console/Commands/CollectorUptimeCommand.php` | Created | `collector:uptime` — name/state output, exit 0/1/2 |
| `laravel/app/Console/Commands/CollectorDeepCommand.php` | Created | `collector:deep` — name/state output, exit 0/1/2 |
| `laravel/app/Console/Commands/MonitorCheckCommand.php` | Created | `monitor:check {all\|--list}` — Reporter output, exit 0/1/2 |
| `laravel/app/Providers/AppServiceProvider.php` | Modified | Binds SiteRegistry + Collector singletons; syncFromConfig at boot |
| `laravel/routes/console.php` | Modified | Scheduler: uptime every 5min, deep every 6h |
| `laravel/tests/Feature/CommandExitCodeTest.php` | Created | 8 exit-code integration tests |
| `laravel/tests/Feature/SchedulerTest.php` | Created | 2 cadence tests |
| `laravel/app/Models/{UptimeCheck,VersionSnapshot,SiteHealthSnapshot,ActivitySnapshot}.php` | Modified | `$timestamps = false` (legacy schema parity) |
| `openspec/changes/laravel-rewrite/tasks.md` | Modified | Marked 3.1-3.4, 4.1-4.5 complete |

## Status
11/19 tasks complete (PR 1: 6, PR 2: 9). Ready for next batch: PR 3 (Filament) then PR 4 (E2E + cleanup).
