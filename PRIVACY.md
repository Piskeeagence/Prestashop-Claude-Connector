# Privacy policy: Piskee for PrestaShop

Last updated: 29 October 2026

This policy covers the Piskee for PrestaShop module (`piskeeconnector`), the Piskee Pro add-on (`piskeeconnectorpro`) and the Piskee plugin for Claude. They are published by Piskee, e-commerce SEO & GEO agency, Chantilly (Oise), France. Contact: louisalexandre@piskee.com.

## In short

Your shop's data stays in your shop. The module runs on your own server and talks to Claude directly. Piskee receives no product, order or customer data.

## 1. How the module works

- The module is installed on the merchant's own PrestaShop server. It exposes an MCP server, protected by OAuth 2.1, that the merchant connects to Claude.
- When the merchant asks Claude for something, Claude calls the module on the merchant's shop. The module reads or changes data in the shop, with the rights of the employee who approved the connection, and sends the answer back to Claude.
- This data goes between the merchant's shop and Anthropic only. It does not go through Piskee's servers. Anthropic processes it under its own terms and privacy policy, as the merchant's AI provider.

## 2. What is stored in the shop

In the shop's own database: the OAuth connections (tokens stored as SHA-256 hashes only), the count of changes per month, the module settings and, with Pro, the history of changes made by Claude (to undo them). Backups of theme files and images are stored in the shop's files. The merchant can revoke every connection and delete this data by uninstalling the module.

## 3. What is sent to Piskee

- **Version check:** once a day at most, the module asks www.piskee.com for the latest version number. The request contains no shop data. Like any web request, it shows the server's IP address.
- **Pro licence:** for shops with a Pro licence only, the module sends the licence key to www.piskee.com to renew it shortly before it expires and to download Pro updates. The key identifies the licence and the shop domain it was issued for.
- **Purchase:** subscriptions are paid through Stripe. Piskee receives the billing details needed to issue the licence and the invoices.

Piskee uses this data only to provide updates, licences and invoices. It is not sold or shared, except with the providers needed for these purposes (hosting, Stripe, accounting). Licence and billing data are kept for as long as legally required.

## 4. The Claude plugin

The plugin contains instructions (skills) and the address of the merchant's own shop connector. It runs no code, stores nothing and sends nothing to Piskee.

## 5. Customers of the shop

The merchant remains the data controller of their customers' data. Claude can read customer and order data only through the merchant's request and with the merchant's rights. Messages to customers are sent only after the merchant approves them.

## 6. Your rights

Under the GDPR, you can ask for access to, correction of or deletion of the data Piskee holds about you (licence and billing data): louisalexandre@piskee.com. You can also complain to the CNIL (www.cnil.fr).
