# SEB Codebase Map

Audit scope: `C:\xampp\htdocs\SEB`.
Audit mode: read-only static audit. This document describes the source as it exists, not an ideal target architecture.

## Runtime Entry Points

- `index.php`: root PHP router. It dispatches direct PHP pages, the JSON API, legacy static assets, Vite assets, and the React production shell. If `dist/index.html` exists, the root request is React-first.
- `.htaccess`: Apache rewrite layer. Existing files pass through; `/assets/*` maps to `dist/assets`; remaining application routes map to `index.php`.
- `src/main.jsx`: React development/build entry point. Mounts Redux, `BrowserRouter`, `App`, and shared CSS.
- `dist/index.html`: generated Vite production shell served by `index.php` in production.
- `api/seb_api.php`: action-based JSON API. The `action` query parameter selects authentication, devices, borrowing, rooms, personal inventory, news, subjects, team, and maintenance operations.
- `Page/*.php`: legacy public pages and administrative pages. These are executable directly and also through `index.php`.
- `Page/tai-khoan.php`: legacy form login handler.
- `Page/admin_login.php`: separate admin login handler.

## Repository Areas

### `src/`

React application source.

- `src/App.jsx`: startup authentication check and global application shell.
- `src/routes/AppRoutes.jsx`: public and authenticated route declarations.
- `src/components/RequireAuth.jsx`: client-side route guard; server/API authorization remains authoritative.
- `src/components/legacy/`: shared header, navigation, footer, shell, and dynamic legacy CSS loading.
- `src/pages/`: React pages for home, devices, rooms, personal inventory, news, login, dashboard, borrow history, profile, team, project, and about content.
- `src/services/api.js`: React fetch wrapper and fallback data behavior.
- `src/store/`: Redux Toolkit store and application/auth state.
- `src/utils/`: asset and formatting helpers.

### `api/`

- `api/seb_api.php`: session-based JSON API. It calls `connect.php`, `components/seb_db.php`, direct SQL, and stored-procedure helpers.

### `Page/`

- Public legacy pages: device inventory, room registration, personal inventory, news, about, account login, logout.
- Admin pages: dashboard, users, devices, borrows, rooms, maintenance, news, and statistics.
- `Page/admin_auth.php`: shared admin guard, CSRF helpers, upload validation, and admin notifications.
- `Page/admin_layout.php` is not the shell itself; the shared admin rendering shell is in `components/admin_layout.php`.

### `components/`

- `components/seb_db.php`: shared SQL Server helpers, schema introspection, device mapping, borrow creation/history, personal inventory, status resolution, dashboard statistics, and stored-procedure calls.
- `components/category_helper.php`: category lookup and category display normalization.
- `components/admin_layout.php`: common admin HTML shell and navigation.
- `components/session_helper.php`: session destruction/logout support.

### `Javascript/`

Legacy browser behavior.

- `seb_api.js`: legacy API wrapper.
- `Java.js`: authentication modal, navigation, protected-page UI, and public registration behavior.
- `kho_thiet_bi.js`: legacy device list/filter/borrow behavior.
- `device_modal.js`: legacy device detail and borrow modal.
- `dang-ky-phong.js`: legacy room booking behavior.
- `toast.js`: browser notifications.

### `CSS/`

Shared legacy and React-compatible styles. `main.css`, `kho.css`, `kho-pages.css`, `dang-ky-phong.css`, and `news.css` are used by React pages; other files support legacy pages. `react-layout.css` is the shared React layout override loaded after page-specific legacy CSS.

### `database/` and `sql/`

- `database/db_patch_v2.sql`: idempotent patch for selected application tables/columns. It does not define the complete base schema or all stored procedures.
- `sql/`: additional database migration/support scripts.
- Stored procedure definitions are not present in the repository; the application depends on objects already installed in SQL Server.

### `data/`

JSON fallback/content sources such as device, news, team, and related data.

### `dist/`

Generated Vite output. It is a runtime dependency because `index.php` serves it directly. It must be regenerated after React source changes.

### `docs/`

Deployment and domain configuration notes.

### `vendor/`

Composer dependencies, primarily PHPMailer.

## Business Logic Owners

| Business area | Current owner | Secondary implementations |
|---|---|---|
| Authentication | `api/seb_api.php`, `Page/tai-khoan.php`, `Page/admin_login.php` | React login, `Java.js`, admin auth |
| Device listing/status | `components/seb_db.php` | `index.php`, admin device page, legacy JS |
| Borrow request creation | `components/seb_db.php` through `api/seb_api.php` | legacy API client, React API client |
| Borrow approval/return | `Page/admin_borrows.php` | status/statistics readers in helpers/admin pages |
| Inventory quantity | SQL Server `Kho`, PHP approval flow, stored procedures | dashboard/statistics calculations |
| Room booking | `api/seb_api.php` and admin room page | React and legacy room clients |
| Personal inventory | `components/seb_db.php` and API | stored procedures, React/legacy clients |
| News | `api/seb_api.php`, `Page/admin_tin_tuc.php` | `data/news.json`, React NewsPage |
| Maintenance | `Page/admin_bao_tri.php`, API, homepage query | React HomePage |
| Admin authorization | `Page/admin_auth.php` | duplicated functions in `Page/admin_login.php` |

## Important Runtime Constraints

- PHP pages rely on `index.php` changing the working directory before `require`, so relative includes are a hidden dependency.
- Production behavior depends on the generated `dist/` directory.
- SQL Server schema and stored procedure result shapes are external dependencies.
- PHP sessions are the authentication mechanism; there is no token/JWT layer.
- `PhieuMuon` may contain both numeric and text status columns in the deployed schema; all readers/writers must keep them consistent.
