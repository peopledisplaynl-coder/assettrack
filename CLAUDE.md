# AssetTrack — instructions for Claude

AssetTrack is a free, open-source ICT asset register for schools and small organisations
(PHP 8 + MySQL/MariaDB, server-rendered pages, vanilla JS). Owner: Ton (Peopledisplay).
Talk to Ton in Dutch. Code, comments and technical docs in English; everything users see
in Dutch.

## Where things run
- **Leading working copy:** `D:\PeopleDisplay_ASSET_install\assettrack` on Ton's laptop
  (this git repo; remote `github.com/peopledisplaynl-coder/assettrack`).
- **Production:** https://mijnict.peopledisplay.nl (Strato). **Test server:** a second Strato
  install. SFTP details live only in `deploy.config.json` on Ton's laptop (not in git —
  this repository is public).
- Hosting limits are design rules: shared hosting, PHP only, no SSH shell, no Node, no
  Composer, no daemons. Background work would have to be a cron-called PHP script.
  See `STRATO_HOSTING_SKILL.md` in the claude.ai project (no DELIMITER/triggers, use
  Strato DB hostname, SFTP is sequential, `__DIR__` paths).

## Layout
- App files live at the repo root (`index.php`, `modules/`, `includes/`, `templates/`, `assets/`).
- `includes/version.php` — the version (semver). Same number in `package.json`, the
  README badge and a `CHANGELOG.md` section (`npm run check:version` enforces this).
- `includes/db.php` — PDO helpers (`query`, `queryOne`, `execute`, …) and the automatic
  migration hook. `ASSETTRACK_CONFIG` (env) can point to another config (tests/demo only).
- `migrations/NNNN_name.php` — database changes, applied automatically on the first request
  after a deploy (`includes/migrations.php`, table `schema_migrations`).
- `install/` — wizard for fresh installs only; never deployed to an existing server;
  disables itself after a successful install.
- `tools/deploy/` — SFTP deploy, `tools/demo/` — fictional demo school, `tools/dev/stack.sh`
  — local MariaDB + PHP server, `tests/` — PHP and Playwright tests, `docs/` — architecture
  and user guides.

## Commands
```bash
npm install                      # once (deploy + Playwright)
npm test                         # php -l on all files, version check, PHP tests
tools/dev/stack.sh start         # local demo on http://127.0.0.1:8080 (demo / demo1234)
npm run test:e2e                 # Playwright against the local demo (+ fresh-install test)
npm run package                  # dist/assettrack-X.Y.Z.zip for a NEW installation
npm run deploy -- test --dry-run # what would be uploaded
npm run deploy -- productie      # upload changed files via SFTP (asks for the password)
npm run deploy -- productie --compare --stale  # compare every file with the server + list leftovers (uploads/deletes nothing)
php tools/demo/make-csv.php      # regenerate the fictional import CSV
AT_DEMO_MODE=false tools/dev/stack.sh start && npm run screenshots   # user-guide screenshots
npm run docs                     # docs/handleiding/handleiding.md -> PDF (Chromium) + Word (pandoc)
```
In the Claude workspace: `apt-get install mariadb-server` if missing; Chromium for
Playwright is in `/opt/pw-browsers`. Background servers stop after a while — fine once
tests have run.

## Conventions
- **Schema change** → new `migrations/NNNN_description.php` (idempotent: check with
  `migrationColumnExists()` / `migrationTableExists()`), and the same change in
  `install/install.sql`. Never edit a released migration. The PHP test
  "install.sql matches the migrated schema" catches drift.
- **New page/module** → `modules/<area>/`, `requireLogin()` + `requirePermission()`, CSRF
  on every POST (`generateCsrfToken()` / `verifyCsrfToken()`), `__DIR__` includes, link
  it in `templates/header.php` or the settings cards, add it to the e2e
  "main pages load" list.
- **New setting** → column on `companies` (global) via a migration, default, validation,
  UI under `modules/settings/`, mention in the user guide.
- **New permission** → `role_permissions` defaults in `install.sql` + a migration with
  `INSERT IGNORE`.
- Keep the demo (`tools/demo/DemoData.php`) in step with new fields so screenshots show them.
- Privacy: no real school data, names or serial numbers in the repo, tests or docs.
  Demo data is fictional (MAC `02:…`, IPs `10.…`, domain `.example`).
- Never commit or upload `includes/config.php`, `deploy.config.json` or anything in
  `assets/uploads/`.

## Every change
1. Build it fully (code, migration, demo data, docs). 2. `npm test` and `npm run test:e2e`;
look at screenshots at phone and desktop width for UI changes. 3. Update the user guide
(`docs/handleiding/handleiding.md`, screenshots, `npm run docs`) when users notice the change. 4. Bump the version in `includes/version.php`, `package.json`,
README badge, add a `CHANGELOG.md` entry. 5. Commit as Ton
(`git -c user.name="Ton" -c user.email="noreply@assettrack.local" commit`).
6. Deliver the whole project as `assettrack-update-X.Y.Z.zip` (no node_modules, .git,
config, uploads, tests/.tmp) or write it straight into the working copy on Ton's laptop.
7. Update `claude/AssetTrack_projectoverzicht.md` in the claude.ai project.
