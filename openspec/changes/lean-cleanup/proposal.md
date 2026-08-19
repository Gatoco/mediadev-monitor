# Proposal: Lean Cleanup

## Intent

Apply the ponytail audit findings to mediadev-monitor: remove dead code,
unused flexibility, and duplicated presentation without changing any behavior.
Pure deletion/shrink pass — no features added, no semantics altered.

## Motivation

Audit of the full tree (2869 lines) found ~150 lines of dead weight: two
unused SQLite tables, a legacy migration helper whose column already exists,
deprecated delegating helpers, an unused response method, and ~80 lines of
inline CSS duplicated across three views.

## Scope

**In (deletion/shrink only):**
- `web/`: extract 3 duplicated inline `<style>` blocks into `web/style.css`.
- `src/Infra/Sqlite.php`: drop `users` + `session` tables (zero readers/writers;
  auth uses native PHP sessions + `config/auth.php`); drop `tryAlter()` + call
  (`wp_user` already in CREATE TABLE).
- `src/Version/VersionTracker.php` + `src/SiteHealth/SiteHealthCollector.php`:
  drop deprecated private `basicAuth()` wrappers.
- `src/Infra/RestClient.php`: drop `RestResponse::ok()` (0 callers); make
  `timeoutMs`/`maxRetries` constants (no caller configures them); drop the
  `$method` parameter (`request()` only ever receives `'GET'`).
- `web/templates/`: remove empty dir.
- Cosmetic comments: `detectCoreVersion()` phantom step-4 comment; `tlsState()`
  docblock claims `self-signed` which the code never emits.

**NOT touched (refuted/justified by evidence):**
- `wp-bootstrap.sh` jq fallback: `wordpress:cli` image has NO jq (verified),
  the grep fallback is the real path, not dead code.
- `e2e-assert.sh`, fixtures, compose: E2E infrastructure is spec-mandated.

## Success Criteria

- `php -l` clean on all touched files.
- `bin/e2e-assert.sh` still passes 12/12 (behavior unchanged).
- `docker compose --profile e2e up -d` + smoke run of `collector.php deep`.
- Net: ~-150 lines, 0 deps removed.

## Files

| File | Action |
|------|--------|
| `web/style.css` | New (shared CSS) |
| `web/index.php` | Modified (link CSS) |
| `web/site.php` | Modified (link CSS) |
| `web/login.php` | Modified (link CSS) |
| `src/Infra/Sqlite.php` | Modified (−2 tables, −tryAlter) |
| `src/Infra/RestClient.php` | Modified (−ok, −ctor params, −method arg) |
| `src/Version/VersionTracker.php` | Modified (−basicAuth, −comment) |
| `src/SiteHealth/SiteHealthCollector.php` | Modified (−basicAuth) |
| `src/Uptime/UptimeChecker.php` | Modified (−docblock claim) |
| `web/templates/` | Deleted (empty) |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Behavior drift in RestClient retry semantics | Low | E2E 12/12 re-run + deep run smoke |
| CSS extraction breaks a view | Low | Visual check via dashboard login smoke |
| Spec references dead tables | Low | No spec references `users`/`session` tables |
