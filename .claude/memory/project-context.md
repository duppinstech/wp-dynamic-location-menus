# Project context

_Shared, committed memory. Keep it factual and current. If the repo is public, so is this file._

- **Product:** Dynamic Location Menus, a WordPress plugin. Owner: Odell Duppins / Duppins Technology.
- **Repo:** https://github.com/duppinstech/wp-dynamic-location-menus (public)
- **Current release:** v2.0.0, tagged 2026-10-18. `CHANGELOG.md` dates 2.0.0 as 2026-05-29.
- **Distribution:** versioned zip from GitHub Releases. `DLM_GitHub_Updater` checks that channel. Disable with `DLM_DISABLE_GITHUB_UPDATER`.
- **Key dependencies:** PHP >= 7.4 (`composer.json`). No Composer packages and no `package.json`. WordPress >= 6.0.
- **Integrations:** GitHub Releases API (`api.github.com`). Clone and audit read stored Elementor post meta; they do not call an Elementor service.
- **CI:** `.github/workflows/ci.yml` runs `composer lint` and `composer test` on PHP 8.2. Tests: yes, smoke check only (`tests/run.php` does not boot WordPress). Releases are built by `release.yml` on `v*` tags.

## Review watchlist
Known items each department re-checks on every review until fixed. Remove lines when they're resolved.
- **Security:** none yet
- **Legal:** `INTENT.md` and `composer.json` say proprietary. The plugin header, `readme.txt`, and `OWNERSHIP.md` say GPL-2.0-or-later (`OWNERSHIP.md`: from version 1.0.3).
- **Finance:** none yet
- **Product:** none yet
- **Marketing:** none yet
- **Support:** none yet
- **Ops:** none yet
- **UX:** none yet
