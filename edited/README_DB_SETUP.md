# Admin DB Setup (Stores, Workers, Projects, Notifications)

Follow these steps to enable database-backed admin modules.

## 1) Configure DB connection
Edit `edited/php/db.php` defaults or use environment variables before starting PHP:

```powershell
# PowerShell example (temporary for session)
$env:DB_HOST = 'localhost'
$env:DB_NAME = 'edge_trading'   # change if different
$env:DB_USER = 'root'
$env:DB_PASS = ''
```

## 2) Apply migrations
Run the SQL files in `edited/migrations/` against your MySQL database, at minimum:
- `010_create_core_admin_tables.sql` (Stores, Workers, Projects, Notifications)
- Any existing appointments migrations (already in repo)

You can run them via your MySQL client, phpMyAdmin, or terminal.

## 3) Use the simple Admin API (optional convenience)
We added a small session-protected endpoint `edited/php/admin_api.php` you can call from your admin pages to list/create records.

- Auth: Requires `$_SESSION['admin_logged']` (already set after admin login).
- Endpoints (method + query):
  - GET  `admin_api.php?entity=stores`
  - POST `admin_api.php?entity=stores`           body: { name, location?, manager?, status? }
  - GET  `admin_api.php?entity=workers`
  - POST `admin_api.php?entity=workers`          body: { name, role?, store_id?, contact?, status?, start_date?, probation_end?, avatar_url? }
  - GET  `admin_api.php?entity=projects`
  - POST `admin_api.php?entity=projects`         body: { name, store_id?, owner?, due_date?, status? }
  - GET  `admin_api.php?entity=notifications`
  - POST `admin_api.php?entity=notifications`    body: { title, body?, type? }

Notes:
- This is a minimal helper. For updates/deletes, add PUT/DELETE handlers similarly.
- If you prefer direct PHP rendering (no JS fetch), integrate queries directly in your `store.html`/`workers.html`/`projects.html` by converting them to `.php` and using `php/db.php` to fetch and print rows.

## 4) Wiring pages (two options)
- Option A (JS fetch): Convert your admin pages to call `admin_api.php` for live data rendering.
- Option B (direct PHP): Convert the HTML pages to `.php` and query with mysqli to render tables server-side.

We can wire either approach for Stores, Workers, Projects, and Notifications on request.
