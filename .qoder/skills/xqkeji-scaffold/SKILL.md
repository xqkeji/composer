---
name: xqkeji-scaffold
description: Drive the xqkeji low-code Composer generator (composer xqkeji:*) to scaffold and edit modules, controllers, forms, tables, elements and models inside a host business project. Use when asked to create or modify xqkeji 低代码 modules/forms/tables/elements, set the current module/controller, append a field to an existing form/table, or add a select_ dropdown element.
---

# xqkeji Low-Code Scaffold

## Overview

Operate the `xqkeji:*` Composer generator commands that create and edit code skeletons (module → controller → form/table → element) for the 新齐 low-code framework. This skill covers *how to drive* the commands; exact arguments live in the repo's `docs/commands.json`.

## Prerequisites (verify before running anything)

- Commands run with `composer` **inside an installed host business project** that has this plugin and the closed-source `php-xqkeji` extension. This generator repo is NOT its own host — running `composer xqkeji:*` here has no effect.
- Confirm the host: `cd` to the target project (e.g. a `www/<app>` dir) and check it lists the commands: `composer list | grep xqkeji`.
- On Windows Git Bash, `php`/`composer` may be absent from PATH — use the shim, e.g. `/d/wnmmp-1.2.8/bin/composer/composer.bat`. Discover the working shim rather than assuming.

## Mental model

```
module → controller → form / table → element
                    ↑ model (pairs with controller)
```

- A persistent **current context** (module / controller / mode) is stored in `runtime/composer/context.php`. Most generators depend on it. **Always run `xqkeji:use <module>` first** to point at the target module.
- `xqkeji:form` auto-switches to form mode; `xqkeji:table` auto-switches to table mode. `xqkeji:element` writes to `form/element/` or `table/element/` based on the current mode.

### Naming & reference conventions (critical)

- Input names adapt case: `user` / `user_login` → class `User` / `UserLogin`; config names use snake_case.
- In a form/table `$el` array: `@ElementName` reuses an element from the **base** module; `~ElementName` references/creates an element in the **current** module (base checked first, then current; created if neither has it).
- **`select_` shortcut**: an element whose snake name starts with `select_` (e.g. `select_dept` / `SelectDept`) auto-creates an empty subclass of `xqkeji\form\element\SelectModel` with no name prompt, referenced in the form as `~SelectDept`.

## Commands (one-liners; full args in docs/commands.json)

- `xqkeji:use <name> [-m|-c|-f|-T]` — switch current module/controller, or set form/table mode.
- `xqkeji:module <name> [-p path] [-t 中文名]` — create/register a module.
- `xqkeji:controller <name> [-e entry] [-a auth|login] [-A actions] [-t 中文名] [-f]` — create controller + init acl/menu/lang.
- `xqkeji:model <name>` — create model class.
- `xqkeji:action <name> [-t 中文名]` — create controller action class.
- `xqkeji:form <name> [-e el...] [-b tab] [-g global] [-a]` — create form; `-a` interactively appends an element to an existing form.
- `xqkeji:table <name> [-e col...] [-T tree] [-D drag] [-N no-controller] [-a]` — create table; `-a` appends a column to an existing table.
- `xqkeji:element <name> [-c|-e|-r] [-y type] [-l list] [-D default] [-m model]` — create/edit/remove a single element.
- `xqkeji:remove <name> [-p path] [-f]` — remove a module.
- `xqkeji:path <package> <path> [--copy|--no-update|--no-alias]` — register a local path repo (symlink).
- `xqkeji:doc [-o dir]` — re-export `docs/commands.json` + `commands.md` from live command definitions.

## Canonical workflow

```bash
composer xqkeji:use -- edu                 # 1) point at target module
composer xqkeji:form Article -e title,content   # 2) create form (auto form-mode) + elements
composer xqkeji:element cover -c -y=Image       # 3) add one element standalone
composer xqkeji:table Article -e id,title,status,edit_delete  # 4) table (also builds controller+menu)
```

## Appending to an existing form/table with `-a`

Use when the user wants to add a field to an already-generated form/table without touching controller/acl/menu/lang.

```bash
composer xqkeji:form Article -a            # lists current elements, prompts for position
composer xqkeji:table Article -a           # same for a table column
```

`-a` behavior:
- Shows the existing `$el` list (numbered; TabForms list each tab's inner elements).
- Enter a number to insert **after** that element; `0` or empty inserts **before the first**; picking a Tab descends into it.
- The new element goes through the `xqkeji:element` creation flow (interactive type/project prompts); a `select_` name short-circuits to a SelectModel subclass; an existing same-name element is reused via `@`/`~` rather than recreated.
- Edits are written by text offset — only one reference line is inserted, all other content and hand-edits are preserved.

## After changing any command

Regenerate the authoritative docs so they never drift:

```bash
composer xqkeji:doc
```

Do not hand-edit `docs/commands.json` or `docs/commands.md`.

## Boundaries

- Never run destructive `xqkeji:remove`/`-f` without explicit user confirmation.
- Do not commit or push generated changes without the user's approval.
- When unsure of exact flags, read `docs/commands.json` in the generator repo instead of guessing.
