-- migrations/20261115_0610_uber_fleet_report.sql
-- Fleet manager Uber report, part 2: the WhatsApp message and its send log.
-- uber_report_log keeps every report that was sent (the exact text, the number and when),
-- so there is a record to look back on if there is ever a dispute.
-- sent_at is a unix timestamp, like the other Uber and fuel timestamps.
-- Also adds the message wording as a built-in WhatsApp template (placement uber_report),
-- English only. It is edited under WhatsApp Messages like the other built-in messages.
-- The fleet manager number is a System Variable and needs no database change.
-- Needs migration 20261112_1640 (the recipient column on whatsapp_templates) to have been run.
-- Safe to run more than once.
CREATE TABLE IF NOT EXISTS uber_report_log (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    uber_income_id  INT UNSIGNED NOT NULL,
    recipient_phone VARCHAR(30) NOT NULL,
    message_content TEXT NOT NULL,
    sent_at         INT UNSIGNED NOT NULL,
    INDEX idx_url_week (uber_income_id, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO whatsapp_templates (template_key, label, placement, recipient, body, body_af, active, sort_order, is_builtin)
SELECT 'uber_fleet_report', 'Uber fleet manager report', 'uber_report', 'client',
'🚗 *Uber weekly report*
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
🚙 Odo: {odo}',
NULL, 1, 900, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM whatsapp_templates x WHERE x.template_key = 'uber_fleet_report');
