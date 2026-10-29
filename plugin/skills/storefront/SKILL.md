---
name: storefront
description: Organise the PrestaShop storefront without touching code - categories, main menu, mega menu, home page slider, banner, home page product blocks, module positions. Use when the merchant wants to change navigation, add a category, rework the menu or the home page.
---

# Storefront: categories, menu and home page

## Categories and menu

1. `list_categories` and `get_menu` to see the current tree and menu.
2. New categories: `create_category` creates them inactive. Fill their texts (see the product-seo skill), then activate with `update_category` once the merchant approves. `update_category` also moves or reorders a category (`id_parent`, `position`).
3. Menu: propose the new menu as a simple tree, then apply it with `set_menu` (categories, CMS pages, brands, custom links). If the shop uses a third-party menu module, `inspect_menu_module` shows it: it is read, not changed.
4. Mega menu (Pro: `get_mega_menu`, `set_mega_menu`, `publish_mega_menu`): build it as a draft, give the merchant the preview link, publish only after approval.

## Home page

- `get_appearance` first: it shows the slider, banner, custom text, product blocks, social links and the order of the home page blocks.
- Then `save_slide` and `configure_slider`, `set_banner`, `set_custom_text`, `configure_home_products`, `set_social_links`, `set_home_blocks`.
- Images must come from a URL the merchant gives or approves. Write alt texts.

## Module positions

`list_module_hooks` shows which module appears in which zone. `set_module_hook` places, moves or removes a module from a zone, after approval.

## Rules

- These tools change the live shop's settings, not the theme code. For template, CSS or JS changes, use the theme-fix skill.
- After visible changes, `clear_cache` if the merchant does not see them.
