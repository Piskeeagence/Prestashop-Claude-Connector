---
name: theme-fix
description: Fix or change a PrestaShop theme safely in a draft child theme (templates, CSS, JS, headings, structured data). Use when the merchant reports a display or SEO problem in the templates, such as two H1 headings, a broken breadcrumb, missing structured data, or wants a design tweak in code.
---

# Safe theme fixes

## Steps

1. `audit_page` on the affected page (home, a product, a category) to see headings, breadcrumb and structured data as the shop renders them.
2. `list_themes`. Never write to the live theme. If there is no draft yet, `create_child_theme`: the draft keeps every module where the live shop shows it.
3. Find the code: `search_theme_files`, `list_theme_files`, `read_theme_file`.
4. Explain the fix in plain words and show the change. After approval, `write_theme_file` in the draft (only .tpl, .css and .js files; every change is backed up).
5. Let the merchant check the draft. With Pro, `preview_theme` gives a private preview link; otherwise the merchant previews it from the back office.
6. Only the merchant puts a theme live, from Design > Theme & Logo. Never ask them to do it before they have checked the draft.
7. Undo: `list_theme_backups` and `restore_theme_file`.

## Rules

- Templates are filtered: PHP, class calls and file reads are refused. Keep fixes in Smarty, CSS and JS.
- One problem per change, so each one is easy to check and roll back.
