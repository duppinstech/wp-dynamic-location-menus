---
paths:
  - "dynamic-location-menus.php"
  - "uninstall.php"
  - "includes/**/*.php"
  - "assets/**/*.js"
  - "assets/**/*.css"
  - "tests/**/*.php"
---

# Code style

## PHP
- Target **PHP 7.4**. For PHP 7.4 that means no `match`, enums, `readonly`, constructor promotion, `?->`, union types, or `str_contains` without a polyfill.
- WordPress Coding Standards: spaces inside parentheses, Yoda conditions for literals; match the file's array syntax and indentation. Don't reformat lines you didn't change.
- Prefix every global function, option, meta key, transient, hook and AJAX action with `dlm_`. Text domain `dynamic-location-menus` on every translatable string.
- Docblock on every public method and every hook the plugin fires.
- No PHP namespace and no Composer autoload of plugin classes. `dynamic-location-menus.php` loads each class with `require_once`. Classes are `DLM_*`. `dlm_init()` on `plugins_loaded` calls `DLM_Menu_Filter::register()`, `DLM_Admin::register()` in wp-admin, and `DLM_Scanner::register_cron()`. The updater and plugin-info classes use `boot()`. No field library. The plugin's global function is `dlm_init()` in the main file.

## JavaScript
- Data from PHP arrives through `wp_localize_script` or `wp_add_inline_script`; never inline secrets.
- `assets/admin.js` is a jQuery IIFE, enqueued as `dlm-admin` with a `jquery` dependency from `DLM_Admin`. There is no build step.

## CSS
- Scope front-end CSS under a plugin wrapper or body class so it doesn't leak into themes or wp-admin.
- Don't edit vendored libraries (`*.min.*`, third-party bundles).

## Comments
- Explain *why*, not *what*.
