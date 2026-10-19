---
paths:
  - "**/*.php"
  - "**/*.js"
  - "**/*.css"
---

# Code style

## PHP
- Target **PHP 7.4**. For PHP 7.4 that means no `match`, enums, `readonly`, constructor promotion, `?->`, union types, or `str_contains` without a polyfill.
- WordPress Coding Standards: spaces inside parentheses, Yoda conditions for literals; match the file's array syntax and indentation. Don't reformat lines you didn't change.
- Prefix every global function, option, meta key, transient, hook and AJAX action with `dlm_`. Text domain `dynamic-location-menus` on every translatable string.
- Docblock on every public method and every hook the plugin fires.
<!-- GENERATE: namespace and autoloading, class naming, module/init pattern, field library usage, where global functions live. Facts from the code. -->

## JavaScript
- Data from PHP arrives through `wp_localize_script` or `wp_add_inline_script`; never inline secrets.
<!-- GENERATE: jQuery or vanilla, build step or none, module pattern. -->

## CSS
- Scope front-end CSS under a plugin wrapper or body class so it doesn't leak into themes or wp-admin.
- Don't edit vendored libraries (`*.min.*`, third-party bundles).

## Comments
- Explain *why*, not *what*.
