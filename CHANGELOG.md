# Changelog

## 2.3.1
- Buying Pro from the module page: the "Go Pro" button opens the Stripe checkout of the plan chosen in the monthly / yearly switch, and the onboarding buttons open the €150 excl. VAT onboarding checkout.
- Multistore pricing: a Pro licence covers one shop. Each additional shop of a multistore installation needs its own key, at €10 excl. VAT per month, shown on the module page, in the limit message given to Claude, in the README and the documentation. Without its own key, an additional shop can be read but not changed.

## 2.3.0
- llms.txt: two new tools, `preview_llms_txt` and `publish_llms_txt`. They build the shop's llms.txt (llmstxt.org) from its categories, best sellers, customer pages and contact, show it for approval, then write it at the root of the shop and keep the previous version.
- Extension point for Piskee add-ons: modules can publish extra tools to Claude through the hook `actionPiskeeConnectorTools` (name, title, description, scope, permission, handler). Add-on tools are offered only to shops with a valid Pro licence, are checked like core tools (OAuth scope and the employee's own rights), and cannot replace a core tool.
- Piskee Pro 301 redirects (separate Pro add-on): redirects managed by Claude or from the back office, automatic redirects when the URL of a product, category or CMS page changes or when it is deleted, and 404 pages from the native "Pages not found" module.
- Pro launch price: €29 excl. VAT per month, locked in for life for the first 100 customers. Yearly plan: €290 excl. VAT (2 months free). Onboarding session: €150 excl. VAT.
- Spanish, Italian and Polish: module page, consent screen, tool titles, prompts and full documentation (FR, EN, ES, IT, PL), with a language menu on the module page.
- PrestaShop 1.7: the module page now loads its stylesheet and script (1.7 refused asset paths with a version query string).
- Add-on tools are accepted only from Piskee's own add-ons, and only when their scope and permission agree (no write tool shown as read-only).

## 2.2.0
- Cross-selling and cart: four new tools.
  - `get_cross_selling`: related products, products bought together in real orders, and the cross-selling modules installed with where they appear.
  - `suggest_related_products`: "frequently bought together" suggestions for one product or the best sellers, leaving out inactive, out of stock and already related products.
  - `bulk_set_related_products`: related products for up to 50 products at once.
  - `configure_cross_selling_block`: the native "customers who also bought" block (ps_crossselling), on the product page and in the cart.
- `set_related_products` can now add, remove or replace.
- The module description and welcome list mention cross-selling and the 301 redirects of Piskee Pro.

## 2.1.0
- PrestaShop 1.7 support: a single module now runs on PrestaShop 1.7.6 to 9.x, PHP 7.2 to 8.3.
  - On 1.7 the consent screen uses the legacy back office controller; on 8 and 9 it uses the Symfony route.
  - The product image folder is resolved for both 1.7 (`_PS_PROD_IMG_DIR_`) and 8/9 (`_PS_PRODUCT_IMG_DIR_`).
  - Code checked with PHPCompatibility (PHP 7.2+), linted with PHP 7.2, and tested end to end on PrestaShop 1.7.8.11 (PHP 7.4): OAuth, consent, the 56 tools, theme activation and rollback.
- Page audits accept the port the shop domain declares (for example shop.example:8080).
- The consent tab installs even when the shop's language list is empty.
- The plan card (usage of the free plan) is shown on every tab of the module page.

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
