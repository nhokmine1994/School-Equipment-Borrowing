# AGENTS.md — SEB Project Context for OpenCode
# Trường THCS Lộc An — Hệ thống Mượn/Trả Thiết Bị (SEB v2)

## 📍 PROJECT OVERVIEW

- **Domain (production)**: https://seb.io.vn
- **Stack**: React 18 (Vite 5) + PHP 8.2 + SQL Server (sqlsrv driver)
- **Server**: XAMPP + PHP built-in server (`php -S 0.0.0.0:8080 -t . index.php`)
- **DB**: SQL Server Express, database name = `SEB`
- **Architecture**: React SPA frontend + PHP REST API + SQL Server backend

## 🏗 ARCHITECTURE

```
SEB/
├── index.php          # MAIN ROUTER: /api/* → API, /assets/* → dist/, .php → direct exec, else → React SPA
├── connect.php        # DB config (sqlsrv_connect, env vars SEB_DB_SERVER/NAME/USER/PASSWORD)
├── .htaccess          # Apache rewrite rules
├── api/seb_api.php    # REST API (login, devices, borrows, rooms, news, subjects, register)
├── Page/              # PHP server pages
│   ├── admin_*.php    # Admin management pages (9 pages, nav 8 tabs)
│   ├── tai-khoan.php  # Login handler (POST, session + password_verify)
│   ├── logout.php     # Logout
│   └── kho_*.php, news.php, about.php, etc. (legacy PHP pages — still work)
├── components/        # PHP shared helpers
│   ├── admin_layout.php  # Admin head/nav/footer shell
│   ├── seb_db.php       # DB helpers (devices map, phiếu muon, kho ca nhan, categories)
│   └── category_helper.php
├── src/               # REACT SOURCE (edit here, run npm run build)
│   ├── main.jsx       # Entry point (React 18 + Redux + BrowserRouter)
│   ├── App.jsx        # Root (Redux + auth check from PHP session)
│   ├── routes/AppRoutes.jsx  # 12 routes: /, /devices, /rooms, /personal, /news, /about, /login, /dashboard, /borrow, /profile, /ve-chung-toi, /ve-du-an
│   ├── pages/         # 12 JSX pages (HomePage, DevicesPage, RoomsPage, PersonalPage, NewsPage, AboutPage, TeamPage, ProjectPage, LoginPage, DashboardPage, BorrowPage, ProfilePage)
│   ├── components/legacy/  # LegacyPageShell (Header + Nav + Footer shared by all pages)
│   ├── services/api.js     # Fetch wrapper → api/seb_api.php
│   ├── store/         # Redux Toolkit (appSlice: authStatus, user)
│   └── utils/         # assetPath, formatters
├── CSS/               # Legacy CSS shared by React + PHP (main.css, kho.css, etc)
├── dist/              # BUILD OUTPUT (npm run build) — browsers load from here
├── Images/            # Static images (logo, device photos, sponsor logos, news images)
├── Javascript/        # Legacy vanilla JS (kho_thiet_bi.js, Java.js, seb_api.js, device_modal.js)
├── database/db_patch_v2.sql  # SQL migration patch (idempotent, add IDActive/Active/BoMon/ThongBao etc)
├── docs/              # Deploy guides (DI_CHUYEN_DU_AN.md, CAU_HINH_DOMAIN_seb_io_vn.md)
├── vendor/            # Composer (PhpMailer)
├── data/              # JSON fallbacks (news.json, team.json, devices.json)
└── Page/*.html        # OLD static pages (# LEGACY — do NOT delete, some may still be referenced)
```

## 🗄 DATABASE SCHEMA (SQL Server)

