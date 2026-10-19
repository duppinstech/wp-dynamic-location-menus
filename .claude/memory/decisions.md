# Architectural decisions

Append-only log. Format: `## YYYY-MM-DD — Title`, then **Context** / **Decision** / **Consequences**. Owner waivers of High findings are recorded here too.

## 2026-05-30 — Store configuration and the page map in options

**Context**
The initial commit (`a2c6871`) keeps menu configuration and the scanned page index in WordPress options.

**Decision**
`dlm_settings` holds the pattern, locations, and slots. The scanner stores `dlm_page_map` and `dlm_last_scan`. `uninstall.php` deletes those options.

**Consequences**
There is no custom table. Deleting the plugin removes that data.

## 2026-05-30 — License the plugin GPL-2.0-or-later

**Context**
The plugin header in the initial commit is GPL-2.0-or-later. `CHANGELOG.md` records that header under 2.0.0, dated 2026-05-29. `OWNERSHIP.md` says the GPL applies from version 1.0.3.

**Decision**
Ship the plugin under GPL-2.0-or-later.

**Consequences**
`INTENT.md` and `composer.json` still say proprietary. That mismatch is on the legal watchlist.

## 2026-10-17 — Smoke-check in CI without booting WordPress

**Context**
`f25889c` added pull request lint and test checks.

**Decision**
`composer lint` runs `tests/lint.php`. `composer test` runs `tests/run.php`, which checks the bootstrap file and Composer package identity and does not load WordPress.

**Consequences**
`.github/workflows/ci.yml` (PHP 8.2) does not exercise menu rewrites. `PLAN.md` says there is no product test suite yet.

## 2026-10-18 — Update sites from GitHub Releases

**Context**
`293635c` installed the GitHub updater standard.

**Decision**
`DLM_GitHub_Updater` reads `https://api.github.com/repos/duppinstech/wp-dynamic-location-menus/releases/latest`. A `v*` tag runs `.github/workflows/release.yml`, which publishes `wp-dynamic-location-menus.zip`.

**Consequences**
A tag is a production update for sites that leave the updater on. `DLM_DISABLE_GITHUB_UPDATER` turns it off.

