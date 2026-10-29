---
name: connect-shop
description: Connect Claude to a PrestaShop store with the Piskee module. Use when the user wants to manage a PrestaShop shop but no Piskee tools (shop_info, search_products...) are available, or asks how to install or connect Piskee.
---

# Connect a PrestaShop store

The Piskee tools come from the free Piskee module installed in the merchant's own shop. Each shop has its own address, so the merchant connects their shop once.

## If no Piskee tool is available

Explain the setup in the merchant's language, in this order:

1. **Install the module (free).** Download the latest `piskeeconnector-x.y.z.zip` from https://github.com/Piskeeagence/Prestashop-Claude-Connector/releases/latest, then in the back office: Modules > Module Manager > Upload a module.
2. **Check the shop.** Open Modules > Piskee. The Connection tab checks HTTPS, friendly URLs and the public addresses, and shows the connector address, like `https://your-shop.com/module/piskeeconnector/mcp`.
3. **Connect Claude.**
   - On claude.ai or the Claude apps: Settings > Connectors > Add custom connector, paste the address, click Connect, then Allow on the consent screen of the back office.
   - In Claude Code: the plugin asks for the address when it is enabled; otherwise run `claude mcp add --transport http piskee <address>` and type `/mcp` to sign in.

Requirements: PrestaShop 1.7.6 to 9, PHP 7.2 or later, HTTPS, friendly URLs.

## Once connected

Call `shop_info` first: it gives the shop name, languages, currency, the module version, the plan (free or Pro) and any setup warning. Then offer a first win, such as a catalogue audit (see the catalogue-audit skill).
