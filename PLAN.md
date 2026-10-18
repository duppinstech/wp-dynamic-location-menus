# Plan

## Current

- Ship plugin updates by tagging `v*` on `main`. `.github/workflows/release.yml` builds `wp-dynamic-location-menus.zip` and publishes the GitHub Release.
- Keep product behavior as it is unless a task asks for a product change.

## Baseline

- Docs, pull request CI, and agent guidance are repo hygiene. They do not change how menus are rewritten.

## Open

- `composer test` is a smoke check. It does not boot WordPress, and there is no product test suite yet.
