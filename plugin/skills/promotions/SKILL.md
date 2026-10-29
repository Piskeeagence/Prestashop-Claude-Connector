---
name: promotions
description: Set up PrestaShop promotions, vouchers and catalogue price rules. Use when the merchant wants a sale, a discount on a product, a category, a brand or the whole catalogue, a voucher code, or prices for a customer group.
---

# Promotions, vouchers and price rules

## Choose the right tool

| Need | Tool |
|---|---|
| Discount on one product | `create_promotion` |
| Discount on a category, a brand or the whole catalogue | `bulk_create_promotion` |
| Rule applied by PrestaShop (for example a customer group, a country, a minimum quantity) | `create_price_rule` |
| A code the customer types at checkout | `create_voucher` |
| Prices for a B2B group | `save_customer_group` with its reduction, or `create_price_rule` for that group |

## Steps

1. Read first: `list_promotions`, `list_price_rules` and `list_vouchers`, so a new discount does not stack on an existing one by accident.
2. Confirm every number with the merchant before writing: amount or percentage, tax included or not, start and end date and time, which products, which customers, usage limits. Never invent a discount.
3. Summarise the promotion in one short table and ask for approval.
4. Create it, then report what the customer will see (for example: "-20 % on 143 products of Outdoor until Sunday 23:59").
5. To stop a promotion: `delete_promotion`, `delete_price_rule` or `delete_voucher`, after confirmation.

## Rules

- A promotion that would stack on an existing one is refused unless the merchant explicitly asks to replace it.
- Bulk promotions count one change per product on the free plan.
