# Changelog

All notable changes to Dynamic Location Menus are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-05-29

### Added

- **Phone field** on each location row (used for cloning and consistency checks).
- **Coverage matrix actions** — Create button on gap cells; column **+N** bulk-clones to all missing cities for a slot.
- **Clone pages tab** — duplicate a template city page to selected locations with city/phone/slug find-replace in stored content and Elementor JSON.
- **Consistency tab** — per-location audit of phones and location names in stored page source (not Nimba-rendered HTML).
- New classes: `DLM_Content`, `DLM_Audit`, `DLM_Cloner`.

### Fixed

- **Clone batch “Skip existing” checkbox** — unchecking the box now allows cloning to locations that already have an indexed slot page (unchecked boxes were omitted from POST and were incorrectly treated as “skip”).
- **Location title/heading formatting** — strips city+state with or without a comma (e.g. `Blue Springs MO`) and always outputs the configured label format (`Blue Springs, MO`); fixes doubled titles like `Heavy Duty Towing Blue Springs MO in Blue Springs, MO`.
- **Page map slot detection** — nested city URLs (e.g. `…/joplin/roadside-assistance/vehicle-lockouts/`) now index under the leaf service slot, not the parent; prefers `/service-areas/` pages over flat AdWords landing slugs when multiple pages match the same slot.
- **Menu slot matching** — menu URLs that share depth and leaf slug with a configured slot (e.g. `/services/heavy-duty-recovery/rotator-service/`) now rewrite even when the middle path segment differs from the slot row's `url_path`.
- **Menu rewrite regression** — context detection no longer depends solely on `is_singular()` (menus in headers often render earlier); removed `sanitize_text_field()` from request paths; resolver rewrites menu items that point at the wrong city instead of treating any indexed city URL as final.
- **City hub slug matching** — paths like `/service-areas/kansas/kansas-city/…` now map to the configured location `kansas-city-ks` (and `/service-areas/missouri/kansas-city/…` to `kansas-city-mo`), so menus rewrite on city-hub URLs.
- **Plugin Check compliance** — `wp_strip_all_tags()`, sanitized request path, unslashed POST/GET handling, readme tested-up-to 7.0, shortened short description, GPL-2.0-or-later license header, text domain `dynamic-location-menus`.

## [1.1.3] - 2026-05-29

### Fixed

- City page map includes published **pages** only. Blog posts are excluded so they cannot replace service pages in navigation rewrites.

## [1.1.2] - 2026-05-29

### Fixed

- Highway slot name `services` no longer treats every `/services/...` menu item as a highway link.
- Suffix-only slot token matches rejected (`services` vs `towing-services`).

### Added

- Optional **City page slug(s)** field on service slot rows.

## [1.1.1] - 2026-05-29

### Fixed

- Shorter slot tokens no longer match inside longer service slugs (e.g. `towing` vs `towing-services`).
- City page indexing requires the slot row's url_path leaf segment to appear in the page URL, preventing cross-service rewrites.

## [1.1.0] - 2026-05-29

### Changed

- Location context is detected from city slugs anywhere in the URL (service-areas paths, services paths, and flat landing-page slugs).
- City pages are indexed when a URL contains both a city slug and a slot name.
- URL patterns moved to optional advanced settings; no longer required for menu rewriting.

### Added

- City **aliases** field on locations (e.g. `blue-springs-mo` maps to canonical `blue-springs`).

## [1.0.0] - 2026-05-29

### Added

- Initial release
- Location-aware nav menu URL rewriting from a single master menu
- Admin settings page with locations and service slot repeaters
- Configurable URL patterns with optional secondary merged pattern
- Coverage matrix and manual rescan
- URL preview tool
- Daily cron scan and rescan on relevant page saves
- Missing-page behavior: fallback, leave unchanged, or hide
- GitHub-based plugin update checker

[2.0.0]: https://github.com/oduppinsjr/wp-dynamic-location-menus/releases/tag/v2.0.0
[1.1.3]: https://github.com/oduppinsjr/wp-dynamic-location-menus/releases/tag/v1.1.3
[1.1.2]: https://github.com/oduppinsjr/wp-dynamic-location-menus/releases/tag/v1.1.2
[1.1.1]: https://github.com/oduppinsjr/wp-dynamic-location-menus/releases/tag/v1.1.1
[1.1.0]: https://github.com/oduppinsjr/wp-dynamic-location-menus/releases/tag/v1.1.0
[1.0.0]: https://github.com/oduppinsjr/wp-dynamic-location-menus/releases/tag/v1.0.0
