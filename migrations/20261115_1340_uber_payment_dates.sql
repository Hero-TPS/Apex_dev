-- migrations/20261115_1340_uber_payment_dates.sql
-- Fleet report, part 3: the day a vehicle repair payment was made, and a fuller report message.
-- paid_on is the date entered next to a Vehicle Repairs cost row on the weekly Uber entry.
-- Payments without a date keep working: the reports show the week they were paid in instead.
-- Also gives the built-in Uber fleet report message a Brought forward line and a per-repair
-- breakdown (original cost, every payment with its date, still due). The message is only
-- replaced when it still has the wording it was installed with, so a message you have
-- edited yourself under WhatsApp Messages is left alone.
-- Needs migration 20261115_0610 (the fleet report message) to have been run first.
-- Safe to run more than once.
ALTER TABLE uber_additional_costs
    ADD COLUMN IF NOT EXISTS paid_on DATE NULL DEFAULT NULL;

UPDATE whatsapp_templates
SET body = '🚗 *Uber weekly report*
📅 {week}

📊 Brought forward: {brought_forward}
💳 Card income: {card_income}
🚗 Car rental: {car_rental}
🚨 Fines: {fines}
🔧 Repairs paid this week: {repairs_paid}
📈 Net this week: {net}
💰 Paid in: {paid_in}
📊 Balance: {balance}

{if repairs_detail}
🔧 *Vehicle repairs*
{repairs_detail}

Total still due: {repairs_owing}
{/if}

📝 Notes: {notes}
🚙 Odo: {odo}'
WHERE template_key = 'uber_fleet_report'
  AND body = '🚗 *Uber weekly report*
📅 {week}

💳 Card income: {card_income}
🚗 Car rental: {car_rental}
🚨 Fines: {fines}
🔧 Repairs paid this week: {repairs_paid}
{repairs_detail}
🔧 Repairs still owing: {repairs_owing}
📈 Net this week: {net}

💰 Paid in: {paid_in}
📊 Balance: {balance}

📝 Notes: {notes}
🚙 Odo: {odo}';
