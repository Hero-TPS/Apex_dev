# Copilot Session Preferences

## Display
- **"show"** = display the full file content in a file block (workbench view). Never summarise, never use tables. Full content always.

## Workflow
- The user updates git manually via **GitHub Desktop** — do not suggest git commands, CLI steps, or automated commits/pushes.
- Do not use agents or automated PR creation unless explicitly asked.
- Do not assume what the next priority is — the user decides what to work on next.
- Never put db update and create SQL code in files. Always use LIVE_MIGRATION.md. And notify me as such in chats and when using agents. 

## General
- This is a PHP/MySQL project running on XAMPP (local) and a hosted server.
- Timezone is always `Africa/Johannesburg` (UTC+2 / SAST). Use the `TIME_ZONE` constant.
- All styles go in `assets/css/styles.css` — never inline styles.
- Database changes always require migration SQL to be provided first.
- Always use PDO prepared statements for queries and `htmlspecialchars()` for output.
- API responses use `jsonResponse(['success' => bool, 'message' => string])`.
- Logging via `logInfo()`, `logError()`, `logWarning()`, `logCritical()`.
- WhatsApp links use `formatPhoneNumberForWhatsApp($phone)`.
- Don't use JS alert. Show in HTML and log to watchdog

## Waze Link Generation (Booking Detail View)
- GPS coordinates are stored in `contacts.pickup_lat` / `contacts.pickup_lng` (aliased as `client_pickup_lat` / `client_pickup_lng` in the booking result array) and always reference the **original_pickup** location.
- `$pickupIsStandard` must be derived from `$booking['original_pickup']` (not the effective post-swap pickup).
- Waze link logic respects the `was_swapped` flag:
  - **Not swapped, GPS present:** pickup Waze link uses `?ll=LAT,LNG&navigate=yes`; destination uses `?q=ENCODED_ADDRESS&navigate=yes`.
  - **Swapped, GPS present:** destination Waze link uses `?ll=LAT,LNG&navigate=yes`; pickup uses `?q=ENCODED_ADDRESS&navigate=yes`.
  - **No GPS:** both links fall back to `?q=ENCODED_ADDRESS&navigate=yes`.
- The "Mark / Update Pickup GPS" button is visible whenever `$pickupIsStandard` is `true` (original_pickup is a named destination, regardless of swap state).
- GPS save/update always targets the client record (`contacts.pickup_lat`, `contacts.pickup_lng`) via `Clients/api/index.php?action=save_pickup_gps`.