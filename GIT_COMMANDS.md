# Git Commands — Copy & Paste

Repo folder: `~/Projects/Apex-Transit/Apex_dev` (underscore)
Branches: `main` (dev + live) and `feature/itinerary` (itinerary server)

Type only the CAPITAL words: `ZIPNAME`, `MESSAGE`, `FILE`.

---

## 1. Move around

```bash
cd ~/Projects/Apex-Transit/# Git Commands — Copy & Paste

Repo folder: `~/Projects/Apex-Transit/Apex_dev` (underscore)
Branches: `main` (dev + live) and `feature/itinerary` (itinerary server)

Type only the CAPITAL words: `ZIPNAME`, `MESSAGE`, `FILE`.

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
Only the files I expect should be listed.

```bash
git add -A && git commit -m "MESSAGE" && git push
```
Commit and push. Push to `main` = dev deploys. Push to `feature/itinerary` = itinerary server deploys.

If `apexzip` is not found:

```bash
cd ~/Projects/Apex-Transit/Apex_dev && unzip -o ~/Downloads/ZIPNAME.zip -d .
```
Plain unzip. Check the branch yourself first!

---

## 5. Bring main into the itinerary branch

Do this when `main` has moved on. Clean `git status` first.

```bash
cd ~/Projects/Apex-Transit/Apex_dev && git fetch origin && git switch feature/itinerary && git pull
```
Update the branch.

```bash
git merge origin/main
```
Merge main in. (Use `git merge --ff-only origin/main` if the branch has no commits of its own: it refuses instead of making a merge commit.)

```bash
git push
```
Deploys the merged result to the itinerary server.

After merging: check if `main` changed the database. Run the same SQL on the itinerary DB.

If git says CONFLICT:

```bash
git status
```
Lists the conflicted files. Open them, keep both sides, delete the `<<<<<<<`, `=======`, `>>>>>>>` lines. Then:

```bash
git add FILE && git commit
```
Repeat `git add` for each fixed file, then one `git commit`, then `git push`.

Give up and start over (nothing is lost):

```bash
git merge --abort
```

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

Pushed from the wrong branch: stop and ask before doing anything else.

Throw away ONE changed file (permanent!):

```bash
git restore FILE
```

Local copy diverged from GitHub (after editing on the GitHub website):

```bash
git fetch origin && git status
```
Read what it says first. Ask before using reset or force.

---

## 7. Live deploy

1. Push to `main` and make sure the dev site works.
2. GitHub: Actions, Deploy to Live, Run workflow, branch `main`.
3. Watch `Apex_dev` Actions (copy to Apex_live), then `Apex_live` Actions (FTP to server).

Live only runs from `main`. Itinerary only runs from `feature/itinerary`.

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

Fresh clone (only if the local folder is deleted):

```bash
mkdir -p ~/Projects/Apex-Transit && cd ~/Projects/Apex-Transit && git clone git@github.com:Hero-TPS/Apex_dev.git
```
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
Shows the branch, I type the branch name to confirm. Never commits.

```bash
git status
```
Only the files I expect should be listed.

```bash
git add -A && git commit -m "MESSAGE" && git push
```
Commit and push. Push to `main` = dev deploys. Push to `feature/itinerary` = itinerary server deploys.

If `apexzip` is not found:

```bash
cd ~/Projects/Apex-Transit/Apex_dev && unzip -o ~/Downloads/ZIPNAME.zip -d .
```
Plain unzip. Check the branch yourself first!

---

## 5. Bring main into the itinerary branch

Do this when `main` has moved on. Clean `git status` first.

```bash
cd ~/Projects/Apex-Transit/Apex_dev && git fetch origin && git switch feature/itinerary && git pull
```
Update the branch.

```bash
git merge origin/main
```
Merge main in. (Use `git merge --ff-only origin/main` if the branch has no commits of its own: it refuses instead of making a merge commit.)

```bash
git push
```
Deploys the merged result to the itinerary server.

After merging: check if `main` changed the database. Run the same SQL on the itinerary DB.

If git says CONFLICT:

```bash
git status
```
Lists the conflicted files. Open them, keep both sides, delete the `<<<<<<<`, `=======`, `>>>>>>>` lines. Then:

```bash
git add FILE && git commit
```
Repeat `git add` for each fixed file, then one `git commit`, then `git push`.

Give up and start over (nothing is lost):

```bash
git merge --abort
```

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

Pushed from the wrong branch: stop and ask before doing anything else.

Throw away ONE changed file (permanent!):

```bash
git restore FILE
```

Local copy diverged from GitHub (after editing on the GitHub website):

```bash
git fetch origin && git status
```
Read what it says first. Ask before using reset or force.

---

## 7. Live deploy

1. Push to `main` and make sure the dev site works.
2. GitHub: Actions, Deploy to Live, Run workflow, branch `main`.
3. Watch `Apex_dev` Actions (copy to Apex_live), then `Apex_live` Actions (FTP to server).

Live only runs from `main`. Itinerary only runs from `feature/itinerary`.

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

Fresh clone (only if the local folder is deleted):

```bash
mkdir -p ~/Projects/Apex-Transit && cd ~/Projects/Apex-Transit && git clone git@github.com:Hero-TPS/Apex_dev.git
```
