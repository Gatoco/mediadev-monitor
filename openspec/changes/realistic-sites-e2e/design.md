# Design: realistic-sites-e2e

> Change: `realistic-sites-e2e` — 5 network-isolated fixtures + 2-network compose + E2E assert script proving `SiteState` classification for each verified MediaDev case.

## Technical Approach

Extend the current single-WP compose (`--profile e2e`) into a 5-fixture topology on `net-sites`, with the monitor joining both `net-sites` (outbound probes) and `net-monitor` (dashboard exposure). One reusable fixture image (`mediadev/fixture-wp`, from `wordpress:php8.3-apache`) parameterised by `FIXTURE_TYPE` + a shared MySQL with 5 DBs covers RF-03/RF-04. A mu-plugin `fixture-mu.php` overrides the meta generator (outdated case) and disables `/wp/v2/users` + `site-health` routes (hardened case). wp-cli oneshots per WP fixture create `monitor` user + AP, writing tokens to a shared `/ap-tokens` volume. A bash `bin/e2e-assert.sh` waits on healthchecks, bootstraps APs, seeds `config/sites.php`, runs `bin/mediadev check all` + `bin/collector.php deep`, and greps each fixture row against its expected state. **Gap discovered in code read**: `ActivityCollector::collect()` always sends `basicAuth()` on the first request — it does NOT implement AC-01 tokenless-first; this change MUST refactor it (first GET with `null` auth, retry once with AP only on 401/403) to satisfy AC-01..AC-09.

## Architecture Decisions

| ID | Choice | Alternatives | Rationale |
|----|--------|--------------|-----------|
| D1 | Monitor on BOTH `net-sites` + `net-monitor`; fixtures only on `net-sites` | (a) external network + static hosts | DNS resolves fixture hostnames via attach; sites stay isolated from monitor-side clients (RF-02) |
| D2 | One base image `mediadev/fixture-wp` (FIXTURE_TYPE env) + nginx for non-wp + no-listener for down | 5 separate Dockerfiles | Shared base reduces build time/disk; mu-plugin keyed by env keeps divergence in one file |
| D3 | Single `mysql` service, 5 DBs via init script | 5 mysql containers | 5x lighter; isolation irrelevant for fixtures |
| D4 | Outdated: `remove_action('wp_head','wp_generator')` + emit `WordPress 6.8.8` | output-buffering string replace | Clean hook swap; VersionTracker regex matches exactly (verified against `detectCoreVersion`) |
| D5 | Hardened: `rest_endpoints` filter drops `/wp/v2/users` (401 unauth, 200 w/AP); `rest_api_init` unregister site-health (404) | reverse-proxy rules | Native WP filters emulate real mediadev.cl behaviour exactly |
| D6 | wp-cli oneshots write AP tokens to `/ap-tokens/<fixture>.token` (shared volume); `e2e-assert.sh` reads them to build `sites.php` | manual paste / env vars | Fully automated, idempotent, no secrets in compose |
| D7 | `ActivityCollector` refactor: first GET with `null` auth; on 401/403 + AP present → single retry with AP | keep current behaviour | AC-01..AC-09 unmet today; required by EV-08/EV-09 |

## Data Flow

```
   net-monitor (10.x)                  net-sites (10.y)
  ┌────────────────┐              ┌──────────────────────────┐
  │  host:8080     │              │  wp-full:80   wp-outdated │
  │  ┌──────────┐  │   probes     │  wp-hardened  non-wp:80 │
  │  │ monitor  │──┼─────────────▶│  down (no listener)      │
  │  │ (php cli)│  │  HTTP /wp-json│  mysql:3306 (5 DBs)      │
  │  └──────────┘  │              │  wp-cli×3 (oneshot)      │
  │  /ap-tokens ◀──┼──tokens──────│  /ap-tokens volume       │
  └────────────────┘              └──────────────────────────┘
        │ join                          │ fixtures only here
        └──────────────────────────────┘
```

## File Changes

