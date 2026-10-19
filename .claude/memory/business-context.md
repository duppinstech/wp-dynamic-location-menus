# Business context

_Committed. If the repo is public, keep only public-safe facts here: published tiers and list prices, positioning, cost drivers. Private numbers go in the "Business (private)" section of the gitignored `CLAUDE.local.md`._

Read by `finance-reviewer`, `marketing-reviewer`, `legal-reviewer` and `product-reviewer`. Items marked _TBD_ are open questions for Odell. Agents must not fill them in by guessing.

## Product and positioning
- **Product:** Dynamic Location Menus, by Duppins Technology (https://duppinstech.com).
- **Audience:** Multi-location businesses that keep one WordPress navigation menu (`readme.txt`). An operator sets locations, URL patterns, and service slots in wp-admin (`INTENT.md`).
- **Positioning:** Rewrites nav menu links from the visitor's current location context. One master menu of generic service URLs swaps to the matching city page (`INTENT.md`, `README.md`).
- **Built for (client):** product work. Not work-for-hire for a single client (`OWNERSHIP.md`).

## Tiers
| Tier | What's included | Price | Distribution |
|---|---|---|---|
| Core | The plugin in this repository. No Pro add-on is in the repo. | _TBD (Odell)_ | Versioned zip from GitHub Releases |

## Cost drivers
- GitHub latest-release checks are unauthenticated unless a token is configured. The updater says never ship a token in the plugin. No other metered API is called.
- Hosting: _TBD (Odell)_
- Support time: _TBD (Odell)_
