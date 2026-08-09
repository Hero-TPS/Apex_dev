# Claude Session Context — Apex Transit Intranet

## Display & Workflow
- **"show"** = display full file content, never a summary or table.
- PHP files are always delivered as complete, drop-in files — not diffs or patches. SQL is the exception: always as a migration entry appended to `LIVE_MIGRATION.md`, never inline-only in chat or embedded in PHP.
- New SQL migration entries always show the full, updated `LIVE_MIGRATION.md` file (not just the new entry) so it stays trackable across separate chats. New entries' checkboxes stay unchecked — never auto-mark "Done on dev"/"Done on live", that's his call.
- CSS changes are delivered as a snippet (not the whole `styles.css`, which is large), but always as a `.css` file — never `.txt` — so it opens for copying instead of forcing a download.
- Don't assume the next priority — confirm before building past what was asked.
- add a comment at the top (if not already added) to every file with path from root. Most files have it. ex: modules/Bookings/api/index.php

## Conventions
- PHP/MySQL. Timezone always `Africa/Johannesburg` — use the `TIME_ZONE` constant.
- All styles in `assets/css/styles.css`, no inline styles. Module-specific classes use an `at-` prefix.
- PDO prepared statements always; `htmlspecialchars()` on all output.
- API responses: `jsonResponse(['success' => bool, 'message' => string])`.
- Logging via `logInfo()`, `logError()`, `logWarning()`, `logCritical()`.
- Keep `config.php` and `config.example.php` structurally in sync when adding new constants.
- Fixed/rarely-changing values go in `system_variables` (`SYSTEM_VARIABLES` in `includes/helpers.php`), editable via Maintenance — not new tables.
- Booking cancellations are hard-deleted — no `cancelled` status to filter on.

## File Naming During Active Iteration
While a file is still being iterated on in a chat, version it as `{Module}_{filename}_v{N}.php` so old and new versions aren't confused. Version bumps per file, not globally. Drop the suffix once committed.
