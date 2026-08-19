# E2E Verification Specification

**Capability:** e2e-verification · **Change:** phase4-docker-e2e · **Type:** NEW
**Keywords:** RFC 2119 — MUST, SHALL, SHOULD, MAY

## Purpose

An automated assert script (`bin/e2e-assert.sh`) driving the dashboard via curl and verifying the four SiteState values, auth flow, semaphore rendering, site detail, and SQLite persistence — without real client sites.

## Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| ER1 | The script MUST run as `bin/e2e-assert.sh` against a running stack and exit `0` on full pass, non-zero on any failure. | MUST |
| ER2 | The script MUST assert all four SiteState values: `wp-full`, `wp-degraded`, `non-wp`, `down`. | MUST |
| ER3 | Unauthenticated requests to `index.php` and `site.php` MUST redirect to `login.php`. | MUST |
| ER4 | Login with valid credentials MUST land on `index.php`; invalid credentials MUST stay on `login.php` with an error marker. | MUST |
| ER5 | `logout.php` MUST destroy the session; subsequent `index.php` requests MUST redirect to `login.php`. | MUST |
| ER6 | Each state MUST render a stable semaphore CSS token: `green` (wp-full), `yellow` (wp-degraded), `red` (down); `non-wp` a distinct stable marker. | MUST |
| ER7 | `site.php?id=<site_id>` MUST render name, state, uptime history, and (for WP sites) version/health/activity sections. | MUST |
| ER8 | The script MUST verify SQLite persistence: after a collector run + restart + re-login, prior rows MUST still appear. | MUST |
| ER9 | Asserts MUST match stable markers (CSS class, status text), NOT layout, whitespace, or copy. | MUST |
| ER10 | The script SHOULD print a per-check PASS/FAIL line and a final summary. | SHOULD |
| ER11 | The script MAY be parameterized by dashboard base URL and credentials via env vars. | MAY |

## Scenarios

### ER1 — Script pass/fail exit code
**Given** the stack is up and seeded with one site per SiteState
**When** `bin/e2e-assert.sh` runs
**Then** it MUST exit `0` if every check passes and non-zero otherwise.

### ER2 — Four states asserted
**Given** the seeded stack
**When** the script queries `index.php` (authenticated)
**Then** it MUST find a row for each of `wp-full`, `wp-degraded`, `non-wp`, `down`.

### ER3 — Unauthenticated redirect
**Given** no active session
**When** `curl -sS -o /dev/null -w '%{http_code}' localhost:8080/index.php` runs (no cookie)
**Then** the response MUST be `302` to `login.php`. Same for `site.php?id=1`.

### ER4 — Login flow
**Given** valid credentials in `config/auth.php`
**When** a POST to `login.php` is made with them
**Then** the response MUST redirect to `index.php` and set a session cookie. With wrong credentials, the body MUST contain an error marker and no session cookie.

### ER5 — Logout flow
**Given** an authenticated session
**When** `logout.php` is requested
**Then** the session cookie MUST be invalidated and a follow-up to `index.php` MUST redirect to `login.php`.

### ER6 — Semaphore colors
**Given** the seeded stack and an authenticated session
**When** `index.php` is fetched
**Then** the `wp-full` row MUST contain class `green`, `wp-degraded` `yellow`, `down` `red`, and `non-wp` a stable distinguishable marker.

### ER7 — Site detail
**Given** an authenticated session
**When** `site.php?id=<wp_full_id>` is fetched
**Then** the body MUST contain name, state `wp-full`, uptime history, version, site-health, and activity sections.

### ER8 — Persistence after restart
**Given** the stack ran one `uptime` cycle and the dashboard shows a row
**When** `docker compose restart monitor` runs and the dashboard is re-queried (re-authenticated)
**Then** the same row MUST still appear with its prior state.

### ER9 (edge) — Missing site id
**Given** an authenticated session
**When** `site.php?id=999999` is requested
**Then** the response MUST redirect to `index.php` (no crash, no leaked error).

### ER10 (edge) — Down site classification
**Given** a registered site whose host is unreachable (3 consecutive failures)
**When** the script inspects that row on `index.php`
**Then** state text MUST be `down` and the semaphore `red`.