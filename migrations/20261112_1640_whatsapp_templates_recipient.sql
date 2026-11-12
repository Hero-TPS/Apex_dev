-- migrations/20261112_1640_whatsapp_templates_recipient.sql
-- Who a WhatsApp template is sent to: the client (default, as it has always been) or the booking's passenger.
-- Existing templates keep 'client'. Adds a passenger trip message (English only, no Afrikaans text, so it is
-- always sent in English). It is added switched OFF (active = 0): turn it on under WhatsApp Messages once the
-- booking dropdown code that understands the recipient is deployed, otherwise it would be offered for the client.
-- Safe to run more than once.
ALTER TABLE whatsapp_templates
    ADD COLUMN IF NOT EXISTS recipient ENUM('client','passenger') NOT NULL DEFAULT 'client' AFTER placement;

INSERT INTO whatsapp_templates (template_key, label, placement, recipient, body, body_af, active, sort_order, is_builtin)
SELECT 'passenger_trip', 'Passenger trip details', 'booking_menu', 'passenger',
'Hi {passenger_name} 👋
{client_name} has booked a trip for you with {business_name}.

📅 {date} at {time}
📍 From: {pickup}
🏁 To: {destination}
✈️ Flight: {flight_number}
🔎 Track the flight: {flightradar_url}
🚗 Driver: {driver_name}
📞 Driver phone: {driver_phone}
📝 {notes}

Please reply here if anything changes.
{business_owner}',
NULL, 0,
(SELECT COALESCE(MAX(t.sort_order), 0) + 10 FROM whatsapp_templates t WHERE t.placement = 'booking_menu'),
0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM whatsapp_templates x WHERE x.template_key = 'passenger_trip');
