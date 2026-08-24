# Proposal: Laravel 12 + Filament v4 Rewrite

## Intent

Replace vanilla-PHP mediadev-monitor with a NEW Laravel 12 + Filament v4 app (PHP 8.3) porting the verified DOMAIN logic verbatim — preserving exit-code contract, SQLite schema, E2E fixture matrix, and `latestStableWp()` cache — while Laravel/Filament owns infra (auth, config, scheduler, ORM, UI). Approach 1 (exploration): port 6 domain classes as a Laravel-agnostic library; repository interfaces as persistence port; Eloquent models implement the port.

## Scope

### In Scope
- New Laravel 12 + Filament v4 app (new dir, same repo). Stack verified: PHP 8.3; E2E fixture stack unchanged.
- Port 6 domain classes (`Degradation`, `UptimeChecker`, `VersionTracker`, `SiteHealthCollector`, `ActivityCollector`, `RestClient`) byte-for-byte as Laravel-agnostic library.
- Repository interfaces (persistence port); Eloquent models implement them, replacing `Sqlite`/`PDO` (only structural change to domain).
- Eloquent migrations replicating 5-table SQLite schema (JSON as TEXT, `TEXT NOT NULL DEFAULT (datetime('now'))`, `SiteState` enum).
- Artisan commands replacing `bin/collector.php` + `bin/mediadev`: exit-code parity 0/1/2, `hasCritical()` = DOWN or `versions.severity === 'red'`.
- Scheduler: 5-min uptime / 6h deep via single cron line.
- Filament SiteResource + dashboard widgets/stats with eager-loading (no N+1).
- Laravel/Filament auth replacing `src/Auth/Auth.php`; re-target `bin/e2e-assert.sh` to artisan (compatible machine-readable output).
- Preserve `wp-latest-version.cache.json` Docker injection (OQ3).

### Out of Scope
- Rewriting domain classification/severity/3-strike/tokenless logic (preserved as-is); `realistic-site-fixtures` fixture stack changes; queue/async collection.

## Capabilities

### New Capabilities
- `laravel-app`: Laravel 12 + Filament v4 scaffold, config/env, auth, Scheduler wiring.
- `eloquent-persistence`: 5-table Eloquent models + migrations replicating SQLite schema; repository interfaces + Eloquent adapters as persistence port.
- `filament-dashboard`: Filament v4 SiteResource + dashboard widgets/stats with eager-loading (replaces `web/*.php` + `Dashboard`).
- `artisan-commands`: Artisan commands mapping `collector.php uptime|deep` + `mediadev check all` with exit-code parity and machine-readable output.

### Modified Capabilities
- `e2e-state-verification`: command surface moves from `bin/*` to artisan; exit-code contract (EV-02..EV-05) + fixture→state matrix preserved; `e2e-assert.sh` re-targeted.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/` (new) | New | Filament Resources, widgets, auth, Providers, Scheduler. |
| `domain/` (new, from `src/`) | New | 6 domain classes verbatim + repository interfaces. |
| `app/Models/` + `database/migrations/` | New | Eloquent models implementing ports; 5-table migration. |
| `app/Console/Commands/` | New | `CollectorUptimeCommand`, `CollectorDeepCommand`, `CheckAllCommand`. |
| `web/*.php`, `src/Auth/`, `src/Infra/Sqlite.php`, `src/Infra/Config.php`, `bin/collector.php`, `bin/mediadev` | Removed | Replaced by Laravel/Filament + artisan. |
| `bin/e2e-assert.sh`, `Dockerfile`, `docker-compose.yml`, `crontab`, `composer.json` | Modified | Re-target to artisan; Laravel container + single cron line; new deps. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Exit-code / `hasCritical()` regression | Med | Spec enforces 0/1/2 parity; E2E contract unchanged. |
| SQLite→Eloquent schema drift (JSON TEXT, `datetime('now')`) | Med | Migration replicates exact column types; spec asserts schema equality. |
| E2E harness output-format break | Med | Artisan emits compatible `name  state` rows; assert script updated in lockstep. |

## Rollback Plan

New app lives in new dir/branch; vanilla app (`src/`, `web/`, `bin/`) stays untouched until cutover. Rollback = revert branch / don't deploy new container; `crontab` + `bin/collector.php` keep running original. No data migration (SQLite file portable; Eloquent reads same schema).

## Success Criteria

- [ ] 6 domain classes ported verbatim; `SiteState` 5-state enum preserved end-to-end.
- [ ] Eloquent migrations replicate 5-table SQLite schema (JSON TEXT, `datetime('now')` default).
- [ ] Artisan commands exit 0/1/2 matching `bin/*`; `hasCritical()` semantics preserved.
- [ ] Scheduler runs uptime 5-min / deep 6h via single cron line.
- [ ] `bin/e2e-assert.sh` passes (12/12) against artisan commands.
- [ ] Filament dashboard renders all sites; widgets eager-load (no N+1 at 28 sites).
- [ ] `wp-latest-version.cache.json` injection resolves `wp-outdated` fixture.