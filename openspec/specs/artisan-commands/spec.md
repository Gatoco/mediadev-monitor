# Artisan Commands Specification

> Capability: `artisan-commands` — NEW full spec. Artisan commands mapping `collector.php uptime|deep` + `mediadev check all` with exit-code parity and machine-readable output.

## Purpose

MUST replace `bin/collector.php` and `bin/mediadev` with Laravel Artisan commands that preserve exit-code semantics (0/1/2) and emit compatible machine-readable output for E2E assertions.

## Requirements

| ID | Requirement |
|----|-------------|
| AC-01 | `php artisan collector:uptime` MUST execute the same `UptimeChecker` logic as `bin/collector.php uptime`. |
| AC-02 | `php artisan collector:deep` MUST execute the same `Collector::runOne deep` logic as `bin/collector.php deep`. |
| AC-03 | `php artisan monitor:check all` MUST execute the same logic as `bin/mediadev check all`. |
| AC-04 | All three commands MUST exit 0 when no critical issues exist. |
| AC-05 | All three commands MUST exit 1 when at least one site is `down` OR when `versions.severity === 'red'`. |
| AC-06 | All three commands MUST exit 2 on usage or config errors. |
| AC-07 | `collector:uptime` and `collector:deep` MUST emit per-site rows in the format `name  state`, identical to `bin/collector.php`. |
| AC-08 | `monitor:check all` MUST emit per-site rows compatible with the `e2e-assert.sh` grep patterns. |
| AC-09 | `hasCritical()` semantics MUST be preserved: DOWN OR `versions.severity === 'red'` is critical. |
| AC-10 | `bin/collector.php` and `bin/mediadev` MUST be removed from the repo. |

## Scenarios

### AC-01 / AC-07 — Uptime command parity
**Given** the same fixtures and config as vanilla
**When** `php artisan collector:uptime` runs
**Then** output MUST match `bin/collector.php uptime` line-for-line; exit code MUST match.

### AC-02 / AC-07 — Deep command parity
**Given** the same fixtures and config as vanilla
**When** `php artisan collector:deep` runs
**Then** output MUST match `bin/collector.php deep` line-for-line; exit code MUST match.

### AC-03 / AC-08 — Check all parity
**Given** the same fixtures and config as vanilla
**When** `php artisan monitor:check all` runs
**Then** output MUST be grep-compatible with `e2e-assert.sh`; exit code MUST match `bin/mediadev check all`.

### AC-04 — Clean exit
**Given** only non-critical fixtures
**When** any of the three commands runs
**Then** exit code MUST be 0.

### AC-05 / AC-09 — Critical exit
**Given** the `down` and `wp-outdated` fixtures present
**When** any of the three commands runs
**Then** exit code MUST be 1 because `down` is critical and `wp-outdated` has RED severity.

### AC-06 — Config error exit
**Given** a missing or malformed `config/sites.php`
**When** any command runs
**Then** exit code MUST be 2 with a clear error to STDERR.

### AC-10 — Binary removal
**Given** the change is deployed
**When** `ls bin/` runs
**Then** `collector.php` and `mediadev` MUST NOT exist.

## Edge Cases

- Zero sites configured MUST exit 0 (nothing to check, nothing critical).
- A site flapping between states mid-run MUST use the state captured at check time.
