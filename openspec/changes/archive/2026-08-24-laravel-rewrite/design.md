# Design: Laravel 12 + Filament v4 Rewrite

## Technical Approach

Create a new Laravel 12 + Filament v4 app in `laravel/` (same repo, new directory). Port the 6 verified domain classes from `src/` to `domain/` verbatim, replacing their `Sqlite`/`PDO` dependency with repository interfaces (persistence port). Eloquent models + migrations replicate the 5-table SQLite schema exactly. Artisan commands replace `bin/collector.php` and `bin/mediadev` with exit-code parity 0/1/2. Laravel Scheduler handles cron cadence. Filament v4 replaces `web/*.php` + `src/Dashboard/`. The vanilla app stays untouched until cutover.

## Architecture Decisions

| Decision | Options | Tradeoffs | Chosen |
|----------|---------|-----------|--------|
| App location | `laravel/` new dir vs. `git branch` | Dir allows side-by-side comparison and easier Dockerfile dual-build; branch requires switch to test vanilla | `laravel/` new dir |
| Domain isolation | Standalone `domain/` lib vs. inline Laravel models | Lib preserves byte-for-byte logic (lowest regression); inline is idiomatic but risky | Standalone `domain/` with repository ports |
| Persistence port | Repository interfaces vs. direct Eloquent in domain | Interfaces decouple domain from framework; adds adapter layer | Repository interfaces in `domain/Port/` |
| SQLite handling | Reuse existing file vs. new migration-only DB | Reuse preserves E2E data; same schema means Eloquent reads existing rows | Reuse existing SQLite file via `DB_CONNECTION=sqlite` |
| Cron/scheduler | Laravel `schedule:run` vs. individual artisan in crontab | Single cron entry is simpler; `schedule:run` delegates cadence to `app/Console/Kernel.php` | Single cron line `* * * * * cd /app/laravel && php artisan schedule:run` |
| Filament eager-loading | `with()` in Resource query vs. separate service | `with()` in `SiteResource::getEloquentQuery()` is the Filament v4 pattern | `with(['latestUptime', 'latestVersion', 'latestHealth', 'latestActivity'])` |

**Rationale summary:** The domain classes (`Degradation`, `UptimeChecker`, `VersionTracker`, `SiteHealthCollector`, `ActivityCollector`, `RestClient`) contain the verified classification/severity/3-strike/tokenless logic. Any rewrite of this logic risks breaking the E2E fixture matrix. Keeping them Laravel-agnostic with a repository port is the only safe path. Eloquent adapters are thin mechanical mappings.

## Data Flow

```
config/sites.php ──→ SiteRegistry::syncFromConfig() ──→ SiteRepository port
                                                          │
    ┌─────────────────────────────────────────────────────┘
    │
    ▼
Artisan Command ──→ Collector::runAll() ──→ Domain Collectors
    │                                           │
    │    ┌──────────────────────────────────────┘
    │    │
    │    ▼
    └─── UptimeChecker ──→ RestClient ──→ WP Fixtures
          │
          └─────────────→ UptimeCheckRepository::save()

    ┌─── Degradation::classify() ──→ RestClient ──→ WP Fixtures
    │         │
    │         └─────────→ SiteRepository::setState()
    │
    └─── VersionTracker::collect() ──→ RestClient ──→ WP Fixtures
          │
          └─────────→ VersionSnapshotRepository::save()

Filament Dashboard ──→ SiteResource::getEloquentQuery()
                          │
                          └── with([latestUptime, latestVersion, ...])
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `laravel/` | Create | Laravel 12 scaffold (new dir) |
| `domain/Collector/Collector.php` | Create | Ported verbatim from `src/Collector/` |
| `domain/Collector/SiteReport.php` | Create | Ported verbatim |
| `domain/Collector/CollectionReport.php` | Create | Ported verbatim |
| `domain/SiteRegistry/SiteRegistry.php` | Create | Ported; `Sqlite`/`PDO` replaced by `SiteRepository` port |
| `domain/SiteRegistry/Site.php` | Create | Ported verbatim |
| `domain/SiteRegistry/SiteState.php` | Create | Ported verbatim |
| `domain/Degradation/Degradation.php` | Create | Ported verbatim |
| `domain/Uptime/UptimeChecker.php` | Create | Ported; `Sqlite`/`PDO` replaced by `UptimeCheckRepository` port |
| `domain/Version/VersionTracker.php` | Create | Ported; `Sqlite`/`PDO` replaced by `VersionSnapshotRepository` port |
| `domain/SiteHealth/SiteHealthCollector.php` | Create | Ported; `Sqlite`/`PDO` replaced by `SiteHealthSnapshotRepository` port |
| `domain/Activity/ActivityCollector.php` | Create | Ported; `Sqlite`/`PDO` replaced by `ActivitySnapshotRepository` port |
| `domain/Infra/RestClient.php` | Create | Ported verbatim (curl wrapper, zero deps) |
| `domain/Port/SiteRepository.php` | Create | Interface: `all()`, `find()`, `findByUrl()`, `setState()`, `syncFromConfig()` |
| `domain/Port/*Repository.php` | Create | 4 snapshot repository interfaces |
| `app/Models/Site.php` | Create | Eloquent model; implements `SiteRepository` |
| `app/Models/*Snapshot.php` | Create | 4 Eloquent snapshot models; implement port interfaces |
| `app/Repositories/Eloquent*Repository.php` | Create | Adapter classes mapping Eloquent ↔ port |
| `database/migrations/2025_*_create_monitor_tables.php` | Create | 5-table migration replicating SQLite schema |
| `app/Console/Commands/CollectorUptimeCommand.php` | Create | `collector:uptime`; exit 0/1/2 |
| `app/Console/Commands/CollectorDeepCommand.php` | Create | `collector:deep`; exit 0/1/2 |
| `app/Console/Commands/CheckAllCommand.php` | Create | `monitor:check all`; exit 0/1/2 |
| `app/Providers/AppServiceProvider.php` | Modify | Bind repository interfaces to Eloquent adapters |
| `app/Filament/Resources/SiteResource.php` | Create | List, View, Edit; no Create |
| `app/Filament/Widgets/*Widget.php` | Create | Stats widgets with eager-loading |
| `routes/console.php` | Modify | Scheduler: uptime every 5min, deep every 6h |
| `bin/e2e-assert.sh` | Modify | Re-target commands to `php artisan collector:deep`, `php artisan monitor:check all` |
| `Dockerfile` | Modify | Build Laravel app in `/app/laravel`; copy `wp-latest-version.cache.json` |
| `docker-compose.yml` | Modify | Mount `laravel/` as `/app/laravel`; update cron/entrypoint |
| `crontab` | Modify | Single line: `* * * * * cd /app/laravel && php artisan schedule:run` |
| `bin/collector.php` | Delete | Replaced by artisan commands |
| `bin/mediadev` | Delete | Replaced by artisan commands |
| `src/Auth/Auth.php` | Delete | Replaced by Laravel/Filament auth |
| `web/*.php` | Delete | Replaced by Filament |
| `src/Dashboard/Dashboard.php` | Delete | Replaced by Filament widgets |
| `src/Cli/Reporter.php` | Delete | Replaced by artisan output |

## Interfaces / Contracts

```php
namespace Domain\Port;

interface SiteRepository {
    /** @return Site[] */
    public function all(): array;
    public function find(int $id): ?Site;
    public function findByUrl(string $url): ?Site;
    public function setState(int $id, SiteState $state, int $consecutiveFailures): void;
    public function syncFromConfig(array $sites): void;
}

