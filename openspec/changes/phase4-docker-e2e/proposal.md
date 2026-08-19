# Proposal: Phase 4 — Docker + E2E Verification

## Intent

The 7 capabilities are implemented and CLI-verified, but the Docker build has never run, the dashboard has never been tested end-to-end, and cron has never been validated inside the container. Phase 4 proves the whole stack works together reproducibly — without depending on the client's real sites or AP tokens — so the tío can later drop in his 28 sites and trust the deployment.

## Scope

### In Scope
- Extend `docker-compose.yml` with a local WordPress target (`wordpress:php8.3-apache` + MySQL) to exercise `wp-full` state.
- Build + `docker compose up` the monitor container; validate cron jobs (uptime 5min, deep 6h) by running collectors manually.
- Verify SQLite persistence across container restart (volume `mediadev-data`).
- Automated E2E assert script (curl + grep over HTML) covering the 4 SiteState states, dashboard login/logout, semaphore, site detail, and persistence.
- Resolve Q#3 experimentally: probe `/wp/v2/posts` against local WP with and without AP; update `activity` spec accordingly.

### Out of Scope
- CI (GitHub Actions) — deferred to Phase 5.
- Real client sites / AP tokens — not available yet.
- New collectors or dashboard features — only validation + fixes discovered during E2E (those become separate changes).

## Capabilities

### New Capabilities
- `docker-deployment`: extends Dockerfile/compose with local WP + MySQL service; validates build, up, cron, and volume persistence of the monitor container.
- `e2e-verification`: automated assert script validating the 4 SiteState states, dashboard auth flow, semaphore rendering, site detail, and SQLite persistence.

### Modified Capabilities
- `activity`: Q#3 resolution — if `/wp/v2/posts` is public without AP, the activity spec requirement must reflect that (collector works tokenless; AP only when hardened).

## Approach

Add a `wordpress` + `mysql` service to compose, generate an AP in the local WP, register it in `config/sites.php` alongside a non-wp and an unreachable host, then run an assert script that drives the dashboard via curl and checks HTML output for each state.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `docker-compose.yml` | Modified | Add `wordpress` + `mysql` services |
| `config/sites.php` | Modified | Register local WP (with/without AP), non-wp, down host |
| `bin/e2e-assert.sh` | New | Automated dashboard/state assert script |
| `openspec/specs/activity/spec.md` | Modified | Reflect Q#3 outcome (public vs AP) |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Local WP + MySQL heavy/slow to boot | Med | Pin versions, healthcheck on mysql before WP |
| Q#3 outcome changes activity behavior | Med | Resolve experimentally first; update spec before finalizing |
| Dashboard asserts brittle to HTML changes | Low | Assert on stable markers (semaphore class, status text) |

## Rollback Plan

Revert compose additions and remove the assert script; `config/sites.php` is gitignored so no secrets leak. No schema or capability changes are committed until verified.

## Dependencies

- Docker + docker-compose available locally.
- Local WP reachable on a fixed port for the assert script.

## Success Criteria

- [ ] `docker compose up` builds and boots monitor + WP + MySQL cleanly.
- [ ] Manual collector runs (`uptime`, `deep`) succeed inside the container.
- [ ] SQLite survives a container restart.
- [ ] Assert script passes for all 4 SiteState states.
- [ ] Dashboard login/logout + semaphore verified via asserts.
- [ ] Q#3 resolved and `activity` spec updated.
