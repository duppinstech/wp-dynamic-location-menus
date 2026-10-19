# AGENTS.md

## Plugin facts

- Package: `duppins-technology/dynamic-location-menus`
- Main file: `dynamic-location-menus.php`
- Prefix: `dlm_` for functions, options, hooks, and admin-post actions. Classes are `DLM_*`. Constants are `DLM_*`.
- Text domain: `dynamic-location-menus`
- PHP: >= 7.4. WordPress: >= 6.0 (`Requires at least` in the plugin header and `readme.txt`).
- License: GPL-2.0-or-later (plugin header and `readme.txt`). `OWNERSHIP.md` says that license applies from version 1.0.3.
- Repo: public, https://github.com/duppinstech/wp-dynamic-location-menus
- Current release: `v2.0.0`, tagged 2026-10-18. `CHANGELOG.md` dates 2.0.0 as 2026-05-29. `DLM_VERSION` is `2.0.0`.
- Updates: GitHub Releases. `includes/class-dlm-github-updater.php` (`DLM_GitHub_Updater`) checks `duppinstech/wp-dynamic-location-menus`. Disable with `DLM_DISABLE_GITHUB_UPDATER`.
- Plugins screen details: `includes/class-dlm-plugin-info.php` reads `readme.txt` and `assets/wporg/`.
- Pro add-on: none in this repository.
- Integrations: GitHub Releases API. Clone and audit read Elementor post meta stored on the page. They do not call an Elementor service.
- Security-sensitive: `includes/class-dlm-github-updater.php`, `includes/class-dlm-admin.php` (`admin_post_dlm_*` clone handlers), `includes/class-dlm-settings.php` (option sanitize).

## What this is

WordPress plugin `duppins-technology/dynamic-location-menus`. It rewrites nav menu links from location context.

An operator keeps one master menu of generic service URLs. On a page that matches the configured location pattern, matching links swap to that city's page. Locations, URL patterns, and service slots are set in wp-admin.

Sites install a versioned zip from GitHub Releases. The plugin checks that release channel for updates.

## Architecture

- `dynamic-location-menus.php` — constants, `require_once` of includes, boots the updater and plugin-info, and hooks `dlm_init` on `plugins_loaded`. Activation seeds defaults and schedules `dlm_daily_scan`.
- `uninstall.php` — deletes `dlm_settings`, `dlm_page_map`, `dlm_last_scan`, `dlm_github_release`, and the daily scan event.
- `includes/class-dlm-settings.php` — single option `dlm_settings` (pattern, locations, slots, missing-page behavior).
- `includes/class-dlm-slug.php` — slug-in-URL matching for locations and city pages.
- `includes/class-dlm-pattern.php` — compiles `{region}`, `{slot}`, and `{city}` patterns into a regex and a URL builder.
- `includes/class-dlm-registry.php` — reads settings and the cached page map.
- `includes/class-dlm-context.php` — detects the current location from the request URL.
- `includes/class-dlm-resolver.php` — chooses each menu item's URL for the current context, including the missing-page policy.
- `includes/class-dlm-menu-filter.php` — rewrites `wp_nav_menu_objects` and drops items the resolver hides.
- `includes/class-dlm-scanner.php` — builds the page map from published pages on a daily cron and on save.
- `includes/class-dlm-content.php` — extracts searchable text from stored page source for the consistency audit.
- `includes/class-dlm-audit.php` — per-location checks of phones and location names in stored page source.
- `includes/class-dlm-cloner.php` — duplicates a template city page and replaces labels, slugs, and phones in stored content and Elementor meta.
- `includes/class-dlm-admin.php` — Settings → Location Menus (`dlm-settings`): Settings, Coverage matrix, Clone pages, Consistency, URL preview, How it works.
- `includes/class-dlm-github-updater.php` — GitHub Releases updater.
- `includes/class-dlm-plugin-info.php` — plugins-screen details modal from `readme.txt` and `assets/wporg/`.
- `assets/admin.js` — jQuery tab switching and repeater rows. No build step.
- `assets/admin.css` — styles for that admin screen.
- `assets/wporg/` — banner, icon, and screenshot images for the details modal.
- `tests/lint.php` — `php -l` on plugin PHP files, skips `vendor/`.
- `tests/run.php` — smoke check that the bootstrap file and Composer package identity exist. Does not boot WordPress.

## Commands

Install:

```bash
composer install --no-dev --optimize-autoloader
```

Lint (`php -l` on plugin PHP files, skips `vendor/`):

```bash
composer lint
```

Test (smoke check only; does not boot WordPress):

```bash
composer test
```

Build the release zip. This is what `.github/workflows/release.yml` runs when a `v*` tag is pushed. The asset is `wp-dynamic-location-menus.zip`. The folder inside is `dynamic-location-menus`.

```bash
bash bin/build-zip.sh
```

Do not tag, push, or publish a release unless the task says so.

## Conventions

- Indent PHP, JS, and CSS with 4 spaces. Line endings are LF. `*.ps1`, `*.bat`, and `*.cmd` use CRLF.
- Branch from `main` as `type/short-description` (`feat/`, `fix/`, `chore/`, `docs/`, `ci/`).
- Commits use Conventional Commits: `type: summary`.
- Do not commit to `main`.
- Keep plugin behavior changes out of docs and CI commits.

## Hard rules

- `vendor/` is Composer output. It is gitignored. Release zips include it. Do not commit it.
- Do not change `includes/`, `assets/`, `dynamic-location-menus.php`, or `uninstall.php` unless the task is a product change.
- Do not edit `LICENSE.md` terms or rewrite `SECURITY.md` or `CHANGELOG.md` history. Add a changelog entry only when a product change needs one.
- Do not commit `.env`, tokens, `CLAUDE.local.md`, or `.claude/settings.local.json`.
- Do not force-push, run `git reset --hard`, or run `git clean`.

## Agents

| Agent | Use when |
| --- | --- |
| `security-reviewer` | AJAX, REST, admin-post, the updater, or code that reads request input. Also `/department-review`. |
| `legal-reviewer` | A new dependency, bundled asset, third-party API, data collection, or a change to license or readme text. |
| `finance-reviewer` | A paid API call, a new tier, or anything a customer might pay for. |
| `product-reviewer` | Checking a change against `INTENT.md` and `PLAN.md`, before starting or merging a feature. |
| `marketing-reviewer` | Customer-facing copy, naming, screenshots, or release-note highlights. |
| `support-docs-reviewer` | `CHANGELOG.md`, `README.md`, `readme.txt`, admin messages, or i18n, before a PR or release. |
| `release-ops-reviewer` | Compatibility, migrations, zip contents, uninstall, or rollback. Also `/deploy` preflight. |
| `ux-reviewer` | Templates, front-end assets, or admin UI. |
| `test-writer` | After a feature or fix, or to bootstrap a test harness. |
| `research` | WordPress, library, or third-party API behavior is uncertain. Read-only. |
