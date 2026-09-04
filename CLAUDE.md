# Claude Session Context — Apex Transit Intranet

## Display & Workflow
- **"show"** = display full file content, never a summary or table.
- PHP files are always delivered as complete, drop-in files via Artifacts — not diffs or patches, and not pasted as plain chat text. SQL is the exception: always as a migration entry appended to `LIVE_MIGRATION.md`, never inline-only in chat or embedded in PHP.
- New SQL migration entries always show the full, updated `LIVE_MIGRATION.md` file (not just the new entry) so it stays trackable across separate chats. New entries' checkboxes stay unchecked — never auto-mark "Done on dev"/"Done on live", that's his call.
- CSS changes are delivered as complete files via Artifacts (base `styles.css` and any touched module file in full) — never plain chat text, `.txt`, or a partial snippet. Now that most CSS work lands in the smaller per-module files, base `styles.css` sees little traffic, so there's no more size reason to snippet it.
- Don't assume the next priority — confirm before building past what was asked.
- add a comment at the top (if not already added) to every file with path from root. Most files have it. ex: modules/Bookings/api/index.php. Do it only for files that are updated during chats. No need to rescan all files in the repo.

- Discuss before building code. ask first. or when you are explicitly told to build code.

## Conventions
- PHP/MySQL. Timezone always `Africa/Johannesburg` — use the `TIME_ZONE` constant.
- Styles live in `assets/css/styles.css` (base/shared rules only) plus one file per module in `assets/css/modules/{module}.css`, loaded via `@import` at the very top of `styles.css`. New module-specific CSS goes in that module's file, not appended to the base file. No inline `<style>` blocks in PHP files — if you find one, it belongs in a module CSS file instead. Module-specific classes use an `at-` prefix.
- PDO prepared statements always; `htmlspecialchars()` on all output.
- API responses: `jsonResponse(['success' => bool, 'message' => string])`.
- Logging via `logInfo()`, `logError()`, `logWarning()`, `logCritical()` (all writing to `system_logs`) — never raw PHP `error_log()`, which is invisible on `/maintenance/logs.php`. The only legitimate uses of `error_log()` are inside `logger.php`'s own internal fallback and the pre-DB-connection catch in `config.php` — both cases where the logger itself can't be reached yet.
- Client-side (JS) errors: call `window.logJsError(level, message, context)`, from `assets/js/error-logging.js`. It's loaded site-wide via `includes/header.php` and `includes/header_public.php`, and forwards to the same `system_logs` table via `maintenance/api/log_js_error.php`. It also auto-catches uncaught JS errors, unhandled promise rejections, and any failed `$.ajax` call — a page only needs `logJsError()` directly for its own caught exceptions or a `fetch()`'s `.catch()`, since native `fetch()` isn't covered by the automatic jQuery hook.
- Keep `config.php` and `config.example.php` structurally in sync when adding new constants.
- Fixed/rarely-changing values go in `system_variables` (`SYSTEM_VARIABLES` in `includes/helpers.php`), editable via Maintenance — not new tables.
- Booking cancellations are hard-deleted — no `cancelled` status to filter on.

## Things That Bit Us During Cleanup
- **Section headers/comments drift from reality.** Code repeatedly got appended under an existing header instead of a new one (e.g. Bookings/Clients/Uber CSS ended up filed under unrelated headers like "System Logs Styles"). Don't trust a header comment or a file's name as proof of what's actually in it — grep for real usage (`grep -rl "classname" modules/ includes/`) before assuming ownership or deciding something's dead.
- **Per-module API dispatchers legitimately share function names.** `handleAdd`/`handleUpdate`/`handleDelete`/etc. appearing in multiple modules' `api/index.php` files is the normal pattern, not duplication — each operates on a different table. Don't try to merge these.
- **Avoid dev-scratchpad markers in committed code** — `// ✅ FIX`, `// ✅ NEW`, `PASTE LOCATION`, etc. accumulate as noise with no lasting value (git history already covers "what changed and why"). If code is genuinely temporary/debug (like a page's extra debug logging), say so in plain language with a removal condition, the way `DistanceCalculator/index.php`'s debug logging comment does — not a checkmark.
## File Naming During Active Iteration
While a file is still being iterated on in a chat, version it as `{Module}_{filename}_v{N}.php` so old and new versions aren't confused. Version bumps per file, not globally. Drop the suffix once committed.
