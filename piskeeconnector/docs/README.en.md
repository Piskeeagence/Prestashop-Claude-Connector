# Piskee for PrestaShop, documentation

Piskee links your PrestaShop store to Claude: the AI assistant of your back office. You say what you want to do ("rewrite the meta descriptions of the Dresses category", "create sizes S to XL for this product", "which products are out of stock?") and Claude acts directly in your shop, with your rights and under your control.

Designed and built in Chantilly, France, by Piskee, e-commerce SEO & GEO agency.

## Before you start

- PrestaShop 1.7.6 or later (1.7, 8 and 9), PHP 7.2 or later.
- The shop on HTTPS, at the root of its domain, with friendly URLs enabled.
- Nothing else: the module ships its own MCP server and OAuth 2.1 authorisation server.
- A Claude account (claude.ai, the Claude app or Claude Code).

## Installation

1. Modules, Module Manager, Upload a module: drop the module zip file.
2. Open the Piskee module: the Connection tab checks everything. Click **Test the connection** for a live test of the public addresses.

## Connect Claude

1. Copy the address shown in the Connection tab (it ends with `/module/piskeeconnector/mcp`).
2. In Claude: Settings, Connectors, Add, Add custom connector.
3. Name it "Piskee", paste the address, keep the detected settings (sign in now, automatic registration), click Add, then Connect.
4. Your back office opens on an authorisation screen: check the requested rights and click **Authorise**.

With Claude Code, one command:

```
claude mcp add --transport http piskee https://your-shop.com/module/piskeeconnector/mcp
```

Then type `/mcp` in Claude Code to log in.

## What Claude can do

**Products and categories**: search, read and update a product (prices, references, EAN, weight, status, texts and SEO in every language), create a product (inactive by default), manage stock exactly or by goods in and out, set categories, update a category.

**Combinations**: list and create attributes (size, colour...) and their values, create up to 100 combinations at once with price impact, reference, EAN and stock, update a combination.

**Images**: add an image from a web address, write alt texts, choose the cover, downsize heavy originals, centre them on a square background and rebuild every thumbnail.

**Enrichment**: features, brands, related products, promotions (percentage or amount, with dates), and a full catalogue audit listing what holds back your sales and search visibility.

**Cross-selling and cart**: related products (add, remove, replace, up to 50 products at once), "frequently bought together" suggestions computed from your real orders, and the native "customers who also bought" block on the product page and in the cart as additional purchases. Other upsell modules are detected and listed, never changed.

**Orders and tracking**: find an order (reference, customer, tracking number), read it with its history and carrier tracking link, change its status, set a tracking number.

**CMS pages and translations**: read and update CMS pages, update up to 50 products at once (prices, statuses, texts or translations).

**Daily management**: sales summary for a period, low stock, customer search and record, reading customer service messages and replying by email (after your approval), creating vouchers.

**Child theme**: create a draft child theme, read and search theme files, audit the headings and breadcrumb of a live page, change the templates, CSS and JavaScript of the draft, restore a backup.

Three ready-made guides are also offered in Claude's menu: Daily brief, Optimise the catalogue, Clean headings and add a breadcrumb.

## What Claude cannot do

- Refund a customer or touch a payment.
- Change the live theme or a parent theme.
- Put a theme live: you click, in the Child theme tab.
- Delete a product, a customer or an order.
- Get more rights than the employee who connected it.

## Safeguards

- Every tool that changes the shop is flagged as sensitive: Claude asks for your confirmation before running it.
- Rights are those of the PrestaShop profile of the employee who authorised the connection, checked on every call.
- Every theme change is backed up. PHP files and the theme configuration cannot be changed. Templates are checked before they are saved: unbalanced Smarty tags, PHP functions outside a short allow list, class calls, constants and file reads are refused.
- You can switch the server off and disconnect each assistant at any time, in the Connection tab.
- No data is sent to Piskee.

## Putting a theme live

1. Ask Claude to work on a draft child theme.
2. Open Piskee, Child theme tab, and click **Put live** next to the draft.
3. Check the home page, a category and a product. If anything looks wrong, click **Back to the previous theme**.

Only the theme changes: your modules, their settings, hook positions, image sizes and page layouts stay as they are. A draft can only be put live over a theme of the same family (same parent theme).

## Free or Pro

The free version allows 100 changes per month and per shop; reading, searches and audits are unlimited. The counter resets on the 1st of the month.

Piskee Pro (€29 excl. VAT per month, or €290 excl. VAT per year with 2 months free, launch price locked in for life for the first 100 customers, no commitment) lifts the limit and adds 301 redirects, credit slips and returns, multistore with an audit log, and priority support. An onboarding session with the agency is offered at €150 excl. VAT.

Paste the licence key you receive by email in the Free / Pro tab.

## Troubleshooting

- **"This connection request is not valid"** when authorising: remove the connector in Claude, then add it again.
- **Permanent 401 error**: some hosts strip the Authorization header. Add `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` at the top of the `.htaccess` file.
- **Claude does not see new tools**: open a new conversation.
- **Error when the authorisation screen opens**: check the shop is not in maintenance mode and that no firewall blocks the `/.well-known/` addresses.
- **A theme change does not show**: normal until the draft is live.

## Need help?

The Piskee team answers questions about the module and helps shops that want to go further: SEO and GEO audits, content, Google Ads, custom development.

- Contact: [piskee.com/contact](https://www.piskee.com/contact/)
- Email: louisalexandre@piskee.com
- WhatsApp: +33 7 69 15 39 92
- Free SEO & GEO audit in 48 h: [piskee.com](https://www.piskee.com/)

## Legal notice

Piskee for PrestaShop is an independent module published by Piskee (Chantilly, France). It is not an official product of PrestaShop SA or Anthropic, PBC, which did not develop, review or endorse it, and which bear no responsibility for it. PrestaShop is a trademark of PrestaShop SA; Claude is a trademark of Anthropic, PBC; these names are used only to state compatibility. The module is provided "as is", without warranty, within the limits of the law and of the AFL-3.0 licence. Actions decided and approved by the merchant remain the merchant's responsibility: keep regular backups and review changes before accepting them.
