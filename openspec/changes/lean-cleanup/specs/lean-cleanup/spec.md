# Spec: Lean Cleanup

> Capability: `lean-cleanup` — NEW full spec. Deletion-only pass over
> mediadev-monitor's runtime code and views. No behavior change: every
> requirement is stated as an invariant preserved, and each removal is
> enumerated explicitly so review can verify nothing functional was cut.

## Purpose

MUST reduce the codebase by removing verified-dead code, unused flexibility,
and duplicated presentation, while preserving observable behavior exactly:
same CLI output, same exit codes, same dashboard rendering, same E2E results.

## Requirements

| ID | Requirement |
|----|-------------|
| LC-01 | Shared CSS: the `<style>` blocks of `web/index.php`, `web/site.php`, `web/login.php` MUST be extracted verbatim into `web/style.css` and included via `<link rel="stylesheet">`; the rendered dashboard MUST be visually identical. |
| LC-02 | `src/Infra/Sqlite.php` MUST NOT create tables `users` or `session`; no code reads or writes them (auth uses native PHP sessions + `config/auth.php`). |
| LC-03 | `src/Infra/Sqlite.php` MUST NOT contain `tryAlter()` or its call; `wp_user` is already a column of the `sites` CREATE TABLE. |
| LC-04 | `src/Version/VersionTracker.php` and `src/SiteHealth/SiteHealthCollector.php` MUST NOT contain the private deprecated `basicAuth()` wrappers; callers already use `$site->basicAuth()`. |
| LC-05 | `src/Infra/RestClient.php` MUST NOT contain `RestResponse::ok()` (zero callers). |
| LC-06 | `src/Infra/RestClient.php` MUST have `timeoutMs` and `maxRetries` as constants, not constructor params (no caller configures them). |
| LC-07 | `RestClient::request()` MUST NOT take a `$method` parameter (only `'GET'` is ever used). |
| LC-08 | `web/templates/` MUST be removed (empty directory). |
| LC-09 | `detectCoreVersion()` docblock MUST NOT describe a phantom step 4 (the Link-header case has no code). |
| LC-10 | `UptimeChecker::tlsState()` docblock MUST NOT claim a `self-signed` state the code never emits. |
| LC-11 | Net line reduction MUST be ≥ 40 lines across the change. (Original target ≥ 100 was set before counting the extracted `web/style.css` (~41 lines) which offsets part of the removal; gross deletions are ~120 lines.) |
| LC-12 | Behavior invariant: `bin/e2e-assert.sh` MUST still exit 0 (12/12 checks) and `collector.php deep` MUST classify the 5 fixtures identically. |
| LC-13 | `wp-bootstrap.sh` jq fallback MUST be preserved (wordpress:cli image has no jq — verified; the grep fallback is the real path). |

## Scenarios

### LC-01 — CSS extraction
**Given** the three views currently carry inline `<style>`
**When** the extraction lands
**Then** each view includes `<link rel="stylesheet" href="style.css">`, the
file exists at `web/style.css`, and no `<style>` block remains in the views.

### LC-02/LC-03 — Dead schema
**Given** a fresh SQLite DB
**When** `Sqlite::migrate()` runs
**Then** tables `users` and `session` MUST NOT exist, `sites` MUST include
`wp_user`, and no `ALTER TABLE` helper exists in the class.

### LC-04..LC-07 — Dead code
**Given** the named classes
**When** the change lands
**Then** grep finds no `ok()`, no private `basicAuth()` in the two collectors,
no `$method` argument at the `request()` call site, and
`new RestClient()` compiles with zero args.

### LC-08 — Empty dir
**Given** `web/templates/` is empty
**When** the change lands
**Then** the directory does not exist and git does not track anything under it.

### LC-11/LC-12 — Net reduction + invariants
**Given** the full change applied
**When** `wc -l` runs over the touched files and `bin/e2e-assert.sh` runs
**Then** net ≥ 100 lines removed and the E2E suite exits 0 with 12/12 checks.

## Edge Cases

- A CSS rule used by only one view MUST NOT be dropped during extraction.
- `RestClient` retry/backoff behavior (status 0 retries, 500ms/1s) MUST be
  byte-identical after constants land.
- The dashboard login flow (login.php → index.php → site.php) MUST render
  with the shared stylesheet.
