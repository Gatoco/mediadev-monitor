# Realistic Site Fixtures Specification

> Capability: `realistic-site-fixtures` — NEW full spec. 5 network-isolated fixtures emulating verified MediaDev field cases (Engram obs #183/#184) so the monitor proves state classification end-to-end.

## Purpose

MUST provide 5 deterministic, network-isolated HTTP stacks + a monitor host so collectors observe the same HTTP/REST signals each real site exhibits, such that `Degradation::classify()`, `UptimeChecker`, `VersionTracker`, `SiteHealthCollector`, and `ActivityCollector` produce the expected `SiteState`.

## Requirements

| ID | Requirement |
|----|-------------|
| RF-01 | Fixtures MUST run on two isolated networks: `net-sites` (5 fixtures + DBs) and `net-monitor` (monitor). |
| RF-02 | Monitor MUST reach fixtures only via `net-sites`; fixtures MUST NOT be on `net-monitor`. |
| RF-03 | There MUST be exactly 5 fixtures: `wp-full`, `wp-outdated`, `wp-hardened`, `non-wp`, `down`. |
| RF-04 | Each WP fixture MUST run a real WordPress stack + own DB; `non-wp` MUST serve static content; `down` MUST expose no HTTP listener. |
| RF-05 | Startup MUST be gated by healthchecks; collectors MUST NOT run before all healthy fixtures are ready. |
| RF-06 | A bootstrap step MUST create a monitor user + AP in each WP fixture. |
| RF-07 | `wp-full` MUST respond 200 to `/wp-json/` and expose public `/wp/v2/posts` unauthenticated. |
| RF-08 | `wp-outdated` MUST emit `<meta name="generator" content="WordPress 6.8.8">`; other WP fixtures MUST emit current stable. |
| RF-09 | `wp-hardened` MUST return 401 on `/wp/v2/users` without token, 200 with valid AP, 404 on unauthenticated `/wp-site-health/v1/tests`. |
| RF-10 | `non-wp` MUST return 200 on `/` and 404 on `/wp-json/`. |
| RF-11 | `down` MUST refuse connections (HTTP 000) on every probe. |
| RF-12 | A seed config MUST map each fixture URL to its expected state. |
| RF-13 | Teardown MUST remove fixtures, networks, and named volumes; re-setup MUST be idempotent. |

### Expected-state seed mapping

| Fixture | Expected state |
|---------|----------------|
| wp-full | `wp-full` |
| wp-outdated | `wp-full` + RED |
| wp-hardened | `wp-degraded` |
| non-wp | `non-wp` |
| down | `down` |

## Scenarios

### RF-01 / RF-02 — Network isolation
**Given** networks `net-sites` and `net-monitor` exist
**When** the monitor issues HTTP to a fixture
**Then** the request MUST traverse `net-sites`; fixtures MUST NOT reach `net-monitor`.

### RF-03 / RF-04 / RF-05 — Readiness gating
**Given** the fixtures profile is starting
**When** any healthcheck is not yet passing
**Then** collectors MUST NOT be invoked; once all pass, all 5 fixtures MUST be addressable.

### RF-06 — Bootstrap AP
**Given** a fresh WP fixture with no monitor user
**When** bootstrap runs
**Then** a monitor user + valid AP MUST exist; re-running MUST be idempotent.

### RF-07 — wp-full REST
**Given** `wp-full` is healthy
**When** an unauthenticated request hits `/wp-json/`
**Then** it MUST return 200; `/wp/v2/posts` MUST return 200 without a token.

### RF-08 — Outdated core meta
**Given** `wp-outdated`
**When** `VersionTracker` reads the home HTML
**Then** `<meta name="generator">` MUST equal `WordPress 6.8.8`; other WP fixtures MUST emit current stable.

### RF-09 — Hardened endpoints
**Given** `wp-hardened`
**When** `/wp/v2/users` is hit without a token, it MUST return 401; with a valid AP, MUST return 200
**When** `/wp-site-health/v1/tests` is hit without a token, it MUST return 404.

### RF-10 — non-wp static
**Given** `non-wp`
**When** `/` is requested, MUST return 200; when `/wp-json/` is requested, MUST return 404.

### RF-11 — Down fixture
**Given** `down` exposes no listener
**When** any HTTP probe is issued
**Then** the connection MUST be refused, yielding HTTP 000.

### RF-13 — Teardown & idempotency
**Given** fixtures and volumes exist
**When** teardown runs
**Then** all fixtures, networks, and named volumes MUST be removed; re-setup MUST produce the same 5 fixtures with no residual state.

## Edge Cases

- A fixture whose healthcheck flaps MUST NOT block collectors once stabilised.
- If `wp-outdated`'s generator override fails, teardown MUST run before re-setup.
- A WP fixture reachable on `net-monitor` MUST fail a topology assertion.