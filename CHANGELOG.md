# Changelog

## 2.0.3
- Putting a draft live no longer goes through PrestaShop's full theme switch, which reset the theme's modules (menu, slider…), rebuilt image types and reordered hooks. Within one theme family only the active theme changes, and the merchant's page layouts are kept. A switch to another family is refused and sent to Design > Theme & Logo.
- Drafts no longer carry the parent's "modules to reset/disable" instructions.
- Rewriting an existing theme file now counts as a change on the free plan.
- `update_combination` no longer reports an unchanged price as a change.
- `bulk_update_products` shows the free plan counter.
- `set_product_categories` refuses a main category that is not in the list, and reports when nothing changes.
- The Symfony cache is rebuilt on install and upgrade, so the consent page route is found at once.
- Long rights no longer wrap under their icon on the consent screen.

## 2.0.1
- The consent screen is now a back office route that works on PrestaShop 8 and 9 without an admin URL token, fixing "Invalid token" on PrestaShop 9.
- Theme templates are filtered before they are written: PHP functions outside an allow list, class calls, constants, file reads, `{fetch}`, `{eval}` and similar are refused.
- Child theme writes resolve symbolic links and refuse folders shared with a live theme or its parent.
- A copy of a live child theme keeps its own configuration, and a new child starts from the parent's `custom.css` and `custom.js`.
- Page audits follow redirects by hand, on the shop's own domain and standard ports only.
- Image downloads refuse every non-public network range and non-standard port, and ignore proxy variables.
- Fixed product images on PrestaShop 9: the removed `_PS_PROD_IMG_DIR_` constant is no longer used.
- Originals are kept before an image is reshaped, and the EXIF orientation is applied.
- A failed customer service email no longer stops the response.
- Multistore: products, categories, combinations, images, features, CMS pages and vouchers are checked against the shop of the connection.
- Stock deltas are applied as a relative update in SQL. Unchanged values no longer count against the free quota.
- Theme backups can no longer be read from the web, even on nginx.
- The licence and the monthly counter are kept when the module is uninstalled.

## 2.0.0
- Built-in MCP server (Streamable HTTP) and OAuth 2.1 authorisation server.
- 56 tools across catalogue, combinations, images, orders, customer service, CMS, vouchers and child themes.
