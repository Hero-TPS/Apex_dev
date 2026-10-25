# Dev Workflow — From Claude's Zip to Git Push

Local repo: `~/Projects/Apex-Transit/Apex_dev`

## Branches and where they deploy

| Branch | Deploys to | How |
|---|---|---|
| `main` | Dev server | `deploy-to-dev.yml` — automatic on every push |
| `main` | Live | `deploy-to-live.yml` — manual only ("Run workflow"), pushes to `Apex_live` |
| `feature/itinerary` | Itinerary branch server (`apex-dev-feature.infinityfree.me`) | `deploy-to-itinerary.yml` — automatic on every push to that branch only |

Each workflow refuses to run from any branch other than its own, so the
"Run workflow" button cannot send the wrong branch's files to the wrong server.

`config.php` and `service-account-key.json` are gitignored and never deployed.
Each server has its own copy, added by hand with the file manager. The
itinerary server also has its own database (imported from dev).

## 1. Get the files

Claude delivers changed files as a `.zip` whose internal folder structure already
matches the repo (e.g. `modules/Bookings/api/index.php`). Download it — it lands in
`~/Downloads/`.

## 2. Extract into the repo — with the branch check

Always use `apexzip` instead of a bare `unzip`. It shows the branch the repo is
on and makes you type that branch's name before anything is extracted.

```bash
apexzip ~/Downloads/<zip-name>.zip
```

- It always works on `~/Projects/Apex-Transit/Apex_dev`, whatever folder your
  terminal is in, and never changes your terminal's folder.
- It prints the branch, the zip's file list, and asks you to type the branch name.
  A wrong or empty answer aborts with nothing changed.
- It warns if the working tree already has uncommitted changes, and asks before
  continuing (useful when one chat gives you two zips in a row).
- It refuses zips containing absolute paths, `..` paths or `.git` entries.
- It never runs `git add`, `commit`, `push` or `pull`.

The terminal prompt also shows the current branch: green `[main]`, red for any
other branch. If the red tag is there and you did not expect it, stop.

One-time setup (put `apex-git-helpers.sh` in your home folder as
`~/.apex-git-helpers.sh`):

```bash
echo 'source ~/.apex-git-helpers.sh' >> ~/.bashrc && source ~/.bashrc
```

Switching branch before unzipping:

```bash
cd ~/Projects/Apex-Transit/Apex_dev && git switch main
cd ~/Projects/Apex-Transit/Apex_dev && git switch feature/itinerary
```

## 3. Check what changed before committing

```bash
git status
```

Confirm it lists only the files you expect as modified — nothing extra, nothing missing.

## 4. Commit and push

```bash
git add -A
git commit -m "Describe what changed"
git push
```

Pushing `main` deploys to dev. Pushing `feature/itinerary` deploys to the
itinerary server. Check the Actions tab shows only the workflow you expected.

## Keeping `feature/itinerary` in sync with `main`

Do this whenever `main` has moved on. Commit or stash anything uncommitted first.

```bash
cd ~/Projects/Apex-Transit/Apex_dev && git fetch origin && git switch feature/itinerary && git pull
git merge origin/main
```

- Use `origin/main`, not local `main`: small fixes pasted straight onto GitHub
  mean local `main` can be behind.
- If the branch has no commits of its own yet, `git merge --ff-only origin/main`
  is safer — it refuses rather than creating a merge commit.
- If git reports conflicts, fix them, then `git add <file> && git commit`.
- Then `git push` — this deploys the merged result to the itinerary server.

**Database:** the itinerary server's database does not pick up `main`'s
migrations by itself. After a merge, check whether `main` changed the database
(entries run on dev since the last sync) and run the same SQL on the itinerary
database. Itinerary tables created on the branch exist only on that database
until the branch is merged into `main`.

**If `LIVE_MIGRATION.md` conflicts:** keep both sides' entries and delete only the
conflict markers (`<<<<<<<`, `=======`, `>>>>>>>`). Put `main`'s entries first
and the itinerary entries after. Leave each side's checkbox state as it was.

**Editing the deploy workflows:** edit them on `main` only, then sync the branch
with the steps above, so the copies never diverge.

## Avoiding a username/PAT prompt on every push

Pick one:

**A. Cache the credential permanently**
```bash
git config --global credential.helper store
```
Enter username + PAT once on the next push; it's saved in `~/.git-credentials`
(plain text) and never asked again.

**B. Switch to SSH (no PAT at all) — recommended for a long-term machine**
```bash
ssh-keygen -t ed25519 -C "your_email@example.com"
cat ~/.ssh/id_ed25519.pub
```
Paste the public key at https://github.com/settings/keys → **New SSH key**.
Then point the repo at the SSH remote:
```bash
cd ~/Projects/Apex-Transit/Apex_dev
git remote set-url origin git@github.com:Hero-TPS/Apex_dev.git
```
After that, `git push` authenticates via the key — no username/password/PAT ever again.

## Troubleshooting notes

- If `find ~ -maxdepth 5 -type d -name ".git"` doesn't show the expected repo, the
  local folder name may not match what you'd guess — this repo's folder is
  `Apex_dev` (underscore), not `Apex-dev`.
- If files were unzipped onto the wrong branch **before committing**: nothing is
  lost yet. Run `git status` to see them, then `git stash -u`, `git switch <right
  branch>`, `git stash pop`. Do not commit them on the wrong branch first.
- If a deploy run is red with "FTP_ITINERARY_SERVER_DIR is empty or missing", the
  repository secret was deleted or misspelled — re-add it under Settings →
  Secrets and variables → Actions.
