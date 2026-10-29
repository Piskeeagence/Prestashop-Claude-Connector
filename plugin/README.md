# Piskee for PrestaShop

**Stop clicking through your PrestaShop back office.** Type one sentence. Claude does the work. You approve every change.

This plugin gives Claude the know-how to run a PrestaShop store with you: audit the catalogue, rewrite product pages for SEO, set up promotions, follow orders and customer messages, rework menus and the home page, fix the theme safely in a draft, and make the shop visible in AI assistants. It works with the free **Piskee module** installed in your shop, which gives Claude 93 tools (117 with Piskee Pro).

## What you can ask

- *"Audit my catalogue and tell me what to fix first."*
- *"Rewrite the meta descriptions of the Dresses category, under 155 characters, in French and English."*
- *"Put the whole Outdoor category at -20 % until Sunday."*
- *"Which paid orders still have no tracking number? Send me the invoice of order 1042."*
- *"Add a Gift ideas category and put it in the main menu, after Women."*
- *"Our product pages have two H1 headings. Fix it in a draft theme so I can check it."*
- *"Create our llms.txt so ChatGPT and Perplexity understand what we sell."*

## Skills included

| Skill | What it does |
|---|---|
| connect-shop | Walks you through installing the module and connecting your shop |
| catalogue-audit | Audits the catalogue and turns it into a 5-priority fix plan |
| product-seo | Writes meta titles, meta descriptions, descriptions and alt texts in every shop language, in batches you approve |
| promotions | Promotions on a product, a category, a brand or the whole catalogue, vouchers, price rules, B2B group prices |
| orders-and-service | Orders to ship, tracking numbers, statuses, PDF invoices, customer replies; refunds, abandoned carts and reviews with Pro |
| storefront | Categories, main menu, mega menu (Pro), home page slider, banner and product blocks, module positions |
| theme-fix | Template, CSS and JS fixes in a draft child theme only, with backups |
| ai-visibility | llms.txt, and structured data and FAQs with Pro |

## Set up in 5 minutes

1. **Install the free module** in your shop: download `piskeeconnector-x.y.z.zip` from the [latest release](https://github.com/Piskeeagence/Prestashop-Claude-Connector/releases/latest), then Modules > Module Manager > Upload a module. PrestaShop 1.7.6 to 9, PHP 7.2+, HTTPS.
2. **Copy your shop's address** from Modules > Piskee > Connection, for example `https://your-shop.com/module/piskeeconnector/mcp`.
3. **Connect it.**
   - On claude.ai and in the Claude apps: Settings > Connectors > Add custom connector, paste the address, then Allow on your back office's consent screen.
   - In Claude Code: the plugin asks for the address when you enable it, then type `/mcp` to sign in.

Each shop has its own address, so you connect your own shop. Nobody else's.

## Zero risk, by design

- Nothing changes without your yes: Claude reads freely and asks before it writes.
- Claude acts with the PrestaShop rights of the employee who connected it, checked on every call.
- Theme work happens only in a draft child theme. Only you put a theme live.
- No payments, no money moved, no deletion of products, customers or orders.
- Free for 100 changes a month. Piskee Pro (€29 excl. VAT a month) makes changes unlimited and adds an undo history.

## Data

The plugin contains only instructions (skills) and the address of **your own shop's** connector. It runs no code and stores nothing.

- When you use it, Claude sends requests to your shop's Piskee module over HTTPS (OAuth 2.1 sign-in). Your shop data goes from your shop to Claude and back. **It does not go through Piskee.**
- The Piskee module itself checks once a day for a new version on www.piskee.com, and, for Pro shops only, sends the licence key to renew it or download Pro updates. No shop, product, order or customer data is sent.

Full details: [privacy policy](https://github.com/Piskeeagence/Prestashop-Claude-Connector/blob/main/PRIVACY.md).

## Support

Piskee, e-commerce SEO & GEO agency, Chantilly, France. [piskee.com/contact](https://www.piskee.com/contact/) · louisalexandre@piskee.com

Piskee is an independent publisher. PrestaShop is a trademark of PrestaShop SA and Claude a trademark of Anthropic, PBC; neither developed nor endorses this plugin.
