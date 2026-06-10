# ระบบรับเรื่องร้องเรียน–ร้องทุกข์ สอศ.

PHP 8+ / MariaDB 10+ / React 18 (Babel standalone, no build step)

## Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8+ with PDO (no framework) |
| Database | MariaDB 10+ — database `vec_cgts` |
| Frontend | React 18 UMD + Babel standalone (JSX in browser) |
| Auth | PHP session (`vec_cgts_sess`) + bcrypt passwords |
| CSS | Custom design system in `styles.css` (oxblood maroon theme, CSS variables, light/dark) |

## Entry points

- `index.php` — SPA shell; injects `window.__INITIAL_USER__` from session
- `api/auth.php` — GET/POST/DELETE (me / login / logout)
- `api/cases.php` — GET list, GET ?id=, POST create, PATCH ?id= update
- `api/officers.php` — GET list with live workload count
- `api/upload.php` — POST multipart file upload

## First-time setup

```
http://localhost/vec.cgts/db/setup.php?confirm=install
```

Creates DB, all tables, seed data, and correct bcrypt hashes. **Delete `db/setup.php` after running.**

Test accounts (password: `password`):
- `officer` — เจ้าหน้าที่นิติการ
- `dir_legal` — ผอ.กลุ่มนิติการ
- `dir_admin` — ผอ.สำนักอำนวยการ

## Key files

```
config/db.php          DB credentials (edit this)
db/schema.sql          CREATE TABLE statements
db/seed.sql            Sample data (8 cases + officers + users)
api/_common.php        Shared: PDO bootstrap, session, json_out(), require_auth(), audit()
data.jsx               Icons, constants (STATUS/TRACKS/CLASS), api{} client, helpers
public.jsx             Public pages: home, complaint form (4 steps), status tracking
admin-officer.jsx      Officer dashboard, case list, case detail, assign modal, import form
admin-directors.jsx    Director dashboards + charts (grouped bars, donut)
app.jsx                Theme hook, login form, AdminApp shell, VaultPage, ReportCenter, Root
uploads/.htaccess      Deny direct access to uploaded files
```

## Data flow

1. `index.php` checks `$_SESSION['user_id']` → serialises user JSON into `window.__INITIAL_USER__`
2. React `App` reads that value; if non-null, renders AdminApp directly (no login flash)
3. All data fetches go through `api{}` object in `data.jsx` (thin `fetch` wrappers)
4. `AdminApp` calls `api.getCases()` + `api.getOfficers()` on mount; stores in local state
5. `updateCase(id, patch)` calls `PATCH /api/cases.php?id=` and merges the server response back into state

## DB schema (summary)

- `officers` — นิติกร (id = "o1"…"o4", matches React components)
- `users` — login accounts (role: officer / dir_legal / dir_admin)
- `cases` — สำนวน (FK to officers.assignee_id)
- `case_events` — timeline rows (sort_order, ev_status: done/active/pending)
- `case_files` — attachments (stored_name = random hex on disk)
- `audit_log` — PDPA access log

## Dev notes

- `officerById(officers, id)` — always pass the `officers` array (no global OFFICERS anymore)
- `thDate(iso)` converts `YYYY-MM-DD` → Thai Buddhist date string
- Dark mode: `document.documentElement.setAttribute("data-theme", "dark")` — CSS vars switch automatically
- The old `ระบบรับเรื่องร้องเรียน สอศ.html` is the original prototype; `index.php` is the PHP app entry
