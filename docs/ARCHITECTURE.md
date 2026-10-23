# AssetTrack architecture

## Overview
Classic server-rendered PHP application: every page is a PHP file that includes
`includes/db.php`, `includes/auth.php` and `includes/functions.php`, checks login and
permissions, handles its own POST (with CSRF token) and renders HTML through
`templates/header.php` / `templates/footer.php`. There is no framework, no Composer and
no build step, so the code can be uploaded to shared hosting as-is.

```
browser ──HTTP──> modules/*.php ──> includes/functions.php ──> includes/db.php ──PDO──> MariaDB
                        │                                         └─ autoMigrate() on first connect
                        └─ templates/header.php + footer.php (theme from `companies`)
```

## Request lifecycle
1. `includes/db.php` loads `includes/version.php`, the config (`includes/config.php`, or
   `ASSETTRACK_CONFIG` for tests/demo) and defines the PDO helpers.
2. The first `getDB()` call of a request connects and runs `autoMigrate()`: one
   `SELECT version FROM schema_migrations` plus a directory listing of `migrations/`.
   Pending migrations run under `GET_LOCK('assettrack_migrations')`; failures are logged
   with `error_log()` and retried on the next request, never shown to users.
3. `includes/auth.php` starts the session (8 hours), restores a "remember me" login
   (selector/validator cookie, table `remember_tokens`, rotated on use) and offers
   `requireLogin()`, `requirePermission()`, `hasPermission()`, `getLocationId()`.
4. The page queries data with `query()/queryOne()/execute()` (prepared statements only).

## Data model (main tables)
| Table | Purpose |
|---|---|
| `organisations` → `locations` → `rooms` | Foundation, schools/sites, rooms. `assets.room` is free text matched on name + location (no FK). |
| `assets` | One device; `location_id` FK, many descriptive columns (dates, network, hardware). |
| `asset_images`, `asset_relations` | Photos (in `assets/uploads/asset_images/`) and links (desktop ↔ monitor). |
| `custom_fields`, `custom_field_values` | Extra fields defined by the admin. |
| `asset_templates` | Presets for new assets. |
| `users`, `user_locations`, `role_permissions` | Accounts, per-location rights, per-role permissions. |
| `remember_tokens` | "30 dagen ingelogd blijven". |
| `companies` | Single row with global settings: branding, theme, `scan_base_url`. |
| `brands`, `asset_types` | Lookup lists (free text on assets, no FK). |
| `kb_categories`, `kb_articles`, `kb_article_assets` | Knowledge base. |
| `audit_log` | Who changed what. |
| `schema_migrations` | Applied database migrations. |

Fresh installs get the full schema from `install/install.sql`; existing installs reach
the same schema through `migrations/`. A PHP test compares both.

## Pages (main routes)
| Path | What |
|---|---|
| `index.php` | Login (+ redirect back to a scanned asset) |
| `select_location.php`, `switch_location.php` | Choose the active school/site |
| `dashboard.php` | Totals, warnings, recent activity |
| `modules/assets/` | List/search, add, edit, view, bulk edit, import (CSV), templates, relations, images, scanner, `scan.php` (QR target), search across locations |
| `modules/labels/` | Label sheets/rolls with QR or barcode, PDF/print, PNG for pocket printers |
| `modules/reports/` | 8 reports, column choice, CSV and PDF (FPDF in `includes/lib/fpdf/`) |
| `modules/kb/` | Knowledge base |
| `modules/users/` | Users, permissions, own profile |
| `modules/settings/` | Organisation/locations/rooms, lists, custom fields, appearance, scan domain, system/audit log, backup |
| `install/` | Installation wizard (fresh installs only) |

## Deploy and release
- `npm run deploy -- <target>` uploads changed app files over SFTP (sequentially),
  remembers hashes in `.deploy-state-<target>.json`, refuses targets without an existing
  `includes/config.php`, never touches `includes/config.php`, `assets/uploads/` or
  `install/`, and never deletes remote files (`--stale` lists leftovers).
- `npm run package` builds the zip for new installations (app + `install/`).

## Demo and tests
- `tools/demo/DemoData.php` generates a fictional foundation with three schools, ±220
  devices, users, custom fields, templates and knowledge-base articles; deterministic so
  screenshots are stable. `tools/demo/seed.php` refuses databases whose name lacks
  "demo"/"test". `DEMO_MODE` in the config shows the demo login and a banner.
- `tests/php/` — dependency-free runner: unit tests plus migration tests against a real
  MariaDB. `tests/e2e/` — Playwright against `tools/dev/stack.sh` (login, search, add
  asset, reports, all main pages, automatic migration, phone width, fresh install from the
  release zip).
