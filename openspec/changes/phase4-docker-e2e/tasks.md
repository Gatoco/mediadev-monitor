# Tasks: Phase 4 — Docker + E2E Verification

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~180-220 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | size-exception |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: size-exception
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Compose + bootstrap + collector refactor + assert + verify | PR 1 | `bin/e2e-assert.sh; echo $?` | `docker compose --profile e2e up -d` | Revert compose additions, rm `bin/e2e-assert.sh` + `bin/wp-bootstrap.sh` |

## Phase 1: Compose Infrastructure

- [x] 1.1 Add `mysql` service to `docker-compose.yml` (`mysql:8.0`, healthcheck `mysqladmin ping`, vol `mediadev-wp-db`, `profiles: [e2e]`)
- [x] 1.2 Add `wordpress` service (`wordpress:php8.3-apache`, `8081:80`, depends_on mysql healthy, healthcheck `curl -fsS /wp-json/`, `profiles: [e2e]`)
- [x] 1.3 Add `wp-cli` oneshot service (`wordpress:cli`, depends_on wordpress healthy, `profiles: [e2e]`)
- [x] 1.4 Add monitor `depends_on: wordpress(service_healthy)` under e2e profile; keep `config/sites.php`+`config/auth.php` ro mounts (DR5)
- [x] 1.5 Ensure `Dockerfile` installs `curl` in monitor image (DR2)
- [x] 1.6 Create `bin/wp-bootstrap.sh`: `wp user get monitor \| wp user create`, then `wp user application-password create`; token to stdout; idempotent
- [x] 1.7 RED (process integration): run `bin/wp-bootstrap.sh` twice → no error, same user, idempotent
- [x] 1.8 Verify `docker compose --profile e2e up -d` healthy ≤120s (DR3); `docker compose ps` none exited

## Phase 2: ActivityCollector Tokenless-First

- [ ] 2.1 RED (process integration): PHPUnit — collector re-run on same site produces no duplicate snapshot row
- [ ] 2.2 Modify `src/Activity/ActivityCollector.php::collect()`: tokenless `GET /wp-json/wp/v2/posts?per_page=5` first (AR1)
- [ ] 2.3 `200` (incl. `[]`) → available (AR2, AR3); `404`/`0` → unavailable (AR5)
- [ ] 2.4 `401`/`403` + AP configured → retry Basic Auth; `200` persist / `401`/`403` unavailable (AR4, AR6)
- [ ] 2.5 Skip endpoint for `non-wp`/`down` (AR7); normalize fields with defaults (AR8); shape `{"posts":[],"unavailable":bool}` (AR9)
- [ ] 2.6 PHPUnit: 200 tokenless, 401→AP 200, 401→AP 401, 404, 0, missing-field normalization

## Phase 3: E2E Assert Script

- [ ] 3.1 RED (shell commands): `bin/e2e-assert.sh` skeleton `set -euo pipefail` + env defaults; run vs stopped stack → exit `2`
- [ ] 3.2 RED (shell commands): unreachable `BASE_URL` → exit `2`
- [ ] 3.3 Unauth redirect (ER3): `index.php` + `site.php?id=1` → `302` login
- [ ] 3.4 Login valid/invalid (ER4): `302`+cookie / `class="error"`
- [ ] 3.5 Logout (ER5): session invalidated → follow-up `302` login
- [ ] 3.6 Four states + semaphore (ER2, ER6): `>wp-full<`, `dot green`/`yellow`/`red`, non-wp marker
- [ ] 3.7 Site detail (ER7): `<h2>Uptime`, `<h2>Versiones`, `<h2>Site Health`, `<h2>Actividad reciente`
- [ ] 3.8 Edges (ER9, ER10): `id=999999`→`302`; `down`+`red`
- [ ] 3.9 Persistence (ER8): collector + `down && up` + relogin → row present
- [ ] 3.10 Per-check PASS/FAIL + summary (ER10); env params (ER11)

## Phase 4: Verification

- [ ] 4.1 RED (process integration): start monitor before WP healthy → site classifies `down`, no crash
- [ ] 4.2 `docker compose build monitor` clean (DR2); `curl localhost:8080/login.php` → `200` (DR4)
- [ ] 4.3 `docker compose exec monitor php bin/collector.php uptime` → exit 0, new SQLite row (DR7)
- [ ] 4.4 Deep run: local WP → `wp-full` (DR8); `wp-degraded` seed → posts 200 tokenless (AR10)
- [ ] 4.5 SQLite persists `down && up` (DR6); ro-mount touch fails (DR5)
- [ ] 4.6 `bin/e2e-assert.sh` end-to-end → exit `0`

## Phase 5: Cleanup

- [ ] 5.1 Update `config/sites.example.php` with 4-site E2E seed + e2e profile docs
- [ ] 5.2 Archive `openspec/changes/phase4-docker-e2e/specs/activity/spec.md` → `openspec/specs/activity/spec.md`
- [ ] 5.3 Confirm `crontab` unchanged; `docker compose up` (no profile) boots only monitor