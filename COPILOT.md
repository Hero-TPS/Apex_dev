# Copilot Session Preferences

## Display
- **"show"** = display the full file content in a file block (workbench view). Never summarise, never use tables. Full content always.

## Workflow
- The user updates git manually via **GitHub Desktop** — do not suggest git commands, CLI steps, or automated commits/pushes.
- Do not use agents or automated PR creation unless explicitly asked.
- Do not assume what the next priority is — the user decides what to work on next.
- Never put db update and create SQL code in files. Always use LIVE_MIGRATION.md. And notify me as such in chats and when using agents

## General
- This is a PHP/MySQL project running on XAMPP (local) and a hosted server.
- Timezone is always `Africa/Johannesburg` (UTC+2 / SAST). Use the `TIME_ZONE` constant.
- All styles go in `assets/css/styles.css` — never inline styles.
- Database changes always require migration SQL to be provided first.
- Always use PDO prepared statements for queries and `htmlspecialchars()` for output.
- API responses use `jsonResponse(['success' => bool, 'message' => string])`.
- Logging via `logInfo()`, `logError()`, `logWarning()`, `logCritical()`.
- WhatsApp links use `formatPhoneNumberForWhatsApp($phone)`.