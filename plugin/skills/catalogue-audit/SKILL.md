---
name: catalogue-audit
description: Audit a PrestaShop catalogue and turn the findings into a prioritised fix plan. Use when the merchant asks what is wrong with their shop or catalogue, wants a health check, or does not know where to start.
---

# Catalogue audit

## Steps

1. Call `shop_info` to learn the languages, the currency and the plan.
2. Call `catalog_audit`. It counts what is worth fixing in one language, with sample product ids: thin or missing descriptions, missing or too long meta titles and descriptions, products active but out of stock or inactive but in stock, zero prices, thin category descriptions, and more. Run it in each shop language that matters.
3. Add context when it helps: `low_stock` for stock risks, `sales_summary` to know which products sell, so that fixes start with the products that bring money.
4. Present a short plan, ordered by impact on sales:
   - one table: issue, number of products, impact, proposed fix, effort;
   - put best sellers and active products first;
   - never more than 5 priorities.
5. Ask which priority to start with. Then hand over to the matching skill (product-seo for texts, storefront for menus and home page, theme-fix for template problems).

## Rules

- The audit only reads. Nothing changes until the merchant approves a fix.
- Never invent product facts, prices or EANs to fill a gap: list what is missing and ask the merchant for the data.
- On the free plan, changes are limited to 100 a month. If a fix plan needs more, say so before starting, so the merchant can choose what matters most.
