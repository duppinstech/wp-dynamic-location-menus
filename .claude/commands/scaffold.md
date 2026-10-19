---
description: Scaffold new code for this plugin
argument-hint: "module <dir>/<Class_Name> | <project-specific type> <name>"
---

Request: $ARGUMENTS

## `module <dir>/<Class_Name>`
- Create the class in the plugin's code folder, following the namespace and module pattern in `.claude/rules/code-style.md`, with an `init()` (or equivalent) that only registers hooks.
- Register it wherever the plugin wires its modules (see Architecture in `AGENTS.md`), at the right point in the load order, and explain the placement.
- Run `composer dump-autoload` if the plugin autoloads and this is a new directory.

<!-- GENERATE: sections for this plugin's own repeatable building blocks (templates, field groups, blocks, shortcodes, settings tabs, integrations), each listing the files to create and where to register them. Delete this comment if there are none. -->

After scaffolding, run `php -l` on the new files and list what's left for a human to fill in.
