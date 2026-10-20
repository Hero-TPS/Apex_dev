# Live Migration Scripts

SQL scripts that must be run on the **live database** during the next update.
Run in order. Check off each one after executing.

---

### [whatsapp] Add the combined bookings message (`booking_group`)

Needed for: the "Send combined message" button on a client's bookings page. Safe to run more than once.

- [ ] Done on dev
- [ ] Done on live

```sql
INSERT IGNORE INTO whatsapp_templates (template_key, label, placement, body, body_af, active, sort_order, is_builtin) VALUES
('booking_group', 'Combined bookings confirmation', 'booking_group', '*BOOKINGS CONFIRMED* ✅

Good day {client_name},

{trips}

💰 Cost: {cost_each} each
💰 Total: {cost_total}

🚗 Looking forward to being of service to you. 👍

Regards,
{business_owner}
{business_name}', '*BESPREKINGS BEVESTIG* ✅

Goeie dag {client_name},

{trips}

💰 Koste: {cost_each} elk
💰 Totaal: {cost_total}

🚗 Ons sien daarna uit om u van diens te wees. 👍

Groete,
{business_owner}
{business_name}', 1, 100, 1);
```

---

### [whatsapp] Combined bookings message: add the total line

Needed for: showing a total when the trips in a combined message have different prices. Run after the entry above. Only changes the message if it does not have `{cost_total}` yet, so it is safe to run more than once. If you have edited this message under WhatsApp Messages, add the total line there by hand instead (`💰 Total: {cost_total}` / `💰 Totaal: {cost_total}`, under the cost line).

- [x] Done on dev
- [x] Done on live

```sql
UPDATE whatsapp_templates
SET body = REPLACE(body, '💰 Cost: {cost_each} each', CONCAT('💰 Cost: {cost_each} each', CHAR(10), '💰 Total: {cost_total}')),
    body_af = REPLACE(body_af, '💰 Koste: {cost_each} elk', CONCAT('💰 Koste: {cost_each} elk', CHAR(10), '💰 Totaal: {cost_total}'))
WHERE template_key = 'booking_group'
  AND body NOT LIKE '%{cost_total}%';
```

---

**Notes:**
- Each entry requires two checkboxes: **Done on dev** and **Done on live**
- Always run on dev first and verify before running on live
- Move completed items to the Completed section with the date done
