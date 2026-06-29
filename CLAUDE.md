# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Before making any changes

```
git pull
```

Always pull before editing. The team commits frequently and merge conflicts are painful on JSX files.

## Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8+ with PDO (no framework) |
| Database | MariaDB 10+ (D:\wamp64\bin\mariadb\mariadb11.5.2\bin\mysql.exe) — database `vec_cgts` |
| Frontend | React 18 UMD + Babel standalone (JSX transpiled **in-browser**, no build step) |
| Auth | PHP session (`vec_cgts_sess`) + bcrypt |
| CSS | Custom design system in `styles.css` (oxblood maroon theme, CSS variables, light/dark) |

## Running the app

Open in browser: `http://localhost/vec.cgts/`  
There is no build step, no `npm install`, no compilation. Editing a `.jsx` or `.php` file takes effect on the next page reload.

## Database

Credentials: `config/db.php` (root / no password by default).

**Fresh install:**
```
http://localhost/vec.cgts/db/setup.php?confirm=install
```
Creates DB, all tables, seed data, bcrypt hashes. Delete `db/setup.php` afterward.

**Run a migration manually:**
```
D:\wamp64\bin\mariadb\mariadb11.5.2\bin\mysql.exe -u root vec_cgts < db/<migration>.sql
```

**Migration files** (run in order on existing installs):
```
db/schema.sql              — canonical CREATE TABLE (used by setup.php)
db/add_*.sql               — incremental migrations (ALTER TABLE / new tables)
db/migrate_*.sql           — data migrations
```
After adding a column or table, always update `db/schema.sql` so fresh installs stay consistent.

Test accounts (password: `password`): `officer`, `dir_legal`, `dir_admin`

## JSX loading order

`index.php` loads JSX files in this fixed order via `<script type="text/babel">`:

```
data → public → admin-officer → admin-directors → admin-users →
admin-sla → admin-roles → admin-officers → admin-lookup →
admin-exec → admin-case-tasks → admin-calendar → app
```

Each file exposes its components via `Object.assign(window, { ... })` at the bottom. A component used in `app.jsx` must be defined in a file that loads **before** `app.jsx`. If you create a component that belongs conceptually to an existing file, add it there rather than creating a new file (which would also require editing `index.php`).

## Architecture

### Request flow
1. `index.php` — PHP session check → injects `window.__INITIAL_USER__`, `window.__ROLE_LABELS__`, `window.__APP_BASE__`, `window.__APP_VERSION__` into HTML
2. React `Root` (in `app.jsx`) reads `__INITIAL_USER__`; if non-null renders `AdminApp` directly (no login flash)
3. All API calls go through the `api{}` object in `data.jsx` — thin wrappers around `apiFetch()`
4. `AdminApp` loads `cases` + `officers` on mount into local state; child components receive them as props
5. `updateCase(id, patch)` → `PATCH /api/cases.php?id=` → merges server response back into state

### Role-based access

Roles (ENUM in `users.role`):

| Role | Access |
|---|---|
| `officer` / `secretary` | Only sees cases **assigned to their own officer_id** |
| `head_secretary` | Sees only **unassigned** cases; can propose assignment to dir_legal via `/api/proposals.php` |
| `dir_legal` | All cases; approves proposals from head_secretary; can manage officer/head_secretary users |
| `dir_admin`, `deputy_secretary`, `secretary` | All cases (exec dashboard view) |
| `admin` | Full access including user/officer/lookup management |

The API enforces this: `GET /api/cases.php` filters rows server-side based on `$_SESSION['role']` and the user's linked `officer_id`.

**Assigning head_secretary role:** only `admin` or `dir_legal` can set this role. `dir_legal` cannot set any other role via the API.

### Proposal workflow (head_secretary → dir_legal)
1. `head_secretary` POSTs to `/api/proposals.php` with `{ case_id, proposed_officer?, note? }` → creates a `case_task_proposals` record with `from_task_no=0`
2. `dir_legal` sees badge count in sidebar nav; views `/proposals` page
3. `dir_legal` PATCHes `/api/proposals.php?id=` with `{ action:'approve'|'change', final_officer, review_note }` → sets `assignee_id` on the case

### API conventions
- Every API file starts with `require_once __DIR__ . '/_common.php'`
- `require_auth()` → returns `['id'=>..., 'role'=>...]` or sends 401
- `require_user_manager()` → passes for `admin`, `dir_legal`, or users with `can_manage_users=1`
- `json_out($data, $code)` — sends JSON and exits; never echo before calling it
- `audit($action, $target, $detail)` — writes to `audit_log` (PDPA compliance)
- `err($msg, $code)` — sends error JSON and exits

### Frontend conventions
- `officerById(officers, id)` — always pass the `officers` array; there is no global OFFICERS constant
- `thDate(iso)` — converts `YYYY-MM-DD` → Thai Buddhist era date string
- `apiFetch(path, options)` — prepends `window.__APP_BASE__`; throws on HTTP error using the server's `error` field
- `DEFAULT_ROLE_LABELS` / `ROLE_ORDER` in `data.jsx` must include every role value in the ENUM
- CSS utility classes: `.vcenter` (flex + align-items center), `.between` (space-between), `.fade-in`, `.tnum` (tabular numbers), `.muted`, `.faint`, `.sm`, `.tiny`

### Key DB tables
- `officers` — นิติกร linked to user accounts via `users.officer_id`
- `users` — login accounts; `officer_id` FK links a user to an officer record for case-assignment filtering
- `cases` — สำนวน; `assignee_id` FK to `officers.id`
- `case_events` — timeline rows ordered by `sort_order`; `step_key` links to `sla_steps`
- `case_task_proposals` — pending assignment proposals; `from_task_no=0` means initial-assignment proposal (head_secretary flow); higher values are step-transition proposals
- `notifications` — in-app notifications; created on case assignment
- `audit_log` — every view/create/update/delete action with user_id + IP

### Impersonation
Admin can impersonate any non-admin user via `POST /api/impersonate.php`. The session stores `impersonator_id` and `impersonator_name`; `index.php` injects `is_impersonating: true` into `__INITIAL_USER__`. A banner is shown in `AdminApp`.
