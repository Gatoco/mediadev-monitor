# Design: Phase 4 — Docker + E2E Verification

## Technical Approach

Extend single-service compose with local WordPress + MySQL (Docker network `http://wordpress:80`), seed four SiteState reps, add wp-cli oneshot for AP, add `bin/e2e-assert.sh` (curl+grep over stable HTML markers). Activity collector: tokenless-first for `/wp/v2/posts` only. Maps to docker-deployment (DR1–10), e2e-verification (ER1–11), activity (AR1–11).

## Architecture Decisions

### D1 — Compose topology

| Option | Tradeoff | Decision |
|---|---|---|
| Merge into `docker-compose.yml` w/ `profiles: [e2e]` | One file; `up` skips e2e unless `--profile e2e` | ✅ |
| Separate `docker-compose.e2e.yml` | Zero prod risk; two files | Rejected |

Services: `monitor` (+`depends_on: wordpress(service_healthy)` under profile), `wordpress` (`wordpress:php8.3-apache`, `8081:80`, healthcheck `curl -fsS http://localhost/wp-json/`), `mysql` (`mysql:8.0`, healthcheck `mysqladmin ping`, vol `mediadev-wp-db`), `wp-cli` (`wordpress:cli` oneshot). Monitor→WP: `http://wordpress:80`.

### D2 — AP bootstrap

| Option | Tradeoff | Decision |
|---|---|---|
| A. wp-cli oneshot creates user+AP, prints token | Reproducible | ✅ |
| B. Manual README step | Brittle | Rejected |
| C. REST bootstrap w/ admin password | Admin pwd in env | Rejected |

`bin/wp-bootstrap.sh`: `wp user get monitor \|\| wp user create`, then `wp user application-password create`. Token to stdout for paste into `config/sites.php` (gitignored).

### D3 — Q#3: activity collector tokenless-first

| Endpoint | Tokenless-first? | Why |
|---|---|---|
| `/wp-json/wp/v2/posts` | ✅ | Public by default (AR1) |
| `/wp-json/wp/v2/plugins`, `/themes` | ❌ | Always AP (VersionTracker) |
| `/wp-json/wp-site-health/v1/tests` | ❌ | Always AP (SiteHealthCollector) |

`ActivityCollector::collect()`: (1) `get($endpoint, null)`; (2) `200`→available; (3) `401`/`403`+AP→retry, `200` persist / `401`/`403` unavailable; (4) `404`/`0`→unavailable. Backward compatible.

### D4 — E2E seed (`config/sites.php`)

| ID | name | url | type | wp_user | token | State |
|---|---|---|---|---|---|---|
| 1 | wp-full | `http://wordpress:80` | `wp` | `monitor` | AP | `wp-full` |
| 2 | wp-degraded | `http://wordpress:80` | `auto` | `null` | `null` | `wp-degraded` (plugins/themes/health 401; posts 200 tokenless, AR10) |
| 3 | non-wp | `https://example.org` | `non-wp` | `null` | `null` | `non-wp` |
| 4 | down | `https://192.0.2.1` | `auto` | `null` | `null` | `down` (RFC 5737) |

`wp-degraded` reuses same WP without AP → `Collector::runOne()` L102 marks degraded.

### D5 — E2E assert (`bin/e2e-assert.sh`)

Bash, `set -euo pipefail`, curl+grep, cookie jar. Env: `BASE_URL` (default `http://localhost:8080`), `E2E_USER`, `E2E_PASS`.

| Step | Marker |
|---|---|
| unauth redirect | `302` |
| login valid/invalid | `302`+cookie / `class="error"` |
| dashboard | `<title>Mediadev Monitor — Dashboard</title>` |
| four states | `>wp-full<`, `>wp-degraded<`, `>non-wp<`, `>down<` |
| semaphore | `dot green`/`yellow`/`red` |
| stats | `<b style="color:#ef4444">`, `<b style="color:#f59e0b">` |
| site detail | `<h2>Uptime`, `<h2>Versiones`, `<h2>Site Health`, `<h2>Actividad reciente` |
| missing site / logout | `id=999999`→`302` / →`302` login |
| persistence | collector + down/up + relogin → row present |

Exit: `0` pass, `1` fail, `2` setup error. Per-check PASS/FAIL + summary (ER10).

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `docker-compose.yml` | Modify | Add `wordpress`, `mysql`, `wp-cli` under `profiles: [e2e]`; vol `mediadev-wp-db`; monitor `depends_on` |
| `Dockerfile` | Modify | Optional `jq`; keep `php:8.3-cli` |
| `bin/e2e-assert.sh` | Create | Bash E2E assert (D5) |
| `bin/wp-bootstrap.sh` | Create | wp-cli oneshot: user + AP |
| `config/sites.example.php` | Modify | 4-site E2E seed template |
| `src/Activity/ActivityCollector.php` | Modify | Tokenless-first (D3) |
| `crontab` | Unchanged | — |
| `openspec/specs/activity/spec.md` | Modify (archive) | Accept delta post-verify |

## Testing Strategy

| Layer | What | How |
|-------|------|-----|
| Unit | ActivityCollector: 200 tokenless, 401→AP 200/401, 404, 0; normalization | PHPUnit, RestClient mock |
| Integration | up healthy 120s; collector writes SQLite; persists down/up | Shell (DR3/6/7) |
| E2E | 4 states, auth, semaphore, detail, persistence, missing id | `bin/e2e-assert.sh` |
| RED | stopped WP→exit 2; wrong creds→login-fail passes; WP not healthy→down not crash; re-run→no dup snapshots | Shell |

## Threat Matrix

Touched: shell commands + process integration. Routing/VCS/PR/exec-classification: all N/A.

| Boundary | Applicability | Design response | RED tests |
|---|---|---|---|
| Shell commands | Applicable — bash w/ curl, docker exec, wp | `set -euo pipefail`, no eval, URLs from env; wp-cli idempotent (`wp user get \|\| create`) | unreachable BASE_URL→exit 2; wp-bootstrap re-run idempotent |
| Process integration | Applicable — cron in container; wp-cli depends_on WP healthy | healthcheck gates; collector idempotent; WP down→site `down` (not crash) | monitor before WP healthy→down; collector re-run→no dup snapshots |

## Migration / Rollout

No DB migration (schema unchanged, `wp_user` from `807a359`). No code migration (tokenless-first backward compatible). Compose additive (`profiles: [e2e]`; `up` w/o profile=prod). Rollback: revert compose, delete two bin scripts. `config/sites.php` gitignored.

## Open Questions

- [ ] wp-cli AP: shared-volume auto-inject vs stdout paste? Design: paste.
- [ ] `mysql:8.0` vs `mariadb:11`? Design: `mysql:8.0`.