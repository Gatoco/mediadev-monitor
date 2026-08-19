# E2E State Verification Specification

> Capability: `e2e-state-verification` — NEW full spec. Asserts `bin/mediadev check all` and `bin/collector.php deep` classify each of the 5 fixtures into the exact expected `SiteState`, with correct exit codes; verifies Q#3 tokenless-first + 3-strike down detection end-to-end.

## Purpose

MUST provide a deterministic assertion layer over the monitor's classification pipeline: map each fixture to one expected state and verify framework invariants (no false positives, idempotent re-runs, correct exit semantics).

## Requirements

| ID | Requirement |
|----|-------------|
| EV-01 | A verification script MUST assert each fixture's classified `SiteState` equals its expected seed state. |
| EV-02 | `check all` MUST exit 0 when no fixture is critical. |
| EV-03 | `check all` MUST exit 1 when at least one fixture is `down` or RED-critical. |
| EV-04 | `collector.php deep` MUST follow the same exit-code semantics as `check all`. |
| EV-05 | Both commands MUST exit 2 on usage/config errors. |
| EV-06 | `down` fixture MUST become `down` only after 3 consecutive failed probes (3-strike rule). |
| EV-07 | Recovery from `down` MUST reset the failure counter; next success MUST NOT stay `down`. |
| EV-08 | Verification MUST exercise Q#3 tokenless-first: public `/wp/v2/posts` 200 MUST be available without AP. |
| EV-09 | Verification MUST exercise AP retry: 401/403 with AP configured MUST retry with AP and succeed when AP is valid. |
| EV-10 | Verification MUST assert no false positives: `wp-full` ≠ `wp-degraded`; `non-wp` ≠ `wp-full`. |
| EV-11 | Running verification twice in succession MUST yield identical classifications (idempotent). |
| EV-12 | Verification MUST emit a per-fixture row + overall PASS/FAIL summary. |

### Expected fixture → state matrix

| Fixture | Expected | Exit |
|---------|----------|------|
| wp-full | `wp-full` | 0 |
| wp-outdated | `wp-full` + RED | 1 |
| wp-hardened | `wp-degraded` | 0 |
| non-wp | `non-wp` | 0 |
| down | `down` | 1 |

## Scenarios

### EV-01 / EV-10 — Per-fixture classification
**Given** all 5 fixtures healthy (except `down`)
**When** `collector.php deep` runs against the seeded config
**Then** each fixture's classified state MUST equal its expected; `wp-full` MUST NOT be `wp-degraded`; `non-wp` MUST NOT be `wp-full`.

### EV-02 — Clean exit
**Given** only non-critical fixtures present
**When** `check all` runs, it MUST exit 0.

### EV-03 — Critical exit
**Given** the `down` and `wp-outdated` fixtures present
**When** `check all` runs, it MUST exit 1.

### EV-04 / EV-05 — Exit semantics
**Given** a misconfigured `sites.php`
**When** either command runs, it MUST exit 2 with a config error.

### EV-06 — 3-strike down
**Given** `down` is unreachable
**When** fewer than 3 consecutive probes fail, state MUST NOT yet be `down`
**When** the 3rd consecutive failure occurs, state MUST become `down`.

### EV-07 — Recovery reset
**Given** a fixture is `down`
**When** a subsequent probe succeeds, state MUST leave `down` and the failure counter MUST reset to 0.

### EV-08 — Q#3 tokenless-first
**Given** `wp-full` exposes public `/wp/v2/posts` returning 200
**When** `ActivityCollector` runs without an AP configured, activity MUST be available and no AP retry MUST occur.

### EV-09 — AP retry on 401/403
**Given** `wp-hardened` returns 401 on `/wp/v2/posts` and AP is configured
**When** `ActivityCollector` runs, it MUST retry with the AP and the retry MUST succeed.
### EV-11 — Idempotent re-runs
**Given** the verification has run once
**When** it runs again immediately, every per-fixture classification MUST be identical and no residual state MUST affect the second run.

### EV-12 — Reporting
**Given** the verification completes
**Then** output MUST include a per-fixture row + overall summary; overall MUST be PASS only if every fixture PASSed.

## Edge Cases

- A fixture that flaps mid-run MUST be FAIL.
- If `UptimeChecker` marks `down` before 3 strikes, verification MUST FAIL.
- If `ActivityCollector` retries with AP on a public 200, verification MUST FAIL (Q#3 violation).
- If `wp-outdated` core severity is not RED, verification MUST FAIL.