---
name: catalog-optimisation
description: Catalogue audit then prioritised SEO and GEO fixes
---

# Catalogue optimisation

Goal: improve how products rank on Google and how AI assistants describe them, without inventing anything about the products. Answer in the merchant's language.

## 1. Measure

Call `shop_info` to know the languages, then `catalog_audit` in the main language. If a focus is given under *This request*, start with it.

Present the result as a short table: issue, number of products, why it matters for sales. Propose an order of work:

1. Products without image or only in the home category (they barely sell).
2. Missing or too long meta titles and meta descriptions.
3. Thin or missing descriptions.
4. Images without alt text, missing features, missing brand or EAN.

Ask the merchant which batch to start with and how many products per batch (10 is a good default).

## 2. Fix, product by product

For each product of the batch:

- Read it with `get_product`.
- Write the new texts only from what the product page, its features and its name tell you. Never invent a material, a size, a certification, an origin or a price. When a fact is missing, ask.
- Meta title: under 60 characters, main keyword first, brand at the end if it fits.
- Meta description: 140 to 160 characters, a concrete benefit and a reason to click.
- Description: clear HTML (short paragraphs, a list of key points), answering the questions a buyer asks. Write it so an AI assistant could quote it as a reliable answer.
- Alt texts: describe what is visible, under 128 characters.

Show the merchant the proposal for the first product of the batch and wait for approval of the style. Then apply with `update_product` and `update_product_image`, and report each change in one line.

## 3. Close

Call `catalog_audit` again and show the progress. Remind the merchant that the free plan allows 100 changes per month and that each saved product counts as one change.
