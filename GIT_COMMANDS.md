# Git Commands — Copy & Paste

Repo folder: `~/Projects/Apex-Transit/Apex_dev` (underscore)
Branches: `main` (dev + live) and `feature/itinerary` (itinerary server)

Type only the CAPITAL words: `ZIPNAME`, `MESSAGE`, `FILE`, `HASH`, `RUNID`.

---

## 0. Three places (read this first)

| Place | What it is | What happens there |
|---|---|---|
| **Local copy** | `~/Projects/Apex-Transit/Apex_dev` on my computer | The ONLY place git commands run. I never edit or test here. I unzip, merge and push from here. |
| **GitHub repo** | `Hero-TPS/Apex_dev` online | Changes only when I `git push` (or edit on the GitHub website). |
| **Dev site** | The remote dev server | Shows whatever was last pushed and deployed. This is where I test. |

Every command in this file starts in the **local copy**, unless it says GitHub.

Files I create myself (diffs, copies of files for Claude) go in `~/Downloads`, never in the repo folder. Stray files in the repo get committed by accident.

---

## 1. Move around

```bash
cd ~/Projects/Apex-Transit/Apex_dev
```
Go to the repo.

```bash
cd ~/Downloads
```
Go to Downloads (where zips land).

```bash
cd ~
```
Go to home folder.

```bash
cd -
```
Jump back to the previous folder.

```bash
pwd
```
Show the folder I am in now.

```bash
ls ~/Downloads/*.zip
```
List zips in Downloads, so I can copy the exact name.

---

## 2. Where am I? (do this before anything else)

```bash
cd ~/Projects/Apex-Transit/Apex_dev && git branch --show-current && git status
```
Shows the branch and any changed files. Prompt tag: green `[main]`, red = other branch.

Always check the branch first. A commit on the wrong branch is easy to make (see section 6 to undo it).

---

## 3. Switch branch

Need a clean `git status` first (commit or stash anything changed).

```bash
cd ~/Projects/Apex-Transit/Apex_dev && git switch main && git pull
```
Go to main and update it.

```bash
cd ~/Projects/Apex-Transit/Apex_dev && git switch feature/itinerary && git pull
```
Go to the itinerary branch and update it.

---

## 4. Unzip, check, commit, push

Switch to the right branch first (section 3).

```bash
apexzip ~/Downloads/ZIPNAME.zip
```
Shows the branch and asks `Confirm you are using [branch]? (Y)es/(N)o`. Never commits.

```bash
git status
```
Only the files I expect should be listed. If anything else shows (especially under "Untracked files"), do NOT use `git add -A`. Add the files by name instead:

```bash
git add FILE
```
Repeat for each expected file.

If the list is exactly right:

```bash
git add -A && git commit -m "MESSAGE" && git push
```
Commit and push. Push to `main` = dev deploys. Push to `feature/itinerary` = itinerary server deploys.

With files added by name, use `git commit -m "MESSAGE" && git push` instead.

If `apexzip` is not found:

```bash
cd ~/Projects/Apex-Transit/Apex_dev && unzip -o ~/Downloads/ZIPNAME.zip -d .
```
Plain unzip. Check the branch yourself first!

---

## 5. Bring main into the itinerary branch

Do this when `main` has moved on. All of it happens in the local copy, on `feature/itinerary`. Clean `git status` first.

```bash
cd ~/Projects/Apex-Transit/Apex_dev && git fetch origin && git switch feature/itinerary && git pull
```
Update the branch.

```bash
git merge --no-edit origin/main
```
Merge main in. `--no-edit` skips the nano message editor. (Use `git merge --ff-only origin/main` if the branch has no commits of its own: it refuses instead of making a merge commit.)

If there is no conflict:

```bash
git push
```
Deploys the merged result to the itinerary server.

After merging: check if `main` changed the database. Run the same SQL on the itinerary DB.

### If git says CONFLICT

The merge is now "in progress". Nothing is lost.

```bash
git status
```
How to read it:
- **Unmerged paths / both modified** = the files with conflicts.
- **Changes to be committed** = changes from main that merged cleanly. These are NOT my edits.

**How to read the markers** inside a conflicted file (a "hunk" is one block between `<<<<<<<` and `>>>>>>>`):

```
<<<<<<< HEAD
the itinerary branch version (the branch I am on)
||||||| 0d96507
the original both sides started from (only shown with diff3, see section 8)
=======
the origin/main version (coming in)
>>>>>>> origin/main
```

Rule: where the two sides overlap, **main wins**. Keep the itinerary additions on top. No marker line may be left in the file, or the PHP page breaks.

