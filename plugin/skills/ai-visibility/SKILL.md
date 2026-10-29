---
name: ai-visibility
description: Make a PrestaShop shop easier for AI assistants (ChatGPT, Claude, Perplexity) to understand and cite - llms.txt file, structured data, FAQs. Use when the merchant asks about GEO, AI search, llms.txt, or being visible in AI answers.
---

# Visibility in AI assistants (GEO)

## llms.txt

1. `preview_llms_txt` builds the file from the shop: categories, best sellers, customer service pages. Show the preview.
2. Adjust with the merchant (what the shop sells, who it is for, shipping and returns pages), then `publish_llms_txt`. The previous version is kept.

## Structured data and FAQs (Pro)

If the tools are available:
- `get_structured_data` and `set_structured_data` for Product, breadcrumb and FAQ data;
- `get_faq` and `set_faq` for product and category FAQs written from real facts (product data, customer questions in messages). Never invent an answer.

## Content that AI assistants cite

AI assistants quote clear, factual pages. Combine with the product-seo skill: complete descriptions, real specifications, delivery and return conditions written plainly.
