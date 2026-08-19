# Tasks: Lean Cleanup

## Phase 1: Presentation

- [x] 1.1 Create `web/style.css` with the verbatim union of the 3 inline `<style>` blocks (index/site/login).
- [x] 1.2 `web/index.php`: remove inline `<style>`, add `<link rel="stylesheet" href="style.css">` in `<head>`.
- [x] 1.3 `web/site.php`: remove inline `<style>`, add `<link rel="stylesheet" href="style.css">` in `<head>`.
- [x] 1.4 `web/login.php`: remove inline `<style>`, add `<link rel="stylesheet" href="style.css">` in `<head>`.

## Phase 2: Dead schema

- [x] 2.1 `src/Infra/Sqlite.php`: remove `users` + `session` CREATE TABLE blocks from `migrate()`.
- [x] 2.2 `src/Infra/Sqlite.php`: remove `tryAlter()` + its call.

## Phase 3: Dead code

- [x] 3.1 `src/Infra/RestClient.php`: remove `RestResponse::ok()`.
- [x] 3.2 `src/Infra/RestClient.php`: `timeoutMs`/`maxRetries` → constants `TIMEOUT_MS`/`MAX_RETRIES`.
- [x] 3.3 `src/Infra/RestClient.php`: drop `$method` param from `request()` (hardcode `'GET'`).
- [x] 3.4 `src/Version/VersionTracker.php`: remove private `basicAuth()`.
- [x] 3.5 `src/SiteHealth/SiteHealthCollector.php`: remove private `basicAuth()`.

## Phase 4: Cosmetic + empty dir

- [x] 4.1 `src/Version/VersionTracker.php`: docblock — drop phantom step-4 bullet.
- [x] 4.2 `src/Uptime/UptimeChecker.php`: docblock — drop `self-signed` claim.
- [x] 4.3 Remove empty `web/templates/` dir (git rm / rmdir).

## Phase 5: Verification

- [x] 5.1 `php -l` clean on all touched files.
- [x] 5.2 Fresh-DB check: after `docker volume rm mediadev-monitor_mediadev-data` + recreate, `sqlite_master` has only the 5 live tables (no users/session).
- [x] 5.3 `bin/e2e-assert.sh` → 12/12 exit 0 (LC-12 invariant).
- [x] 5.4 `wc -l` net delta ≥ 100 (LC-11).
- [x] 5.5 Smoke: login flow renders with shared stylesheet (curl login page + dashboard page 200 with link tag).

## Non-goals (locked by proposal D6)

- DO NOT touch `wp-bootstrap.sh` jq fallback (no jq in wordpress:cli — verified).
- DO NOT change retry/backoff, timeouts, exit codes, E2E script, fixtures.
