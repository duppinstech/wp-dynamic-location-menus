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

= Support =

Support: [support@duppinstech.com](mailto:support@duppinstech.com). Made by [Duppins Technology](https://duppinstech.com).

== Installation ==

1. Download `wp-dynamic-location-menus.zip` from the latest [GitHub release](https://github.com/duppinstech/wp-dynamic-location-menus/releases).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip, and click **Install Now**.
3. Activate Dynamic Location Menus through the **Plugins** screen.
4. Go to **Settings → Location Menus** and add your locations and service slots.
5. Open the **Coverage matrix** tab and click **Rescan pages now** after you publish city pages.

== Frequently Asked Questions ==

= Do I need a separate menu for each city? =

No. Build one master menu with generic URLs. The plugin rewrites links automatically when the visitor is on a city-specific page.

= What happens when a city page does not exist? =

Choose one of three behaviors in settings: use the configured fallback URL (default), leave the menu item's original URL unchanged, or hide the item.

= Does this work with page caching? =

Full-page caches that key on request URL work transparently. Fragment caches that cache menu HTML separately should vary on the request path.

== Screenshots ==

1. Settings for locations, service slots, and what happens when a city page does not exist.
2. Coverage matrix showing which cities have a page for each service. Create fills one gap.
3. Clone pages copies a template to other cities and replaces the city name, slug, and phone.
4. URL preview for a city page, with the detected location and the rewritten slot links.

== Changelog ==

= 2.0.0 =
* Each location can store a phone number, used when cloning pages and when checking that city pages match.
* The coverage matrix can create the missing page for one city, or clone one service to every city that does not have it yet.
* Clone pages copies a template page to selected cities and replaces the city name, slug, and phone in the stored content, including Elementor data.
* Consistency checks the stored content of each city's pages for the phone number and location name from Settings.
* Unchecking Skip existing on a clone batch clones to cities that already have a page for that service.
* Location titles use the display label you configured, including when the old title had the city and state without a comma.
* A city page nested under a parent service is indexed as that specific service. When both a service-area page and a flat landing page match, the service-area page is used.
* A menu link is rewritten when it has the same depth and final slug as a configured slot, even if a folder in the middle of the path is different.
* Menus rewrite on city pages even when the menu renders before the main content, including city hub URLs such as `/service-areas/kansas/kansas-city/`.

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