### Tables (dbo.)
| Table | Purpose | Key columns |
|---|---|---|
| TaiKhoan | User accounts | TaiKhoan (PK), MatKhau (bcrypt), LoaiTaiKhoan (admin/user/pending/rejected), BoMon |
| Kho | Device inventory | MaThietBi (PK), TenThietBi, MaDanhMuc, SoLuong, TinhTrang, HinhAnh, IDActive (2=active, 1=liquidated) |
| PhieuMuon | Borrow requests | SoPhieuMuon (PK), MaThietBi, TaiKhoan, SoLuong, TinhTrangMuon (nvarchar: pending/Đã duyệt/returned/rejected), HanTra |
| DangKyPhong | Room bookings | MaDatCho (PK), Username, LoaiPhong, SoPhong, DuLieuCa (JSON slots), TrangThai |
| KhoCaNhan | Personal device list | TaiKhoan + MaThietBi (composite key) |
| BaoTriThongBao | Maintenance announcements | MaBaoTri (PK), TrangThai (1=visible/0=hidden), ThuTuHienThi |
| ThongBao | News articles | MaThongBao, LoaiThongBao (CHECK: Bổ sung/Sửa chữa/Cập nhật/Bảo trì), NoiDung, TrangThai (Hiển thị/Ẩn) |
| ThongBaoAdmin | Admin notifications feed | LoaiThongBao (room/borrow/device/user), TrangThai (bit) |
| DanhMuc | Device categories | MaDanhMuc (DM001-DM009), TenDanhMuc |
| TinhTrang | Device status lookup | TinhTrang (nvarchar: Sẵn sàng/Đã mượn/Bảo trì) — FK from Kho |
| TinhTrangDuyet | Borrow status lookup | ID, TenTT (pending=3, approved=1, rejected=2, returned=4) |
| Active | Device activity status | IDActive (1=thanh lý, 2=đang dùng) |
| BoMon | Subject lookup | 16 Tiểu học subjects |

### Important schema notes
- `Kho` has NO `ID` column — PK is `MaThietBi` (nvarchar). Use `MaThietBi` for joins/updates.
- `PhieuMuon` has NO numeric status column — status is TEXT in `TinhTrangMuon` nvarchar.
- `PhieuMuon` has NO `IDThietBi` column — use `MaThietBi`.
- `Kho.TinhTrang` has FK constraint to `TinhTrang` table — only allow values: 'Sẵn sàng', 'Đã mượn', 'Bảo trì'.
- `ThongBao.LoaiThongBao` has CHECK constraint — only: 'Bổ sung', 'Sửa chữa', 'Cập nhật', 'Bảo trì'.
- `DangKyPhong.TrangThai` is nvarchar — 'Chờ duyệt', 'Đã duyệt', 'Đã từ chối', 'cancelled'.
- `KhoCaNhan` — device column is `MaThietBi` (varchar), resolved by `seb_resolve_kho_ca_nhan_device_column()`.

## 🔧 KEY TECHNICAL DECISIONS (for future debugging)

### 1. Router (index.php)
- `index.php` at project root is the MAIN ROUTER. It handles:
  - `/api/seb_api.php` → requires api/seb_api.php directly
  - `/assets/*` → serves from `dist/assets/` with correct MIME types (hardcoded MIME map — do NOT use `mime_content_type()` as it returns wrong types for CSS!)
  - `/CSS/*`, `/Javascript/*`, `/Images/*`, `/data/*`, `/Audio/*`, `/video/*` → direct file serving
  - `.php` files → `require` directly with `chdir(dirname($relativePhp))` for correct relative include paths
  - `/index.php` → renders React SPA from `dist/index.html` OR falls back to PHP page
  - Everything else → React SPA (dist/index.html)

