---
name: daily-brief
description: Morning brief of the shop
---

# Daily brief

Give the merchant a short, useful brief of their shop. Answer in the merchant's language.

1. Call `sales_summary` for today, then for yesterday, and compare: orders, revenue tax included, average order. If today is Monday, also give the previous 7 days.
2. Call `low_stock` with threshold 3. List what is out of stock first, then what will run out soon. Group combinations of the same product.
3. Call `list_customer_messages` with status open. Count the conversations awaiting a reply and quote the oldest one in one line.

Then write the brief:

- Three lines of figures, with the change against the previous period.
- What needs action today, most urgent first, in at most five bullet points.
- One suggestion the merchant can say yes to (for example: "Shall I draft replies to the 3 waiting messages?").

Do not change anything in the shop during this brief. Never invent figures: if a tool fails, say which part is missing.
