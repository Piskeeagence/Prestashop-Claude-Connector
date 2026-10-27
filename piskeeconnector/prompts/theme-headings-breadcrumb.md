---
name: theme-headings-breadcrumb
description: Clean headings and add a breadcrumb in a draft child theme
---

# Clean headings and add a breadcrumb

Work only in a draft child theme. The live theme is never modified: the merchant puts the draft live from the Piskee module page. Answer in the merchant's language.

## 1. Audit the live pages

Call `audit_page` on the home page, one category page and one product page (find their URLs with `search_products` and `list_categories`, or ask the merchant). Summarise per page: the h1, the heading outline, the problems found, and whether a visible breadcrumb and BreadcrumbList structured data are present.

Target structure:

- One h1 per page: the shop name or main promise on the home page, the category name on a category page, the product name on a product page.
- Logo, menu, footer titles, newsletter and reassurance blocks must not be headings: they become `p`, `span` or `div` with the same CSS class, so the design does not change.
- h2 for the main sections of the content, h3 below, with no skipped level.
- A visible breadcrumb on category and product pages, and BreadcrumbList structured data.

## 2. Prepare the draft

Call `list_themes`. If no editable draft exists, create one with `create_child_theme`, using the live theme as source.

## 3. Fix

- Find where each wrong heading is written with `search_theme_files` (for example `<h1`, `h2 class=`, `footer`).
- Read the file with `read_theme_file`, change only the tags concerned, keep every class and every Smarty block, and write the complete file with `write_theme_file`.
- Breadcrumb: in classic-based themes it is `templates/_partials/breadcrumb.tpl`, included from the layouts. Check it is included on category and product pages, that it is a `nav` with an ordered list, and that structured data is present (BreadcrumbList microdata on the list items, or JSON-LD). Fix in the draft.
- Put any CSS adjustment in `assets/css/custom.css`.

Explain each change in one line: file, before, after.

## 4. Hand over

Tell the merchant the draft is ready, and how to put it live: Modules > Piskee > Child theme tab > Put live. Suggest checking the home, a category and a product page right after, and running this audit again on the live pages. Remind them that "Back to the previous theme" is available if anything looks wrong.
