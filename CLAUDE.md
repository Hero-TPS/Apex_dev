# Claude Session Context — Apex Transit Intranet

## Delivery
- Files are always delivered as a downloadable zip, with the files at their repo paths. He unzips and commits using `GIT_COMMANDS.md` — no need to tell him how. Never deliver files as Artifacts, pasted chat text, diffs or patches.
- PHP and CSS files are complete, drop-in files (a touched module CSS file in full, never a snippet).
- SQL is always a new file in `migrations/` named `YYYYMMDD_HHMM_description.sql`, never inline-only in chat or embedded in PHP. State up front when a zip needs a migration.
- **"show"** = display the full file content in chat, never a summary or table.
- While a piece of work is still being iterated on, version the zip name (`fleet-report-piece2.zip`). Files inside the zip keep their real names.
- Add a comment at the top of every file touched in a chat, with its path from the repo root (e.g. `modules/Bookings/api/index.php`). No need to rescan files that aren't touched.

## Working Rules
- Discuss before building. Ask first, unless explicitly told to build.
- Don't assume the next priority — confirm before building past what was asked.
- Never commit, push or pull, and never offer to, unless explicitly told to. He is on the free Claude plan.

## Local Copy & Git
- The local copy of the repo is only a backup — the site is not run from a local server. Don't remind him to add gitignored files such as `config.php`.
- He uses the local copy to unzip files, then pulls, commits and pushes with git over SSH. Always give SSH-style remotes and clone URLs (`git@github.com:Hero-TPS/Apex_dev.git`), never HTTPS.
- He asks for git commands himself when he wants them.

## Migrations
- A new migration is its own file in `migrations/`, with its exact path. He runs it himself from Services → Database Updates — never mark anything applied for him. `schema_migrations` in each database is the record of what has run; there is no checklist file.
- Never edit a migration that has run anywhere — add a new file.
- Write it safe to run twice (`IF NOT EXISTS`, `INSERT IGNORE`). Plain DDL/DML only (no triggers, stored procedures or `DELIMITER`). Data fixes must not depend on one database's data.
- Database structure is never changed by hand in phpMyAdmin — only through a file in `migrations/`, so dev, live and the itinerary database stay reproducible.
- Pages that depend on a newer table or column point to Services → Database Updates in their error message.
- On `feature/*` branches keep changes additive (new tables/columns), and flag — before writing it — anything that renames, drops or redefines an existing column.

## Conventions
- PHP/MySQL. Timezone is always `Africa/Johannesburg` — use the `TIME_ZONE` constant.
- PDO prepared statements always; `htmlspecialchars()` on all output.
- API responses: `jsonResponse(['success' => bool, 'message' => string])`.
- Logging via `logInfo()`, `logError()`, `logWarning()`, `logCritical()` (all writing to `system_logs`) — never raw `error_log()`, which is invisible on `/services/logs.php`. The only legitimate uses of `error_log()` are inside `logger.php`'s own fallback and the pre-DB-connection catch in `config.php`.
- Client-side (JS) errors: call `window.logJsError(level, message, context)` from `assets/js/error-logging.js` (loaded site-wide via `includes/header.php` and `includes/header_public.php`; it forwards to `system_logs` through `services/api/log_js_error.php`). It also auto-catches uncaught errors, unhandled promise rejections and failed `$.ajax` calls, so a page only needs it directly for its own caught exceptions or a `fetch()` `.catch()` (native `fetch()` isn't covered by the automatic hook).
- Keep `config.php` and `config.example.php` structurally in sync when adding constants.
- Fixed or rarely-changing values go in `system_variables` (`SYSTEM_VARIABLES` in `includes/helpers.php`), editable at Services → System Variables — not new tables.
- Booking cancellations are hard-deleted — there is no `cancelled` status to filter on.

## CSS
- Base and shared rules live in `assets/css/styles.css`. Each module has its own file, `assets/css/modules/{module}.css`, loaded via `@import` at the very top of `styles.css`. New module-specific CSS goes in that module's file, not the base file.
- No inline `<style>` blocks in PHP files — move any you find into the module CSS file.
- Module-specific classes use an `at-` prefix.

## Things That Bit Us During Cleanup
- **Section headers and comments drift from reality.** Code repeatedly got appended under an existing header instead of a new one (e.g. Bookings/Clients/Uber CSS filed under "System Logs Styles"). Don't trust a header comment or file name as proof of what's inside — grep for real usage (`grep -rl "classname" modules/ includes/`) before assuming ownership or deciding something is dead.
- **Per-module API dispatchers legitimately share function names.** `handleAdd`/`handleUpdate`/`handleDelete` etc. appearing in several modules' `api/index.php` is the normal pattern, not duplication — each works on a different table. Don't merge them.
- **No dev-scratchpad markers in committed code** (`// ✅ FIX`, `// ✅ NEW`, `PASTE LOCATION`, etc.) — git history covers what changed and why. Genuinely temporary or debug code is described in plain language with a removal condition, like the debug logging comment in `DistanceCalculator/index.php`.