### 2. React + PHP integration
- React calls PHP API via `src/services/api.js` → `api/seb_api.php`
- Auth uses PHP `$_SESSION` (not JWT/tokens) — React checks via `GET /api/?action=current_user`
- React Router handles SPA routing client-side; PHP router serves `dist/index.html` for all SPA paths
- Legacy PHP pages (Page/*.php) still work alongside React — both share same DB

### 3. CSS loading in React
- `src/main.jsx` statically imports: `index.css`, `CSS/main.css`, `CSS/kho.css`, `CSS/kho-pages.css`, `CSS/dang-ky-phong.css`, `CSS/news.css`
- `src/components/legacy/useLegacyCss.js` dynamically loads additional CSS + Font Awesome CDN
- `LegacyPageShell` wraps every page: Header + Nav + Footer + children
- All React pages use same CSS class names as legacy PHP (equipment-card, kho-app, sidebar, etc.)

### 4. Borrow flow (critical — transaction-safe)
```
User clicks "Mượn" → API borrow_create → INSERT PhieuMuon (status=pending) → Khong trừ kho
Admin approves → UPDATE TinhTrangMuon='approved' + TRỪ Kho.SoLuong (transaction)
Admin marks returned → UPDATE TinhTrangMuon='returned' + CỘNG lại Kho.SoLuong
Admin rejects → UPDATE TinhTrangMuon='rejected' — no stock change
```
- Stock deduction happens ONLY on approve (pending→approved transition)
- Stock restore happens ONLY on return (approved→returned transition)
- Double-approve or double-return is idempotent (won't double-deduct/restore)
- Guard: if device is liquidated (IDActive=1), borrow/add blocked

### 5. Device status values (MUST match TinhTrang lookup table)
Allowed values for `Kho.TinhTrang`: 'Sẵn sàng', 'Đã mượn', 'Bảo trì'
- 'Đang bảo trì', 'Hết', 'Vô hiệu hóa' etc. will cause FK constraint errors!
- Status change via toggle_active uses `Kho.IDActive` (1/2), NOT `Kho.TinhTrang`

### 6. News (ThongBao)
- Category CHECK constraint: only 'Bổ sung', 'Sửa chữa', 'Cập nhật', 'Bảo trì'
- React NewsPage maps to: bao-tri, su-kien, thong-bao, thiet-bi-moi
- Detail view renders paragraphs from NoiDung split by \n\n

### 7. Known quirks
- `sp_XemKho` returns: MaThietBi, TenThietBi, MaDanhMuc, SoLuong, TinhTrang, HinhAnh, ThongTin, PhuKien (NO IDThietBi/ID columns!)
- `seb_fetch_devices_map()` filters out IDActive=1 (liquidated) devices from all public flows
- `admin_thiet_bi.php` is ~484KB HTML output because each device embeds full edit form (heavy by design)
- Camera feature requires HTTPS (getUserMedia blocked on HTTP non-localhost)
- `resolveSafeReturnUrl()` in Page/tai-khoan.php only accepts RELATIVE paths (no scheme/host)

## 🚀 HOW TO RUN

### Development (quick test)
```bash
cd <SEB project root>
php -S localhost:8080 -t . index.php
# Open http://localhost:8080
```

### Build React (after code changes in src/)
```bash
npm install
npm run build
# dist/ will be updated — browser loads dist/index.html via index.php router
```

### Admin login
```
URL: http://seb.io.vn/Page/admin_login.php
Default: admin / admin (CHANGE PRODUCTION PASSWORD!)
```

## 📁 IMPORTANT FILES TO KNOW
- `index.php` — main router, handles asset MIME, API routing, SPA fallback, PHP direct exec
- `api/seb_api.php` — all REST API endpoints
- `components/seb_db.php` — all DB helper functions (schema-tolerant queries)
- `components/admin_layout.php` — admin shell (nav 8 tabs: panel/devices/users/borrows/rooms/maintenance/stats/news)
- `Page/admin_auth.php` — require_admin(), generate_csrf_token(), validate_and_upload_file()
- `Page/tai-khoan.php` — login endpoint POST, session setup, redirect with return URL
- `Page/kho-ca-nhan.php` — personal kho page (protected: requires login)
- `src/pages/HomePage.jsx` — homepage with stats, recent borrows (real data from API), maintenance popup, camera modal
- `src/pages/NewsPage.jsx` — news list + detail view (newspaper style, no popup)
- `vite.config.js` — base:'./' (relative asset paths), cssCodeSplit false

## ⚠️ THINGS THAT WILL BREAK IF YOU DON'T KNOW
1. `Kho` table has NO `ID` column — joins/updates must use `MaThietBi`
2. `PhieuMuon` has NO numeric status column — all status is TEXT in `TinhTrangMuon`
3. `Kho.TinhTrang` FK constraint — never insert/update with values not in TinhTrang table
4. `minmax()` fuzzy column resolution caused duplicate-column SQL errors — fixed by strict mode in `seb_pick_column_name()` (components/seb_db.php)
5. `admin_borrow_set_status()` reads status BEFORE update to avoid double-deduct (admin_borrows.php)
6. PHP BOM (`\xEF\xBB\xBF`) at file head breaks headers/output — ensure files are UTF-8 without BOM
7. PHP `<?php/**` without CRLF after open tag = file treated as plain text (NOT parsed as PHP!)

## 📦 DEPLOYMENT CHECKLIST (for seb.io.vn)
1. Restore DB from .bak backup
2. Run database/db_patch_v2.sql (idempotent schema patch)
3. Copy SEB folder to htdocs (exclude node_modules, .git, dist_bak)
4. npm install && npm run build
5. Apache vhost → ServerName seb.io.vn → DocumentRoot = SEB folder
6. Enable php短 Extension: sqlsrv_82_ts_x64.dll + pdo_sqlsrv
7. DNS A record → server IP
8. HTTPS ON (Cloudflare Tunnel recommended — camera needs it!)
9. Change admin password from default (admin/admin)
