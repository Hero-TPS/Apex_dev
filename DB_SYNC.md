# Sync Live DB → Dev — Step by Step

Use this whenever dev data is stale and you want to pull a fresh copy of the
live database down to the dev database (both hosted, both accessed via
phpMyAdmin) for testing. This is the **reverse** direction of `DEPLOY.md`
(which pushes dev *files* to live) — this doc is about pulling live *data*
down to dev.

> ⚠️ This overwrites the dev database. Any dev-only test data will be lost.
> If you've got dev records you want to keep, note them down before
> importing.

---

## ✅ Before You Start

- [ ] Check `LIVE_MIGRATION.md` — if there are pending migrations not yet run
      on live, run those on live first (or the export you pull down won't
      have the latest columns/tables)
- [ ] Close any open tabs/forms on the dev site so nothing writes to the dev
      DB mid-import

---

## Step 1 — Export From Live (phpMyAdmin on infinityfree.me)

1. Log into the infinityfree.me control panel → phpMyAdmin for
   `hero-pts.infinityfree.me`
2. Select the live database in the left sidebar
3. Go to the **Export** tab
4. Export method: **Custom** (not Quick — you need the options below)
5. Format: **SQL**
6. Under **Object creation options**, tick:
   - **Add DROP TABLE / VIEW / PROCEDURE / FUNCTION / EVENT statement**
     (so the import cleanly replaces dev tables instead of erroring on
     "table already exists")
7. Click **Go** — this downloads a `.sql` file (named something like
   `<db_name>.sql`)

> If the download fails or times out (large DB), stay in Custom export and
> untick any large/irrelevant tables (e.g. `system_logs`) to shrink the file
> — see [Common Problems](#common-problems) below.

---

## Step 2 — Import Into Dev (phpMyAdmin on the dev host)

1. Log into phpMyAdmin for the dev database
2. Select the dev database in the left sidebar
3. **Back up first (optional but safer):** Export tab → Quick → Go, so you
   have a rollback copy of dev before overwriting it
4. Go to the **Import** tab
5. Choose the `.sql` file downloaded in Step 1
6. Format: **SQL**
7. Under **Partial import**, tick **"Disable foreign key checks"** —
   this is the setting that fixes the foreign key errors you've been
   running into. It lets phpMyAdmin drop and recreate tables in whatever
   order they appear in the file, without complaining that a table being
   dropped/created is referenced by a foreign key elsewhere
8. Click **Go**

> If your phpMyAdmin version doesn't have that checkbox: run this once in
> the **SQL** tab on the dev DB *before* importing, then import as normal,
> then run the second line *after* the import finishes:
> ```sql
> SET FOREIGN_KEY_CHECKS=0;
> -- import here --
> SET FOREIGN_KEY_CHECKS=1;
> ```

---

## Step 3 — Verify

- [ ] Open the dev site and check a few modules load without errors
      (Bookings, Uber, Financials)
- [ ] Confirm `config.php` on dev still points to the **dev** DB name —
      importing data doesn't touch `config.php`, but worth a sanity check
      after any DB work
- [ ] Spot-check row counts on 1–2 key tables against what you'd expect from
      live

---

## Common Problems

- **Foreign key errors during import** → see Step 2 — disable foreign key
  checks for the duration of the import, re-enable after
- **Import fails / times out on a large file** → hosted phpMyAdmin usually
  caps upload size lower than you'd like. Stay on Custom export and exclude
  bulky/non-essential tables (logs, old archived data) to shrink the file,
  or check if your host offers a bigger upload limit / SSH access for
  larger dumps
- **"Table already exists" error on import** → re-export from live with
  **Add DROP TABLE** ticked (see Step 1), or manually drop the conflicting
  dev tables first
- **DB name in the `.sql` file doesn't match dev's DB name** → phpMyAdmin
  export sometimes includes a `USE <live_db_name>;` line or
  `CREATE DATABASE` statement. If dev uses a different DB name, either open
  the `.sql` file in a text editor and remove/edit that line before
  importing, or ask your host to match the DB name
- **Import "succeeds" but site looks unchanged** → double check you selected
  the correct database in phpMyAdmin before hitting Import

---

## Notes to Remember

*(add anything you learn from doing this that
isn't covered above)*
