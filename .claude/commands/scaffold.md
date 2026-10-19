---
description: Scaffold new code for this plugin
argument-hint: "module <dir>/<Class_Name> | <project-specific type> <name>"
---

Request: $ARGUMENTS

## `module <dir>/<Class_Name>`
- Create the class in the plugin's code folder, following the namespace and module pattern in `.claude/rules/code-style.md`, with an `init()` (or equivalent) that only registers hooks.
- Register it wherever the plugin wires its modules (see Architecture in `AGENTS.md`), at the right point in the load order, and explain the placement.
- Run `composer dump-autoload` if the plugin autoloads and this is a new directory.

## Settings tab
- In `includes/class-dlm-admin.php`, add a `nav-tab` and a `.dlm-tab` panel that share one `data-tab` value. The screen is Settings → Location Menus, slug `dlm-settings`.
- `assets/admin.js` switches tabs from `data-tab`. There is no per-tab file and no build step.
- A state-changing control uses an `admin_post_dlm_*` action registered in `DLM_Admin::register()`.

After scaffolding, run `php -l` on the new files and list what's left for a human to fill in.
