# Proposal: Realistic Sites E2E

## Intent

Prove the framework classifies each **real MediaDev site state** correctly (from field exploration, obs #183/#184) using 5 containerized, network-isolated emulations — replacing the cancelled `phase4-docker-e2e` single-generic-WP direction. Each fixture is a real WordPress/HTTP stack emulating a verified real case, and the monitor runs "from the other side" (separate network). Goal: does the framework detect the correct state per scenario?

## Scope

### In Scope
- Compose with **two networks**: `net-sites` (5 fixtures) + `net-monitor` (monitor), monitor reaching sites via attachable/external network config.
- **5 fixtures** (real `wordpress:php8.3-apache`, each its own DB): wp-full, wp-outdated (fake `meta generator` via mu-plugin for 6.8.8), wp-hardened (mu-plugin disabling `/wp/v2/users` + 404 `site-health/v1/tests` unauthenticated), non-wp (static nginx, 200 but `/wp-json/` 404), down (no-listener service → connection refused → HTTP 000).
- wp-cli bootstrap creating monitor user + AP in WP fixtures; 401-without-token / 200-with-token AP flow.
- `config/sites.php` seed mapping fixtures → expected states.
- Collector **state classification** assert script: `bin/mediadev check all` + `bin/collector.php deep` → each fixture's expected state.
- Verify Q#3 tokenless-first: `/wp/v2/posts` public without AP; AP only on 401/403.

### Out of Scope
- Dashboard asserts (login/semaphore/detail) — deferred.
- CI, real client sites / tío's AP tokens, new collectors.

## Capabilities

### New Capabilities
- `realistic-site-fixtures`: the 5 compose fixtures + 2-network orchestration + mu-plugin/bootstrap emulating real cases.
- `e2e-state-verification`: assert script mapping each fixture → expected SiteState + exit codes.

### Modified Capabilities
- `activity`: confirm Q#3 tokenless-first behavior (public posts without AP; AP only on 401/403) — already baked; spec confirms it.

## Approach

Define `net-sites` + `net-monitor`. 5 site services on `net-sites` (each its own WP/nginx + DB, `depends_on` gated by healthchecks). Monitor on `net-monitor`, its `sites.php` seeded with fixture URLs (via `net-sites`). wp-cli oneshots create users/APs and apply mu-plugins (generator override, endpoint hardening). Run collectors, assert classification.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `docker-compose.yml` | Modified | 2 networks + 5–7 services (monitor, 5 fixtures, wp-cli) |
| `bin/` | New | fixture mu-plugins, wp-bootstrap, e2e-assert.sh |
| `config/sites.example.php` | Modified | Document fixture URLs + expected states |
| `config/sites.php` (gitignored) | Modified | Seeded with 5 fixture entries |
| `openspec/changes/phase4-docker-e2e/` | Superseded | Replaced by this change |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| 5 real WP stacks heavy/slow to boot | Med | Pin versions, healthcheck gating, shared base image |
| Outdated-core faked via mu-plugin diverges from real WP | Med | Test generator override produces exact `WordPress 6.8.8` string VersionTracker reads |
| Network isolation (separate nets) adds compose friction | Med | Document attachable/external wiring; smoke-test cross-net HTTP first |
| One DB per WP fixture → disk/volume bloat | Low | Named volumes, prune on teardown |

## Rollback Plan

`docker compose --profile e2e down -v` removes fixtures + volumes. `config/sites.php` is gitignored (no secrets). Revert compose/bin changes; no committed schema/capability changes until verified.

## Dependencies

- Docker 29.x + compose 5.x.
- Real exploration data (obs #183/#184) as fixture truth source.

## Success Criteria

- [ ] Each of the 5 fixtures classified into expected state (wp-full, wp-full+RED, wp-degraded, non-wp, down) by `check all` / `collector.php deep`.
- [ ] `check all` renders all 5 states with correct exit codes.
- [ ] Q#3 verified: posts public without AP; AP path exercised on hardened fixture.
- [ ] Down fixture yields HTTP 000 → `down` after 3 strikes.
