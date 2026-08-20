# Eloquent Persistence Specification

> Capability: `eloquent-persistence` — NEW full spec. 5-table Eloquent models + migrations replicating SQLite schema; repository interfaces + Eloquent adapters as persistence port.

## Purpose

MUST replace direct PDO/SQLite access in domain classes with a persistence port: repository interfaces consumed by domain code, implemented by Eloquent models and migrations that replicate the existing 5-table schema exactly.

## Requirements

| ID | Requirement |
|----|-------------|
| EP-01 | Eloquent migrations MUST create 5 tables: `sites`, `uptime_checks`, `version_snapshots`, `site_health_snapshots`, `activity_snapshots`. |
| EP-02 | Each migration MUST replicate column names, types, and constraints of the existing SQLite schema. |
| EP-03 | JSON data MUST be stored as `TEXT` (same as current `TEXT` columns with JSON encoded values). |
| EP-04 | Timestamp columns MUST default to `datetime('now')` equivalent (e.g. `useCurrent()` or `now()`). |
| EP-05 | `sites.current_state` MUST be backed by a `SiteState` enum with 5 states: `wp-full`, `wp-degraded`, `non-wp`, `down`, `unknown`. |
| EP-06 | A repository interface MUST exist for each domain persistence need: `SiteRepository`, `UptimeCheckRepository`, `VersionSnapshotRepository`, `SiteHealthSnapshotRepository`, `ActivitySnapshotRepository`. |
| EP-07 | Eloquent models MUST implement the repository interfaces; domain classes MUST type-hint the interfaces, not Eloquent concrete types. |
| EP-08 | The 6 domain classes (`Degradation`, `UptimeChecker`, `VersionTracker`, `SiteHealthCollector`, `ActivityCollector`, `RestClient`) MUST be ported verbatim, with only PDO/SQLite replaced by repository interfaces. |
| EP-09 | `SiteRegistry::syncFromConfig()` logic MUST persist via the `SiteRepository` port, not raw PDO. |
| EP-10 | No data migration is required; the new Eloquent app MUST read the same SQLite file the vanilla app wrote. |

## Scenarios

### EP-01 / EP-02 — Schema parity
**Given** a fresh database
**When** migrations run
**Then** `PRAGMA table_info(...)` for each table MUST match the original schema column-for-column.

### EP-03 — JSON as TEXT
**Given** a `version_snapshots` row with `plugins_json`
**When** the value is read
**Then** it MUST be a JSON-encoded string stored in a `TEXT` column, identical to vanilla storage.

### EP-04 — Default timestamps
**Given** an `INSERT` without explicit timestamp
**When** the row is queried
**Then** `created_at`/`updated_at`/`ts` MUST default to the current time.

### EP-05 — SiteState enum
**Given** a `sites` row with `current_state = 'wp-degraded'`
**When** Eloquent casts it
**Then** the model MUST return the `SiteState::WP_DEGRADED` enum case.

### EP-06 / EP-07 — Port isolation
**Given** `VersionTracker` depends on `VersionSnapshotRepository`
**When** an Eloquent adapter implements the interface
**Then** `VersionTracker::collect()` MUST persist without referencing Eloquent directly.

### EP-08 — Verbatim domain port
**Given** the vanilla `Degradation::classify()` source
**When** compared to the ported version
**Then** only `Sqlite`/`PDO` references MAY differ; all logic MUST match.

## Edge Cases

- An enum value not in the 5-state set MUST map to `unknown` on read.
- A migration rerun on an existing DB MUST be idempotent (no schema errors).