**Let Claude resolve it:**

1. Copy each conflicted file to Downloads (change the path and the name for each file):
```bash
cp modules/WhatsApp/helpers.php ~/Downloads/helpers_conflicted.php
```
2. Attach the copied files in the chat. Claude sends back a zip with the finished files, using the repo paths.
3. Unzip over the conflicted files:
```bash
unzip -o ~/Downloads/ZIPNAME.zip -d ~/Projects/Apex-Transit/Apex_dev
```
4. Check no markers are left. Both commands must print nothing:
```bash
grep -rn '^<<<<<<<\|^>>>>>>>' modules assets includes
git status
```
5. Mark each fixed file as resolved, then check:
```bash
git add FILE
git status
```
`git status` must say "All conflicts fixed but you are still merging".
6. Finish and push:
```bash
git commit --no-edit
git push
```

**Get out of a merge:**

```bash
git merge --abort
```
Puts the branch back exactly as it was before the merge. Nothing is lost. Use this to start over.

Do NOT use `git merge --quit`. It forgets the merge but leaves the conflicted files behind, and then git refuses to do anything.

If `git merge --abort` says there is no merge to abort, and `git status` shows nothing I changed myself:

```bash
git reset --hard HEAD
```
Throws away the leftover conflict state. Committed work stays.

---

## 6. Oops

Unzipped on the WRONG branch, not committed yet:

```bash
git stash -u
```
Puts the files aside.

```bash
git switch RIGHTBRANCH
```
Use `main` or `feature/itinerary`.

```bash
git stash pop
```
Brings the files back on the right branch.

Stuck in the nano editor (a screen with `GNU nano` at the top):

Press `Ctrl+X`. If it asks to save, press `Y`, then `Enter`. The default message is fine.

**A commit went to the wrong branch and is already pushed:**

First find out what is on GitHub, without changing anything:

```bash
git fetch origin && git log origin/main -5 --format='%h %ad %s' --date=short
```
Shows the last 5 commits on main with their hash (the first column).

```bash
git show --stat HASH
```
Shows which files that commit changed.

Then undo it with a new commit (nothing is rewritten, so it is safe to push):

```bash
git switch main && git pull
git revert --no-edit HASH
git push
```
Use `feature/itinerary` instead of `main` if that is the branch that got it. Never use reset or force on a branch that is already on GitHub.

Throw away ONE changed file (permanent!):

```bash
git restore FILE
```

Local copy diverged from GitHub (after editing on the GitHub website):

```bash
git fetch origin && git status
```
Read what it says first. Ask before using reset or force.

Pushed from the wrong branch and unsure: stop and ask before doing anything else.

---

## 7. Live deploy

1. Push to `main` and make sure the dev site works.
2. GitHub: Actions, Deploy to Live, Run workflow, branch `main`.
3. Watch `Apex_dev` Actions (copy to Apex_live), then `Apex_live` Actions (FTP to server).

Live only runs from `main`. Itinerary only runs from `feature/itinerary`.

### From the command line

Needs the GitHub CLI (`gh`, section 8) and `workflow_dispatch:` under `on:` in the workflow file.

```bash
gh workflow run FILE.yml --ref main
```
Start a workflow by hand. `FILE.yml` is the file name in `.github/workflows/`.

```bash
gh run list --workflow=FILE.yml --limit 5
```
Shows the latest runs and their RUNID.

```bash
gh run view RUNID --log-failed
```
Shows only the steps that failed. Use `--log` for the whole log.

```bash
gh run watch
```
Follow a running workflow live.

---

## 8. One-time setup

```bash
git remote set-url origin git@github.com:Hero-TPS/Apex_dev.git
```
Use the SSH address (no PAT prompts).

```bash
git remote -v
```
Check the address it shows.

```bash
cp ~/Downloads/apex-git-helpers.sh ~/.apex-git-helpers.sh
echo 'source ~/.apex-git-helpers.sh' >> ~/.bashrc && source ~/.bashrc
```
Installs `apexzip` and the branch tag in the prompt. Do once only.

```bash
cd ~/Projects/Apex-Transit/Apex_dev && git config merge.conflictstyle diff3
```
Conflicted files also show the original version between the two sides. Makes conflicts much easier to read. Do once only.

```bash
gh auth login
```
Do once only, after installing `gh`. Choose SSH when it asks.

Fresh clone (only if the local folder is deleted):

```bash
mkdir -p ~/Projects/Apex-Transit && cd ~/Projects/Apex-Transit && git clone git@github.com:Hero-TPS/Apex_dev.git
```
