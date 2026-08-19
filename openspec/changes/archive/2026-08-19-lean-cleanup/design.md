# Design: Lean Cleanup

> Technical design for `lean-cleanup`. Deletion-only change: the design is a
> checklist of precise mechanical edits, each with its blast radius. No new
> abstractions, no dependency changes.

## Decisions

### D1 — CSS: single stylesheet, scoped per view
- Extract the union of the three `<style>` blocks into `web/style.css`.
- The views share selectors with DIFFERENT values (`.card` 1.2rem vs 2.5rem,
  `.dot` 10px vs 12px, `body` flex vs block, `th/td` padding, `table` font):
  a flat union would silently change rendering. Scope by view with a class on
  `<body>` (`view-index` / `view-site` / `view-login`); rules identical across
  views stay global.
- Each view: delete inline `<style>...</style>` and add
  `<link rel="stylesheet" href="style.css">` in `<head>`.
- The built-in PHP server serves static files from `web/` (docroot), so
  `style.css` resolves without config.

### D2 — Sqlite: drop dead tables + legacy migration
- Remove the `users` and `session` CREATE TABLE blocks from `migrate()`.
- Remove `tryAlter()` and its single call; `wp_user` exists in the `sites`
  CREATE TABLE (line 47) — the ALTER was a dev-loop leftover.
- `CREATE TABLE IF NOT EXISTS` means pre-existing DBs keep their dead tables;
  harmless (no reader/writer), documented in the archive note.

### D3 — RestClient: constants + fixed method
- `private const TIMEOUT_MS = 10000;` and `private const MAX_RETRIES = 2;`
  replace the constructor params.
- `request(string $url, ?string $basicAuth)` — drop `$method`, hardcode
  `'GET'` in `curl_setopt_array`.
- `RestResponse::ok()` removed (no callers; callers test `=== 200` / `=== 0`).

### D4 — Collectors: drop deprecated wrappers
- `VersionTracker::basicAuth()` and `SiteHealthCollector::basicAuth()` are
  one-line delegates marked Deprecated; callers already use `$site->basicAuth()`.
  Remove both.

### D5 — Cosmetic comments
- `VersionTracker::detectCoreVersion()` docblock: drop the phantom step-4
  bullet (Link header case has no code).
- `UptimeChecker` docblock: `tlsState` claim of `self-signed` removed; the
  enum of returned states is `valid | expiring | expired | null`.

### D6 — NOT touched
- `wp-bootstrap.sh` jq fallback: `wordpress:cli` has no jq (verified at
  design time, exit 127) — the grep fallback is the live path.
- Retry/backoff, timeout semantics, exit codes, E2E script, fixtures.

## Verification plan

1. `php -l` on every touched PHP file.
2. `docker compose --profile e2e build monitor && up -d` (stack already up).
3. `bin/e2e-assert.sh` → 12/12 exit 0.
4. Fresh-DB check (LC-02): `php -r` listing `sqlite_master` after deleting
   the DB volume → only the 5 live tables.
5. `wc -l` net delta ≥ 100.
