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
<!-- GENERATE: the admin screens and front-end output to check for this plugin, as bullets. -->
3. If you touched the updater, test the Check Again flow against a draft release.

## Automated tests
<!-- GENERATE: if tests/ exists, describe the harness and how to run it. Otherwise: "No suite yet; see PLAN.md." -->
- Location: `tests/unit/` (Brain Monkey) and `tests/integration/` (wp-phpunit). Naming: `Test_<Class_Name>.php`, methods `test_<behavior>_<condition>`.
- Every bug fix ships with a regression test that fails before the fix. Unit tests never hit the network.
- CI runs on PHP 7.4 and the latest 8.x.

## Priority test targets
<!-- GENERATE: 3-6 classes or functions with the most risk (security, money, data, updater). -->
