---
name: product-seo
description: Write or rewrite PrestaShop product and category texts for SEO (meta titles, meta descriptions, descriptions, image alt texts) in every shop language. Use when the merchant asks to improve, rewrite, translate or fill product or category texts, titles or meta descriptions.
---

# Product and category texts for SEO

## Steps

1. Scope the batch: which products (`search_products` by category, brand or query) or categories (`list_categories`), which fields, which languages (`shop_info`).
2. Read before writing: `get_product` or `get_category` for each item. Keep every fact that is already there (materials, sizes, compatibility, care). Never invent a fact, a claim, a certification or a figure.
3. Draft, following these defaults unless the merchant gives their own rules:
   - meta title: 50 to 60 characters, main keyword first, brand name at the end only if it fits;
   - meta description: 140 to 155 characters, one concrete benefit and a reason to click;
   - description: short paragraphs, the facts the customer needs to buy, no keyword stuffing;
   - image alt text: what the image shows, with the product name.
   - every language written natively, not translated word for word.
4. Show the drafts in a table (product, field, current, proposed, length) and ask for approval. Batches of 10 to 20 items are easier to review.
5. Apply the approved texts: `bulk_update_products` for up to 50 products at once, `update_product` for one, `update_category` for categories, `update_product_image` for alt texts.
6. Report what changed and what is left.

## Rules

- Each changed product counts as one change on the free plan (100 a month). Tell the merchant the count before a large batch.
- Pro adds structured data and FAQs (`set_faq`, `set_structured_data`) and an undo history (`list_changes`, `undo_changes`): offer them only if the tools are available.
