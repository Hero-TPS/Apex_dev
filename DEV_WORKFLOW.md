# Dev Workflow — From Claude's Zip to Git Push

Local repo: `~/Projects/Apex-Transit/Apex_dev`

## 1. Get the files

Claude delivers changed files as a `.zip` whose internal folder structure already
matches the repo (e.g. `modules/Bookings/api/index.php`). Download it — it lands in
`~/Downloads/`.

## 2. Extract into the repo

```bash
cd ~/Projects/Apex-Transit/Apex_dev && unzip -o ~/Downloads/<zip-name>.zip -d .
```

- `-o` overwrites existing files without prompting.
- Chaining with `&&` matters: if `cd` fails (wrong path, typo), the rest of the
  command won't silently run from the wrong directory.

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
- If a command silently ran in the wrong directory (e.g. `unzip` created a stray
  `~/modules/` folder), clean it up first: `rm -rf ~/modules`, then redo the
  extract from the correct `cd`.