| Path | Action | Purpose |
|------|--------|---------|
| `docker-compose.yml` | Modify | 2 networks, `mediadev/fixture-wp` build, 5 fixture services, shared mysql, 3 wp-cli oneshots, `/ap-tokens` volume, `--profile e2e` gating, healthchecks |
| `docker/fixture-wp/Dockerfile` | New | Base WP image; copies `fixture-mu.php` + `enable-app-passwords.php` to mu-plugins |
| `docker/fixture-wp/fixture-mu.php` | New | Reads `FIXTURE_TYPE`: `outdated` → generator 6.8.8; `hardened` → drop users + site-health routes |
| `docker/fixture-wp/mysql-init.sql` | New | `CREATE DATABASE IF NOT EXISTS wp_full, wp_outdated, wp_hardened` |
| `docker/non-wp/nginx.conf` + `Dockerfile` | New | Static 200 `/`, 404 `/wp-json/` |
| `docker/down/Dockerfile` | New | `CMD ["sleep","infinity"]` — no listener |
| `bin/wp-bootstrap.sh` | Modify | Accept `FIXTURE_NAME` + `WP_URL` env; write token to `/ap-tokens/<name>.token`; idempotent (already is) |
| `bin/e2e-assert.sh` | New | Phases: wait health → bootstrap AP → seed `sites.php` → run `check all` + `collector deep` → grep per-fixture rows → exit 0/1/2 |
| `src/Activity/ActivityCollector.php` | Modify | Tokenless first, single AP retry on 401/403 (D7) |
| `config/sites.example.php` | Modify | Document 5 fixture URLs + expected states + `/ap-tokens` integration |
| `config/sites.php` (gitignored) | Generated | 5 entries written by `e2e-assert.sh` |

## Interfaces / Contracts

- `FIXTURE_TYPE` env: `full` | `outdated` | `hardened` (WP fixtures only)
- `wp-outdated` home HTML MUST contain `<meta name="generator" content="WordPress 6.8.8">` (matches `VersionTracker::detectCoreVersion` regex)
- `wp-hardened`: unauth `GET /wp-json/wp/v2/users` → 401; authed → 200; unauth `/wp-json/wp-site-health/v1/tests` → 404; `/wp/v2/posts` stays public 200 (so activity = available, degradation stems from health 404)
- `/ap-tokens/<fixture>.token`: one line, 24-char AP (no spaces)
- `sites.php` entry: `['url'=>'http://wp-full','name'=>'wp-full','type'=>'auto','wp_user'=>'monitor','token'=>'<from /ap-tokens>']`

## Testing Strategy

| Layer | Tests |
|-------|-------|
| Unit | `ActivityCollector` tokenless-first (mock RestClient: 200 → no AP; 401+AP → retry 200; 401 no AP → unavailable; 404 → unavailable) |
| Integration (compose) | `curl` probes per fixture asserting RF-07/08/09/10/11; cross-net assertion (fixtures CANNOT resolve monitor hostname) |
| E2E | `bin/e2e-assert.sh` end-to-end: 5 fixtures → expected states, exit 1 (down+outdated-red), Q#3 posts public, no false positives |
| RED | RED-A: assert before health → exit 2; RED-B: bootstrap twice → no duplicate user/AP; RED-C: monitor start before health → sites down, recover after; RED-D: collector re-run → no duplicate snapshots |

## Threat Matrix

| Row | Risk | RED test |
|-----|------|----------|
| Shell commands | wp-cli/curl/docker in bootstrap + assert | RED-A (early run), RED-B (idempotency) |
| Process integration | cron in monitor, wp-cli depends_on fixtures | RED-C (race), RED-D (state pollution) |

## Migration / Rollout

- No DB schema migration (existing tables unchanged).
- Code migration: `ActivityCollector` refactor (D7) — backward compatible (sites without AP behave identically).
- Compose: additive under `--profile e2e`; default `docker compose up` stays single-monitor (uncle's prod).
- Rollback: `docker compose --profile e2e down -v` + revert compose/bin/`ActivityCollector`; no committed secrets.

## Open Questions

- OQ1: Does `wp-hardened` returning 401 on `/wp/v2/users` (vs 403) require `rest_authentication_errors` filter or `rest_endpoints` removal? Spec says 401 — verify wp-cli/wp filter produces 401 not 403.
- OQ2: 3-strike down detection (EV-06) — does current `UptimeChecker::applyThreshold` implement 3 strikes, or only this change adds it? (Need to read `UptimeChecker` in tasks phase.)
- OQ3: `wp-outdated` plugin/theme updates — empty by default → `assess()` returns `green` only if core matches latest; since 6.8.8 ≠ latest, severity = `red`. Confirm `latestStableWp()` returns a version > 6.8.8 (offline fixture cannot reach api.wordpress.org → fallback 6.6.2 — **this would make 6.8.8 NEWER than fallback, severity green, breaking EV-01**). Must inject `wp-latest-version.cache.json` with `6.8.x` or higher into monitor container.

## Next Step

Ready for `sdd-tasks` — decompose D1..D7 + ActivityCollector refactor + 4 RED tests into ordered implementation tasks.