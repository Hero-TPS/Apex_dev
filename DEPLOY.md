# Deploy to Live — Step by Step

Follow these steps every time you want to push dev changes to the live site.

---

## ✅ Pre-Deploy Checklist

Before deploying, confirm the following:

- [ ] All changes are tested and working on local XAMPP
- [ ] All changes are committed and pushed to `Hero-TPS/HPTS-XAMPP` via GitHub Desktop
- [ ] Check `LIVE_MIGRATION.md` — are there any **Pending** SQL scripts that have not yet been run on live?

---

## Step 1 — Run Pending DB Migrations (if any)

If `LIVE_MIGRATION.md` has unchecked items:

1. Log into **phpMyAdmin** on the live server
2. Select the live database
3. Run each pending SQL script **in order**
4. Tick the **Done on live** checkbox in `LIVE_MIGRATION.md`
5. Commit the updated `LIVE_MIGRATION.md` via GitHub Desktop

> ⚠️ Always run DB migrations **before** deploying files, not after.

---

## Step 2 — Trigger the Deploy Workflow

1. Go to: https://github.com/Hero-TPS/HPTS-XAMPP/actions/workflows/deploy-to-live.yml
2. Click **Run workflow**
3. Enter a short deploy note (e.g. `Added driver notes field`, `Fixed booking bug`)
4. Click the green **Run workflow** button

This will:
- Copy all PHP/asset files from dev to `Hero-TPS/Apex_live` (excludes `config.php`, `.md` files, `.git*` files)
- Commit and push to the live repo automatically

---

## Step 3 — Watch the FTP Deploy

Once Step 2 completes, the FTP deploy triggers automatically.

Monitor it here: https://github.com/Hero-TPS/Apex_live/actions

This will:
- FTP all files from `Apex_live` directly to the live server at `hero-pts.infinityfree.me`
- Skip `config.php`, `.md` files, and `.github/` — live config is never overwritten

---

## Step 4 — Verify on Live Site

1. Open https://hero-pts.infinityfree.me and confirm changes are live
2. Do a quick smoke test of any affected features

---

## Files That Are NEVER Deployed to Live

| File | Reason |
|---|---|
| `config.php` | Live has its own DB credentials |
| `*.md` | Documentation only |
| `.gitignore` / `.gitattributes` | Git files, not needed on server |
| `.deployignore` | Reference file only |
| `.github/` | Workflow files, not needed on server |

---

## Repos Involved

| Repo | Purpose |
|---|---|
| `Hero-TPS/HPTS-XAMPP` | Dev repo — linked to local XAMPP |
| `Hero-TPS/Apex_live` | Live repo — auto-deployed to server via FTP |

---

## Notes to remember

- Files that were deleted from live repo to live server will have to be manually deleted.

---

## Something Went Wrong?

- **Deploy workflow failed** → Check https://github.com/Hero-TPS/HPTS-XAMPP/actions for error details
- **FTP deploy failed** → Check https://github.com/Hero-TPS/Apex_live/actions for error details
- **Site broken after deploy** → Manually FTP the previous working files, fix on dev first, then redeploy
- **DB out of sync** → Check `LIVE_MIGRATION.md` for any missed migrations
