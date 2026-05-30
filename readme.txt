=== Dynamic Location Menus ===
Contributors: duppins-technology
Tags: navigation, menus, location, multi-location, seo
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Rewrites nav menu links from location context. One master menu with generic URLs; on city pages, links swap to the matching local page.

== Description ==

Dynamic Location Menus by Duppins Technology lets multi-location businesses maintain a single WordPress navigation menu while still serving city-specific URLs to visitors on location pages.

= How it works =

1. Configure locations (city + optional region) in **Settings → Location Menus**.
2. Configure service slots — each row maps a generic URL on your site to a slot name and an optional fallback URL.
3. Build one nav menu in **Appearance → Menus** using your generic service URLs.
4. When a visitor lands on a page that matches your configured URL pattern (default: `/service-areas/{region}/{slot}/{city}/`), the plugin detects the city and rewrites every matching menu link to that city's specific page.

= Key features =

* Admin-configurable URL patterns with optional secondary "merged" patterns
* Locations and service slot repeaters
* Coverage matrix showing which cities have custom pages — with **Create** and bulk-clone actions
* **Clone pages** tab — duplicate Elementor city pages with city/phone replacement
* **Consistency** tab — audit phones and location names in stored page source
* Phone number per location for cloning and audits
* URL preview tool for debugging rewrites
* Daily automatic page scan plus rescan on relevant page saves
* Configurable missing-page behavior: fallback, leave unchanged, or hide

= URL patterns =

Default: `/service-areas/{region}/{slot}/{city}/`

Tokens supported: `{region}` (optional), `{slot}`, `{city}`.

If region is left blank on a location, the plugin collapses the empty segment so you get `/service-areas/{slot}/{city}/`.

= Service slot matching =

Slot matching is exact-depth. A configured slot of `services/roadside-assistance` matches only URLs at that exact depth — a deeper URL like `services/roadside-assistance/battery-boost` is treated as a sub-service and not rewritten. Add an explicit slot row to rewrite sub-service URLs.

== Installation ==

1. Upload the `dynamic-location-menus` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Settings → Location Menus** to configure locations, slots, and URL patterns.
4. Click **Rescan pages now** on the Coverage tab after configuration.

== Frequently Asked Questions ==

= Do I need a separate menu for each city? =

No. Build one master menu with generic URLs. The plugin rewrites links automatically when the visitor is on a city-specific page.

= What happens when a city page does not exist? =

Choose one of three behaviors in settings: use the configured fallback URL (default), leave the menu item's original URL unchanged, or hide the item.

= Does this work with page caching? =

Full-page caches that key on request URL work transparently. Fragment caches that cache menu HTML separately should vary on the request path.

== Changelog ==

= 2.0.0 =
* Phone field on locations; page cloning with Elementor meta support; consistency audit tab; coverage matrix Create/bulk-clone actions.

= 1.1.3 =
* City page registry scans published pages only; blog posts are no longer used as menu rewrite targets.

= 1.1.2 =
* Fix all /services/ menu links being hijacked when Highway slot name was set to "services".
* Reject suffix-only slot matches (e.g. "services" inside "towing-services").
* Add optional City page slug(s) per slot for menu/city URL mismatches (e.g. tow-services vs towing-services).

= 1.1.1 =
* Fix slot cross-matching where shorter tokens (e.g. "towing") incorrectly matched longer services (e.g. "towing-services").
* City pages are indexed only when the page URL matches the slot row's url_path leaf segment.

= 1.1.0 =
* Slug-based location detection works across service-areas paths, services paths, and flat landing-page URLs.
* City aliases (e.g. blue-springs-mo → blue-springs) in the locations table.
* URL patterns moved to optional advanced settings.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Adds page cloning, consistency auditing, and coverage matrix gap actions. Add phone numbers to each location row after upgrading.

= 1.0.0 =
Initial release of Dynamic Location Menus.
