# SEB Dependency Map

This map records observed dependencies and hidden coupling. It does not propose a redesign.

## Request Dependency Graph

```text
Apache/.htaccess
  -> index.php
      -> dist/index.html
      -> api/seb_api.php
      -> Page/*.php
      -> CSS/Javascript/Images/data

React src/main.jsx
  -> App.jsx
      -> AppRoutes.jsx
      -> store/store.js
      -> services/api.js
      -> components/legacy/LegacyPageShell.jsx

React services/api.js
  -> /api/seb_api.php?action=...

Legacy Javascript/seb_api.js
  -> /api/seb_api.php?action=...

Page/admin_*.php
  -> connect.php
  -> Page/admin_auth.php
  -> components/admin_layout.php
  -> components/seb_db.php
  -> SQL Server
```

## PHP Include Dependencies

| Caller | Direct dependency | Hidden dependency |
|---|---|---|
| `index.php` | `connect.php`, `components/seb_db.php`, `dist/index.html` | current working directory and build artifact |
| `api/seb_api.php` | `connect.php`, `components/seb_db.php` | active PHP session, SQL schema introspection |
| `Page/admin_users.php` | `connect.php`, `admin_auth.php`, `components/seb_db.php`, `components/admin_layout.php` | CSRF session, `BoMon` lookup, notification table |
| `Page/admin_thiet_bi.php` | `connect.php`, `admin_auth.php`, `category_helper.php`, `admin_layout.php` | `sp_XemKho`, `Kho` schema, upload directory |
| `Page/admin_borrows.php` | `connect.php`, `admin_auth.php`, `seb_db.php`, `admin_layout.php` | numeric/text status columns and `TinhTrangDuyet` |
| `Page/admin_login.php` | `connect.php`, local duplicate auth functions, `admin_layout.php` | session state and password format |
| legacy `Page/*.php` | relative `connect.php` and shared Java/CSS | router `chdir()` behavior |

## Function and Module Ownership

### Authentication

- `api/seb_api.php::login_user` handles JSON login.
- `Page/tai-khoan.php` handles legacy form login.
- `Page/admin_login.php` handles admin login and duplicates auth helpers.
- `Page/admin_auth.php::require_admin` protects admin pages.
- `components/seb_db.php::seb_require_login_json` protects API actions.
- `src/App.jsx` synchronizes server session into Redux.
- `src/components/RequireAuth.jsx` protects React routes client-side.

### Devices

- `components/seb_db.php::seb_fetch_devices_map` is the main public device map.
- `components/seb_db.php::seb_map_kho_row_to_device` normalizes database rows.
- `Page/admin_thiet_bi.php` owns admin device CRUD and uploads.
- `src/pages/DevicesPage.jsx` owns the React device UI.
- `Javascript/kho_thiet_bi.js` and `Javascript/device_modal.js` own legacy device UI.

### Borrowing

- `api/seb_api.php::borrow_request` parses and authorizes requests.
- `components/seb_db.php::seb_create_borrow_request` inserts pending requests.
- `Page/admin_borrows.php::admin_borrow_set_status` owns admin status transitions and inventory changes.
- `src/services/api.js` and `Javascript/seb_api.js` are parallel clients.
- `src/pages/BorrowPage.jsx` and `Javascript/kho_thiet_bi.js` are parallel borrowing UIs.

## Database Dependencies

### Tables

`TaiKhoan`, `Kho`, `PhieuMuon`, `KhoCaNhan`, `DangKyPhong`, `ThongBao`, `ThongBaoAdmin`, `BaoTriThongBao`, `BoMon`, `DanhMuc`, `TinhTrang`, `TinhTrangDuyet`, and `Active` are referenced by application code.

### Stored procedures

- `sp_XemKho`: device list and admin device loading.
- `sp_XemKhoCaNhan`: personal inventory loading.
- `sp_ThemKhoCaNhan`: adding a device to personal inventory.
- `sp_MuonThietBi`: legacy borrow helper/reference.
- `sp_ThongKeTongQuan`: dashboard statistics.
- `sp_DuyetMuon` and `sp_TraThietBi`: referenced by comments/expected schema, while current admin approval code performs direct SQL.

Definitions and contracts are external to this repository.

## Hidden Dependencies

- `index.php` calls `chdir(dirname($relativePhp))` before requiring legacy files.
- Relative asset paths in legacy HTML depend on the current URL depth.
- React production runtime depends on `dist/` being rebuilt.
- API behavior depends on whether `PhieuMuon` has numeric status, text status, or both.
- Device behavior depends on stored procedure result column names.
- Session behavior depends on PHP session files and shared same-origin cookies.
- Fallback arrays in `src/services/api.js` can make failed backend calls appear successful.
- Admin notifications are side effects of CRUD/status operations and may fail independently of the main operation.

## Duplicate Logic / Shotgun Change Candidates

- Login/password verification exists in three PHP files.
- Logout exists in API, legacy login, and session helper paths.
- Device status normalization exists in shared helpers, homepage, admin pages, and legacy JS.
- Borrow status aliases exist in shared helpers and admin page-local functions.
- Dashboard and inventory statistics are calculated in multiple PHP and React locations.
- React and legacy clients duplicate API request and UI behavior.

## Circular Dependency Assessment

No direct class-level circular dependency was found. The main cycle-like coupling is operational:

```text
index.php router
  -> legacy PHP page relative includes
  -> shared helpers
  -> assumptions about router working directory
  -> index.php route behavior
```

This is not a formal import cycle, but changing router execution context can break many pages at once.
