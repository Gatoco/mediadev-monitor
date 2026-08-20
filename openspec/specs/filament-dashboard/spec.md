# Filament Dashboard Specification

> Capability: `filament-dashboard` — NEW full spec. Filament v4 SiteResource + dashboard widgets/stats with eager-loading; replaces `web/*.php` + `src/Dashboard/Dashboard.php`.

## Purpose

MUST provide a Filament v4 admin panel that lists all monitored sites, shows per-site detail, and renders dashboard widgets/stats, while eliminating N+1 queries at the current scale of ~28 sites.

## Requirements

| ID | Requirement |
|----|-------------|
| FD-01 | A `SiteResource` MUST exist in Filament with CRUD pages: List, View, Edit (no Create; sites sync from config). |
| FD-02 | The List page MUST display: name, URL, current `SiteState` (color-coded), consecutive failures, and last check timestamp. |
| FD-03 | The View page MUST show the latest snapshot from each of the 4 collectors: uptime, versions, site health, activity. |
| FD-04 | Dashboard widgets MUST display: total sites, sites by state count, average response time, latest version severity summary. |
| FD-05 | All list and widget queries MUST eager-load related snapshots; a list of 28 sites MUST NOT trigger N+1 queries. |
| FD-06 | The `web/*.php` files (`index.php`, `site.php`, `login.php`, `layout.php`) MUST be removed; Filament MUST serve all UI. |
| FD-07 | The vanilla `src/Dashboard/Dashboard.php` MUST be removed; its functionality MUST be replaced by Filament resources and widgets. |
| FD-08 | `SiteState` MUST render with a color-coded badge: `wp-full` green, `wp-degraded` yellow, `non-wp` cyan, `down` red, `unknown` gray. |

## Scenarios

### FD-01 / FD-02 — SiteResource list
**Given** 5 fixtures seeded
**When** the admin opens `SiteResource` List
**Then** all 5 sites MUST appear with name, URL, state badge, and failure count.

### FD-03 — SiteResource view
**Given** a site with uptime, version, health, and activity snapshots
**When** the admin opens the View page
**Then** the latest snapshot from each collector MUST be visible.

### FD-04 / FD-05 — Widgets with eager loading
**Given** 28 sites with related snapshots
**When** the dashboard loads
**Then** total sites, state counts, avg response time, and severity summary MUST render with at most 6 queries total.

### FD-05 — N+1 guard
**Given** the Filament debug toolbar or query logger is enabled
**When** the Site list renders for 28 sites
**Then** the query count MUST be <= 6 (sites + 4 snapshot types + count).

### FD-06 / FD-07 — Web removal
**Given** the change is deployed
**When** `ls web/` runs
**Then** only `style.css` (or nothing) remains; `index.php`, `site.php`, `login.php`, `layout.php` MUST NOT exist.

### FD-08 — State badges
**Given** a site in state `down`
**When** it appears in the list
**Then** the badge MUST be red; `wp-degraded` MUST be yellow; `wp-full` MUST be green.

## Edge Cases

- A site with zero snapshots MUST show "No data" instead of crashing.
- A site whose state is not in the enum MUST show `unknown` in gray.
