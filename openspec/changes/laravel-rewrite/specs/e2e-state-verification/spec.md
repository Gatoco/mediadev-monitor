# Delta for E2E State Verification

> Capability: `e2e-state-verification` — DELTA spec. Command surface moves from `bin/*` to artisan; exit-code contract (EV-02..EV-05) + fixture→state matrix preserved; `e2e-assert.sh` re-targeted.

## ADDED Requirements

### Requirement: EV-A1 — Artisan output compatibility

`php artisan collector:deep` and `php artisan monitor:check all` MUST emit per-site rows in a format grep-compatible with the existing `e2e-assert.sh` assertions.

#### Scenario: Deep output parity
- GIVEN the `wp-full` fixture is healthy
- WHEN `php artisan collector:deep` runs
- THEN output MUST contain a row matching `^wp-full\b` with the state column extractable by `awk '{print $2}'`.

#### Scenario: Check all output parity
- GIVEN the same fixture set as vanilla
- WHEN `php artisan monitor:check all` runs
- THEN output MUST match the `e2e-assert.sh` grep patterns for per-fixture state extraction.

## MODIFIED Requirements

### Requirement: EV-01 — Per-fixture classification

A verification script MUST assert each fixture's classified `SiteState` equals its expected seed state.
(Previously: same requirement; scenario updated to artisan command surface.)

#### Scenario: EV-01 / EV-10 — Per-fixture classification
- GIVEN all 5 fixtures healthy (except `down`)
- WHEN `php artisan collector:deep` runs against the seeded config
- THEN each fixture's classified state MUST equal its expected; `wp-full` MUST NOT be `wp-degraded`; `non-wp` MUST NOT be `wp-full`.

### Requirement: EV-02 — Clean exit

`php artisan monitor:check all` MUST exit 0 when no fixture is critical.
(Previously: `bin/mediadev check all` MUST exit 0 when no fixture is critical.)

#### Scenario: Clean exit
- GIVEN only non-critical fixtures present
- WHEN `php artisan monitor:check all` runs
- THEN it MUST exit 0.

### Requirement: EV-03 — Critical exit

`php artisan monitor:check all` MUST exit 1 when at least one fixture is `down` or RED-critical.
(Previously: `bin/mediadev check all` MUST exit 1 when at least one fixture is `down` or RED-critical.)

#### Scenario: Critical exit
- GIVEN the `down` and `wp-outdated` fixtures present
- WHEN `php artisan monitor:check all` runs
- THEN it MUST exit 1.

### Requirement: EV-04 — Deep exit semantics

`php artisan collector:deep` MUST follow the same exit-code semantics as `monitor:check all`.
(Previously: `bin/collector.php deep` MUST follow the same exit-code semantics as `check all`.)

### Requirement: EV-05 — Error exit

Both artisan commands MUST exit 2 on usage/config errors.
(Previously: both `bin/*` commands MUST exit 2 on usage/config errors.)

#### Scenario: EV-04 / EV-05 — Exit semantics
- GIVEN a misconfigured `config/sites.php`
- WHEN either artisan command runs
- THEN it MUST exit 2 with a config error.

### Requirement: EV-06 — 3-strike down

`down` fixture MUST become `down` only after 3 consecutive failed probes (3-strike rule).
(Previously: same requirement; scenario updated to artisan command surface.)

#### Scenario: 3-strike down
- GIVEN `down` is unreachable
- WHEN fewer than 3 consecutive probes fail via `php artisan collector:uptime`, state MUST NOT yet be `down`
- AND WHEN the 3rd consecutive failure occurs, state MUST become `down`.

### Requirement: EV-10 — No false positives

Verification MUST assert no false positives: `wp-full` ≠ `wp-degraded`; `non-wp` ≠ `wp-full`.
(Previously: same requirement; scenario updated to artisan command surface.)

#### Scenario: EV-10 — False positive guard
- GIVEN `php artisan collector:deep` output for all fixtures
- WHEN states are extracted
- THEN `wp-full` MUST NOT equal `wp-degraded` and `non-wp` MUST NOT equal `wp-full`.

### Requirement: EV-11 — Idempotent re-runs

Running verification twice in succession MUST yield identical classifications (idempotent).
(Previously: same requirement; scenario updated to artisan command surface.)

#### Scenario: Idempotent re-runs
- GIVEN the verification has run once via `php artisan collector:deep`
- WHEN it runs again immediately
- THEN every per-fixture classification MUST be identical and no residual state MUST affect the second run.

## REMOVED Requirements

### Requirement: Old `bin/*` command surface

(Reason: Commands are replaced by Laravel Artisan equivalents; `bin/collector.php` and `bin/mediadev` are removed from the repo.)
(Migration: All consumers, including `e2e-assert.sh` and documentation, MUST reference `php artisan collector:uptime|deep` and `php artisan monitor:check all`.)
