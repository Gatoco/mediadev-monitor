# Activity Specification

> Capability: `activity` — NEW, final post-Q#3 spec. Confirms the resolved tokenless-first behavior. No prior spec existed in `openspec/specs/activity/` (original SDD artifacts lost); this spec documents the FINAL behavior.

## Purpose

`ActivityCollector` MUST determine whether a site's recent post activity is observable, preferring unauthenticated (tokenless) requests and only escalating to Application Password (AP) authentication when the site rejects public access. This resolves Q#3: public posts MUST be readable without AP; AP MUST be used only as a retry after 401/403.

## Requirements

| ID | Requirement |
|----|-------------|
| AC-01 | `ActivityCollector` MUST first attempt an unauthenticated GET `/wp/v2/posts` against the site. |
| AC-02 | A 200 response (including an empty array `[]`) MUST mark activity **available**; no AP retry MUST occur. |
| AC-03 | A 401 or 403 response with an AP configured MUST trigger exactly one retry with the AP. |
| AC-04 | The AP retry MUST succeed (200) and mark activity **available** when the AP is valid. |
| AC-05 | A 404 or HTTP 000 (connection refused) MUST mark activity **unavailable** regardless of AP configuration. |
| AC-06 | A 401/403 with NO AP configured MUST mark activity **unavailable**. |
| AC-07 | A failed AP retry (401/403 after AP use) MUST mark activity **unavailable**. |
| AC-08 | `ActivityCollector` MUST normalize each fetched post into a consistent `ActivityItem` shape (id, title, date, link). |
| AC-09 | The tokenless-first ordering MUST hold even when an AP is configured: the AP MUST NOT be sent on the first request. |
| AC-10 | Activity availability MUST feed `Collector::runOne()` deep mode: when WP_FULL and activity is unavailable, the site MUST be marked `wp-degraded`. |

## Scenarios

### AC-01 / AC-02 / AC-09 — Tokenless-first available
**Given** a WP site with public `/wp/v2/posts` returning 200 and an AP is configured
**When** `ActivityCollector` runs
**Then** the first request MUST be unauthenticated
**And** the response MUST be treated as available
**And** the AP MUST NOT be sent on the first request.

### AC-02 — Empty posts is available
**Given** `/wp/v2/posts` returns 200 with body `[]`
**When** `ActivityCollector` evaluates availability
**Then** activity MUST be marked available
**And** no AP retry MUST occur.

### AC-03 / AC-04 — AP retry succeeds
**Given** `/wp/v2/posts` returns 401 unauthenticated and an AP is configured
**When** `ActivityCollector` runs
**Then** it MUST retry exactly once with the AP
**And** a 200 on the retry MUST mark activity available.

### AC-05 — 404 / 000 unavailable
**Given** `/wp/v2/posts` returns 404 or yields HTTP 000
**When** `ActivityCollector` runs (AP configured or not)
**Then** activity MUST be marked unavailable
**And** no further classification change MUST occur.

### AC-06 — No AP on 401
**Given** `/wp/v2/posts` returns 401 and NO AP is configured
**When** `ActivityCollector` runs
**Then** activity MUST be marked unavailable
**And** no retry MUST occur.

### AC-07 — AP retry fails
**Given** `/wp/v2/posts` returns 401 unauthenticated, an AP is configured, and the AP retry also returns 401
**When** `ActivityCollector` evaluates the result
**Then** activity MUST be marked unavailable.

### AC-08 — Post normalization
**Given** `/wp/v2/posts` returns 200 with one or more posts
**When** `ActivityCollector` parses the response
**Then** each post MUST be normalized to an `ActivityItem` with `id`, `title`, `date`, `link`
**And** missing optional fields MUST be represented as null, not omitted.

### AC-10 — Feeds degradation
**Given** a `WP_FULL` site where activity is unavailable
**When** `Collector::runOne()` runs in deep mode
**Then** the site MUST be reclassified as `wp-degraded`.

## Edge Cases

- A 5xx response MUST be treated as unavailable (defensive default; not retried with AP).
- A redirect (3xx) to a non-WP endpoint returning 404 MUST be classified unavailable.
- A response with 200 but malformed JSON MUST be classified unavailable, not available.
- Rate-limiting (429) MUST NOT trigger an AP retry; activity MUST be marked unavailable for that run.