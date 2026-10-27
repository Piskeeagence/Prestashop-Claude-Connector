<p align="center">
  <img src="piskeeconnector/views/img/piskee-olive.png" alt="Piskee" width="96">
</p>

<h1 align="center">Piskee for PrestaShop</h1>
<p align="center"><strong>The AI sidekick of your PrestaShop store. Works with Claude.</strong><br>
Free module · PrestaShop 8.0 to 9.x · PHP 8.1+ · Made in Chantilly, France</p>

---

Piskee connects your PrestaShop store to Claude through the [Model Context Protocol](https://modelcontextprotocol.io). Tell Claude what you need in plain words, for example "rewrite the meta descriptions of the Dresses category", "create sizes S to XL for this product" or "which orders are still waiting for a tracking number?". Claude then does it in your back office, with your rights and under your control.

The module ships its own MCP server and its own OAuth 2.1 authorisation server. Nothing else needs to be installed, and no data goes through Piskee.

## What Claude can do with Piskee

| Area | Examples |
|---|---|
| **Products & categories** | Search, read and update products: prices, references, EAN, status, texts and SEO in every language. Create products (inactive by default). Set exact stock or record goods in and out. Assign categories. Bulk-update up to 50 products per call. |
| **Combinations** | Create attribute groups and values. Generate up to 100 combinations at once, with price impact, reference, EAN and stock. |
| **Images** | Add an image from a URL. Write alt texts and choose the cover. Downsize heavy originals or put them on a square canvas (originals are kept). Rebuild every thumbnail. |
| **Enrichment** | Features, brands, related products, promotions. A full catalogue audit: missing texts, duplicate titles, out-of-stock but active products, and more. |
| **Orders** | Find and read orders, change status, set tracking numbers. |
| **Customer service** | Read customer messages and reply by email after your approval. Create vouchers. |
| **CMS & translations** | Read and update CMS pages. Translate products, categories and pages in batches. |
| **Theme (child theme only)** | Create a draft child theme. Audit headings, breadcrumb and structured data of a live page. Edit the draft's templates, CSS and JS. Every change is backed up. **You** put the draft live with one click. |

### What it cannot do

- Refund a customer or touch a payment.
- Modify the live theme or a parent theme.
- Delete products, customers or orders.
- Get more rights than the employee who authorised it. Every call is checked against that employee's PrestaShop profile.

## Install

1. Download `piskeeconnector.zip` from the [latest release](https://github.com/Piskeeagence/Prestashop-Claude-Connector/releases/latest).
2. In your back office, go to **Modules › Module Manager › Upload a module**.
3. Open **Piskee**. The *Connection* tab checks HTTPS, friendly URLs and the public addresses, and runs a live self-test.

## Connect Claude

1. Copy the MCP address shown in the module. It ends with `/module/piskeeconnector/mcp`.
2. In Claude, open **Settings › Connectors › Add custom connector**, paste the address, then click **Connect**.
3. Your back office opens a consent screen. Review the rights and click **Allow**.

With Claude Code:

```bash
claude mcp add --transport http piskee https://your-shop.com/module/piskeeconnector/mcp
```

Recommended: a Claude Pro or Max plan, and the most capable model available to you for catalogue and theme work.

## Security model

- **OAuth 2.1:**
  - PKCE (S256) is mandatory.
  - Clients register dynamically (RFC 7591), and refresh tokens rotate.
  - Reusing a token or code revokes the whole session.
  - Tokens are stored as SHA-256 hashes only.
- **Permissions:**
  - The assistant acts as the employee who approved it.
  - Each tool also checks that employee's PrestaShop profile on every call.
- **Multistore:** a connection is bound to one shop, and objects from other shops are refused.
- **Theme:**
  - Writes only go to a non-live child theme, and only to `.tpl`, `.css` and `.js` files.
  - Templates are filtered before they are saved: PHP, class calls, constants, file reads and functions or modifiers outside an allow list are rejected.
  - Symlinks are resolved.
  - Every change is backed up.
- **Network:** image downloads and page audits refuse private networks, non-standard ports and cross-host redirects.
- The merchant can switch the server off and revoke any connection from the module page.

Found a security issue? Please email louisalexandre@piskee.com rather than opening a public issue.

## Free and Pro

The free module allows **100 changes per month** per shop. Reading, searching and audits are unlimited.

**Piskee Pro** costs €30 excl. VAT per month, with no commitment. It removes the limit and adds:
- 301 redirects, with automatic redirects when a URL changes.
- Credit slips and returns.
- Multistore with an audit log.
- GEO content fields, `llms.txt` and an AI product feed.
- Scheduled rules.
- Priority support.

Pro features are being released progressively; ask us for the current list.

An onboarding session with the agency is available for €300 excl. VAT. [Contact us](https://www.piskee.com/contact/).

## Documentation

- 🇬🇧 [Full documentation (English)](piskeeconnector/docs/README.en.md)
- 🇫🇷 [Documentation complète (français)](piskeeconnector/docs/README.fr.md)

---

## 🇫🇷 En français

Piskee relie votre boutique PrestaShop à Claude. Vous dites ce que vous voulez faire, par exemple « réécris les meta descriptions de la catégorie Robes », « crée les tailles S à XL », « quelles commandes attendent un numéro de suivi ? ». Claude agit dans votre back-office, avec vos droits et sous votre contrôle.

- **Compatibilité :** PrestaShop 8.0 à 9.x, PHP 8.1 et plus.
- **Gratuit** jusqu'à 100 modifications par mois. Version Pro illimitée à 30 € HT par mois.
- **Thème :** Claude ne touche jamais au thème en ligne. Il travaille sur un thème enfant brouillon, et c'est vous qui le mettez en ligne en un clic.
- **Installation :** Modules › Gestionnaire de modules › Installer un module, puis suivez l'onglet *Connexion* du module.

Besoin d'aide, d'un audit SEO et GEO gratuit en 48 h, ou d'un accompagnement ?
[piskee.com/contact](https://www.piskee.com/contact/) · louisalexandre@piskee.com · WhatsApp +33 7 69 15 39 92

---

## About Piskee

Piskee is an e-commerce SEO & GEO agency based in Chantilly (Oise), France. We build tools that let merchants spend less time in their back office and more time selling. → [piskee.com](https://www.piskee.com/)

## Legal notice

Piskee for PrestaShop is an independent module published by Piskee.

- **Not an official product.** PrestaShop SA and Anthropic, PBC did not develop, review or endorse it, and bear no responsibility for it.
- **Trademarks.** "PrestaShop" is a trademark of PrestaShop SA and "Claude" is a trademark of Anthropic, PBC. These names are used only to state compatibility.
- **No warranty.** The module is provided "as is", within the limits of applicable law and of the [Academic Free License 3.0](LICENSE).
- **Merchant responsibility.** Actions decided and approved by the merchant remain the merchant's responsibility. Keep regular backups.
