# Tasks: Realistic Sites E2E

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~350-380 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | size-exception |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: size-exception
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| 1 | Fixture infra + bootstrap + assert + RED tests + cleanup | PR 1 | `bin/e2e-assert.sh` | `docker compose --profile e2e up` | revert compose + docker/ + bin/ |

## Phase 1: Fixture Infrastructure

- [x] 1.1 Create `docker/fixture-wp/Dockerfile` from `wordpress:php8.3-apache`; copy `fixture-mu.php` + `enable-app-passwords.php` to mu-plugins.
- [x] 1.2 Create `docker/fixture-wp/fixture-mu.php`: `FIXTURE_TYPE=outdated` → remove `wp_generator` + emit `WordPress 6.8.8`; `hardened` → drop `/wp/v2/users` unauth (401), allow w/AP (200), unregister `site-health` (404).
- [x] 1.3 Create `docker/fixture-wp/enable-app-passwords.php` ensuring AP enabled for `monitor` user.
- [x] 1.4 Create `docker/fixture-wp/mysql-init.sql`: `CREATE DATABASE IF NOT EXISTS wp_full, wp_outdated, wp_hardened`.
- [x] 1.5 Create `docker/non-wp/Dockerfile` + `nginx.conf`: static `/` 200, `/wp-json/` 404.
- [x] 1.6 Create `docker/down/Dockerfile`: `CMD ["sleep","infinity"]` (no listener).
- [x] 1.7 Modify `docker-compose.yml`: add `net-sites` + `net-monitor` networks, `mediadev/fixture-wp` build, 5 fixture services (wp-full/outdated/hardened on net-sites, non-wp, down), shared `mysql` with init script, `/ap-tokens` volume, `--profile e2e` gating, healthchecks; monitor attached to BOTH networks (D1).

## Phase 2: Bootstrap & Cache Injection (OQ3)

- [x] 2.1 Modify `bin/wp-bootstrap.sh`: accept `FIXTURE_NAME` + `WP_URL` env; write token to `/ap-tokens/<name>.token`; confirm idempotent re-run (no duplicate user/AP).
- [x] 2.2 Add 3 wp-cli oneshots to `docker-compose.yml` (one per WP fixture) running `wp-bootstrap.sh` into the shared `/ap-tokens` volume (D6).
- [x] 2.3 **OQ3 CRITICAL**: inject `wp-latest-version.cache.json` (version > 6.8.8, e.g. 7.0.x) into monitor container (mount or COPY) so `VersionTracker::latestStableWp()` returns the cache instead of offline fallback `6.6.2`; without this EV-01 fails (outdated severity would be GREEN).

## Phase 3: E2E Assert Script & Seed Config

- [x] 3.1 Create `bin/e2e-assert.sh`: phases — wait healthchecks → bootstrap APs → seed `config/sites.php` from `/ap-tokens/*.token` → run `bin/mediadev check all` + `bin/collector.php deep` → grep per-fixture rows vs expected states (RF-12/EV-01) → exit 0/1/2 per EV-02..EV-05.
- [x] 3.2 `bin/e2e-assert.sh` asserts Q#3 tokenless-first (EV-08): `wp-full` `/wp/v2/posts` 200 without AP; AP retry exercised on `wp-hardened` (EV-09).
- [x] 3.3 `bin/e2e-assert.sh` asserts 3-strike down (EV-06): `down` fixture reaches `down` only after 3 consecutive probes; recovery resets counter (EV-07).
- [x] 3.4 `bin/e2e-assert.sh` asserts idempotent re-runs (EV-11) and no false positives (EV-10): `wp-full`≠`wp-degraded`, `non-wp`≠`wp-full`.
- [x] 3.5 `bin/e2e-assert.sh` emits per-fixture row + overall PASS/FAIL summary (EV-12).
- [x] 3.6 Seed `config/sites.php` (gitignored): 5 entries mapping fixture URL → expected state (RF-12).

## Phase 4: RED Tests (Threat Matrix)

- [x] 4.1 **RED-A** (shell early run): run `e2e-assert.sh` before fixtures healthy → MUST exit 2 (no false PASS).
- [x] 4.2 **RED-B** (shell idempotency): run `wp-bootstrap.sh` twice on same fixture → no duplicate user/AP, token unchanged.
- [x] 4.3 **RED-C** (process race): start monitor before fixtures healthy → sites report `down`, not crash; after fixtures healthy, re-run → correct states (EV-07).
- [x] 4.4 **RED-D** (state pollution): run `collector.php deep` twice → no duplicate snapshot rows in DB.
- [x] 4.5 Full E2E run: `docker compose --profile e2e up` → `bin/e2e-assert.sh` → all 5 fixtures classify to expected states (EV-01) with correct exit codes (EV-02..EV-05).

## Phase 5: Cleanup & Docs

- [x] 5.1 Modify `config/sites.example.php`: document 5 fixture URLs + expected states + `/ap-tokens` integration.
- [x] 5.2 Confirm prod compose unaffected: default `docker compose up` (no `--profile e2e`) stays single-monitor.
- [x] 5.3 Verify `src/Activity/ActivityCollector.php` already implements D7 tokenless-first (AC-01..AC-09); if not, refactor: first GET `null` auth, single AP retry on 401/403.
- [x] 5.4 Archive specs: confirm `openspec/changes/realistic-sites-e2e/specs/{realistic-site-fixtures,e2e-state-verification,activity}/spec.md` align with implementation.
- [x] 5.5 Teardown verification: `docker compose --profile e2e down -v` removes fixtures, networks, volumes (RF-13).