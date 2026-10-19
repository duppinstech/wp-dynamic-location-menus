---
paths:
  - "tests/**"
  - "includes/**/*.php"
  - "dynamic-location-menus.php"
---

# Testing standard

## Minimum (every change)
1. `php -l` passes on every changed PHP file (the PostToolUse hook does this).
2. Manual smoke test on a WordPress site with `WP_DEBUG` on:
   - The plugin activates without notices or errors.
   - Settings → Location Menus (`dlm-settings`): Settings, Coverage matrix, Clone pages, Consistency, URL preview, How it works.
   - On a URL that matches the location pattern, a master-menu item rewrites to the city page, or follows the missing-page setting (fallback, leave, or hide).
3. If you touched the updater, test the Check Again flow against a draft release.

## Automated tests
- `composer lint` runs `tests/lint.php` (`php -l` on plugin PHP files, skips `vendor/`).
- `composer test` runs `tests/run.php`. It checks that `dynamic-location-menus.php` exists and that the Composer package name and type match. It does not boot WordPress.
- No product suite yet. There is no `tests/unit/` or `tests/integration/`. See `PLAN.md`.
- `.github/workflows/ci.yml` runs `composer lint` and `composer test` on PHP 8.2 for pull requests.

## Priority test targets
- `DLM_GitHub_Updater` — latest-release HTTP, optional token header, and zip install.
- `DLM_Settings::sanitize` — writes to the `dlm_settings` option.
- `DLM_Admin` `admin_post_dlm_clone_gap`, `admin_post_dlm_clone_batch`, and `admin_post_dlm_clone_slot_missing` — nonce, capability, and page creation.
- `DLM_Menu_Filter` and `DLM_Resolver` — front-end menu URL rewrite.
- `DLM_Cloner` — copies stored content and Elementor meta and replaces labels, slugs, and phones.
- `DLM_Scanner` — writes the page map from published pages.
