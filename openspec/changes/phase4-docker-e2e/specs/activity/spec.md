# Activity Specification

**Capability:** activity · **Change:** phase4-docker-e2e · **Type:** NEW (post-Q#3)
**Keywords:** RFC 2119 — MUST, SHALL, SHOULD, MAY

## Purpose

Defines how the monitor collects recent WP post activity from `/wp-json/wp/v2/posts`, resolving Q#3: the endpoint is **public by default** on a standard install (tokenless `GET` returns `200`); APs are **only required when the site hardens** the endpoint (`401`/`403`). The collector MUST handle both modes.

## Requirements

| ID | Requirement | Priority |
|----|-------------|----------|
| AR1 | For any WP-classified site, the collector MUST attempt `GET /wp-json/wp/v2/posts?per_page=<limit>` **tokenless** first. | MUST |
| AR2 | A `200` with a posts array (possibly empty) MUST be treated as **available** and persisted. | MUST |
| AR3 | A `200` with `[]` MUST persist as available with `posts: []`, NOT unavailable. | MUST |
| AR4 | On `401`/`403` (hardened), the collector SHOULD retry with the site's AP Basic Auth, if a token is configured. | SHOULD |
| AR5 | On `404` or status `0` (unreachable), the collector MUST mark activity **unavailable**: `posts: []`, `unavailable: true`. | MUST |
| AR6 | If the hardened AP retry also fails (`401`/`403`), the collector MUST mark unavailable. | MUST |
| AR7 | For `non-wp` or `down` sites, the collector MUST skip the endpoint (no HTTP) and persist nothing new. | MUST |
| AR8 | Each post MUST be normalized to `title`, `link`, `date`. Missing fields default to `(sin título)`, `null`, `null`. | MUST |
| AR9 | The collector MUST persist a snapshot row per site per run with JSON `{"posts": [...], "unavailable": bool}`. | MUST |
| AR10 | The `limit` parameter SHOULD default to `5` and MAY be overridden. | SHOULD |
| AR11 | The collector MAY log the resolved mode (tokenless vs AP) but MUST NOT log tokens. | MAY |

## Scenarios

### AR1 — Tokenless attempt first
**Given** a site classified `wp-full` with no AP
**When** `collect()` runs
**Then** the first request to `/wp-json/wp/v2/posts` MUST carry no `Authorization` header.

### AR2 — Public endpoint returns posts
**Given** a standard (non-hardened) WordPress site
**When** the tokenless `GET` returns `200` with a non-empty posts array
**Then** the snapshot MUST persist with `unavailable: false` and the normalized posts.

### AR3 — Public endpoint, empty posts
**Given** a WP site with no published posts
**When** the tokenless `GET` returns `200` with `[]`
**Then** the snapshot MUST persist with `unavailable: false`, `posts: []`.

### AR4 — Hardened endpoint, AP retry succeeds
**Given** a WP site where tokenless `GET` returns `401` and a valid AP is configured
**When** `collect()` runs
**Then** the collector SHOULD retry with Basic Auth and, on `200`, persist posts with `unavailable: false`.

### AR5 — Unreachable or 404
**Given** a site where the endpoint returns `404` or host is unreachable (status `0`)
**When** `collect()` runs
**Then** the snapshot MUST persist with `unavailable: true`, `posts: []`.

### AR6 — Hardened, AP retry still fails
**Given** a site returning `403` tokenless and an invalid/expired AP
**When** the AP retry also returns `401`/`403`
**Then** the snapshot MUST persist with `unavailable: true`, `posts: []`.

### AR7 — Non-WP and down sites skipped
**Given** a site classified `non-wp` or `down`
**When** a `deep` cycle runs
**Then** NO request to `/wp-json/wp/v2/posts` MUST be issued and no new snapshot row MUST be inserted.

### AR8 — Normalization of fields
**Given** a `200` response whose post object lacks `title.rendered`
**When** posts are normalized
**Then** the title MUST default to `(sin título)`, `link` to `null`, `date` to `null`.

### AR9 — Snapshot shape
**Given** any successful or unavailable collection
**When** the snapshot is persisted
**Then** `posts_json` MUST decode to `{"posts": [...], "unavailable": bool}`.

### AR10 (edge) — `wp-degraded` site
**Given** a site classified `wp-degraded` (some endpoints 403/404, site online)
**When** `collect()` runs
**Then** the collector MUST still attempt tokenless and follow AR2–AR6; on `200`, activity MUST be available even though the site is degraded.