interface UptimeCheckRepository {
    public function save(int $siteId, UptimeResult $result): void;
}

interface VersionSnapshotRepository {
    public function save(int $siteId, ?string $core, array $plugins, array $themes, string $severity): void;
}

interface SiteHealthSnapshotRepository {
    public function save(int $siteId, ?int $score, array $tests, bool $unavailable): void;
}

interface ActivitySnapshotRepository {
    public function save(int $siteId, array $posts, bool $unavailable): void;
}
```

Domain classes type-hint these interfaces. Eloquent adapters implement them. `AppServiceProvider` binds interfaces to adapters.

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | Domain classes (`Degradation`, `UptimeChecker`, etc.) | PHPUnit with repository mocks; runnable without Laravel |
| Unit | `CollectionReport::hasCritical()` semantics | PHPUnit with mocked `SiteReport` |
| Integration | Eloquent adapters against SQLite in-memory | Laravel Pest; assert row exists after `save()` |
| Integration | Artisan commands exit codes | Laravel Pest `artisan()` helper; assert exit codes 0/1/2 |
| Integration | Scheduler cadence | Laravel `assertSchedule` or custom test |
| E2E | `bin/e2e-assert.sh` (re-targeted) | Full Docker compose with fixtures; assert 12/12 checks pass |

## Threat Matrix

| Boundary | Minimum adversarial cases | Applicability | Design response | Planned RED tests |
|---|---|---|---|---|
| Documentation-like paths | `README.sh`, executable Markdown | N/A — no executable docs in change | — | — |
| Git repository selection | `git -C`, relative/absolute paths | N/A — no VCS automation | — | — |
| Commit state | staged, `commit -a`, empty index | N/A — no commit automation | — | — |
| Push state | tracking branch, first push, explicit refspec | N/A — no push automation | — | — |
| PR commands | explicit `--head`, environment prefix, composed commands | N/A — no PR automation | — | — |
| Shell command surface | `php artisan collector:uptime` / `deep` / `monitor:check all` | Applicable | Exit-code parity 0/1/2; machine-readable `name  state` output; stderr on config error | E2E assert verifies exit codes per fixture; unit tests assert `hasCritical()` → DOWN/red severity |
| Process integration (cron/scheduler) | Scheduler entrypoint vs. direct artisan invocation | Applicable | Single cron line `schedule:run` delegates to Laravel Scheduler; cadence declared in `routes/console.php` | Integration test: assert `schedule:run` triggers `uptime` at 5-min boundary and `deep` at 6h boundary |
| Executable classification | Removal of `bin/collector.php` and `bin/mediadev` | Applicable | `bin/*` deleted; E2E harness updated to invoke artisan; no stale executable references | E2E assert script is the RED test — if `bin/*` referenced, script fails |
| Subprocess (e2e-assert.sh) | `docker compose exec` artisan commands inside monitor container | Applicable | Assert script uses `php artisan` inside container; same `awk '{print $2}'` parsing | E2E harness itself — 12 checks per run |

## Migration / Rollout

No data migration — the same SQLite file is reused. Rollout: build new container from `Dockerfile` (Laravel variant), switch traffic. Rollback: revert to vanilla container image. Vanilla `src/`, `web/`, `bin/` remain in repo until explicit cleanup.

## Open Questions

- [ ] Should `laravel/` be a Git submodule or a plain subdirectory? (Plain subdirectory keeps monorepo simplicity.)
- [ ] Filament v4 exact widget API may differ from v3 — verify `StatsOverviewWidget` signature during apply.
