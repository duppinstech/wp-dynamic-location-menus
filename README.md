# Dynamic Location Menus

**By Duppins Technology**

A WordPress plugin that rewrites nav menu links on the fly based on the visitor's current location context. Build ONE master menu using generic service URLs; on city-specific pages, each link automatically swaps to that city's page. Everything — locations, URL patterns, and service slots — is admin-configurable.

## How it works

1. You configure **locations** (city + optional region) in the settings page.
2. You configure **service slots** — each row maps a generic URL on your site to a slot name and an optional fallback URL.
3. You build ONE nav menu in Appearance → Menus using your generic service URLs.
4. When a visitor lands on a page that matches your configured URL pattern (default: `/service-areas/{region}/{slot}/{city}/`), the plugin detects the city and rewrites every matching menu link to that city's specific page. Items with no city version use the fallback (or are hidden — your choice).

## Settings page

**Settings → Location Menus** with four tabs:
- **Settings**: URL pattern, missing-page behavior, locations repeater, slots repeater
- **Coverage matrix**: which cities have which custom pages (with manual rescan)
- **URL preview**: paste any URL, see what the plugin detects and how it would rewrite
- **How it works**: usage notes

## URL patterns

Default: `/service-areas/{region}/{slot}/{city}/`

Tokens supported: `{region}` (optional), `{slot}`, `{city}`. Tokens may be merged inside a segment via the advanced "secondary pattern" feature, e.g., `/locations/{region}/categories/featured-{city}/`.

If region is left blank on a location, the plugin collapses the empty segment so you get `/service-areas/{slot}/{city}/`.

## Service slots — how matching works

Each slot row has a **URL path** (the path the menu item uses, no slashes), a **slot name** (the segment that appears in the city URL pattern), and an optional **fallback URL**.

Slot matching is **exact-depth**. A configured slot of `services/roadside-assistance` matches only URLs at that exact depth — a deeper URL like `services/roadside-assistance/battery-boost` is treated as a sub-service and not rewritten. To swap a sub-service URL, add an explicit slot row for it.

## Missing-page behavior

When a visitor is on a city page but a menu item doesn't have a matching city version, the plugin can:
- **Fallback** (default): use the configured fallback URL
- **Leave**: keep the menu item's original URL unchanged
- **Hide**: remove the item from the menu

## File structure

```
dynamic-location-menus/
├── dynamic-location-menus.php
├── readme.txt                       WordPress.org-style readme (also used by the updater)
├── README.md
├── LICENSE.md
├── SECURITY.md
├── CHANGELOG.md
├── composer.json
├── assets/
│   ├── admin.css
│   └── admin.js
└── includes/
    ├── class-dlm-settings.php       Configuration storage and sanitization
    ├── class-dlm-pattern.php        URL template parser (regex + builder)
    ├── class-dlm-registry.php       Page-map cache + lookup helpers
    ├── class-dlm-context.php        Detect current location from URL
    ├── class-dlm-resolver.php       Decide each menu item's final URL
    ├── class-dlm-menu-filter.php    Hook into wp_nav_menu_objects
    ├── class-dlm-scanner.php        Walk pages, build registry
    ├── class-dlm-admin.php          Settings page
    └── class-dlm-updater.php        GitHub release update checker
```

## Delivery and updates

This plugin checks GitHub for new releases and shows updates in **Dashboard → Updates** like a normal WordPress plugin.

### Install on a site

1. Download the latest `dynamic-location-menus-x.y.z.zip` from [GitHub Releases](https://github.com/oduppinsjr/wp-dynamic-location-menus/releases).
2. Upload and activate it in WordPress.
3. The installed folder must be named `dynamic-location-menus`.

### Publish a new version

1. Bump `Version` and `DLM_VERSION` in `dynamic-location-menus.php`.
2. Update `Stable tag` in `readme.txt` and add an entry to `CHANGELOG.md`.
3. Commit and push to `main`.
4. Create and push a version tag:

```bash
git tag v1.0.1
git push origin v1.0.1
```

GitHub Actions builds a release zip (including Composer dependencies) and attaches it to the release. Sites with the plugin installed will detect the update automatically.

### Private repository access

If the GitHub repository is private, add this to `wp-config.php` on each site that should receive updates:

```php
define('DLM_GITHUB_TOKEN', 'ghp_your_read_only_token_here');
```

Use a fine-grained or classic token with read access to the repository only. Do not commit tokens to this repo.

### Local development

```bash
composer install --no-dev --optimize-autoloader
```

The update checker library lives in `vendor/` and is included in release zips automatically.

## Filters

| Filter | Purpose |
|---|---|
| `dlm_settings` (via WP option) | All config |

You can also override programmatically by storing the `dlm_settings` option from your own plugin/theme bootstrap.

## Caching notes

`wp_nav_menu` output is cached by some plugins. Full-page caches that key on request URL work transparently. Fragment caches that cache menu HTML separately should vary on the request path.

## Versioning

See [CHANGELOG.md](CHANGELOG.md) for release history. This is v1.0.0.

## License

Proprietary — see [LICENSE.md](LICENSE.md). Security reports: [SECURITY.md](SECURITY.md).
