# SEB Architecture

This is the observed architecture. It is not currently a clean layered architecture, and the hybrid design is part of the deployed behavior.

## High-Level Shape

```text
Browser
  |
  +-- Apache/.htaccess or PHP built-in router
  |      |
  |      +-- index.php
  |      |     +-- dist/index.html -> React SPA
  |      |     +-- /api/seb_api.php -> JSON API
  |      |     +-- /Page/*.php -> legacy/admin PHP pages
  |      |     +-- static assets
  |      |
  |      +-- direct PHP execution for existing files
  |
  +-- React clients and legacy JavaScript clients
         |
         +-- PHP session-based JSON API
         +-- legacy form/page handlers

PHP/API/Admin pages
  |
  +-- connect.php
  +-- components/seb_db.php
  +-- direct SQL Server queries
  +-- stored procedures in external SQL Server database

SQL Server
  +-- tables, constraints, lookup tables
  +-- stored procedures not included in repository
  +-- possible triggers/external database behavior
```

## Presentation Layer

There are two active presentation systems:

1. React/Vite pages under `src/pages/`, served from generated `dist/`.
2. Legacy PHP pages under `Page/`, enhanced by files under `Javascript/` and `CSS/`.

Both systems can be reached from the same domain and both use PHP sessions. They share some CSS names and the same API, but their client-side behavior is implemented separately.

## Application/API Layer

`api/seb_api.php` is an action switch rather than a resource-oriented controller. It performs request parsing, authentication checks, validation, database calls, response shaping, and some business decisions in one file.

The primary shared business/data helper is `components/seb_db.php`. It contains schema introspection, status aliases, device mapping, borrow insertion, personal inventory calls, and statistics. Admin pages also contain direct SQL and local business logic, especially `Page/admin_borrows.php` and `Page/admin_thiet_bi.php`.

## Authentication and Authorization

- Authentication state is stored in `$_SESSION['user']`.
- React calls `current_user` during startup and mirrors the result into Redux.
- API operations use `seb_require_login_json()`.
- Admin pages use `require_admin()` and page-level CSRF validation.
- React route protection is only a client-side convenience; API authorization is the security boundary.
- Login is duplicated in the JSON API, public legacy form handler, and admin login page.

## Data Access Layer

There is a partial shared data-access layer in `components/seb_db.php`, but it is not exclusive. Direct SQL appears in:

- `api/seb_api.php`
- `index.php`
- most admin pages
- legacy PHP pages

Stored procedure calls are also mixed with direct SQL. The external procedure contracts cannot be verified from this repository.

## Database and Business Workflows

### Borrow request

```text
React/legacy client
  -> api/seb_api.php?action=borrow_request
  -> login and device/quantity checks
  -> components/seb_db.php::seb_create_borrow_request
  -> INSERT PhieuMuon with pending status
  -> no stock deduction
```

### Approval

```text
Admin form
  -> Page/admin_borrows.php
  -> update PhieuMuon status columns
  -> transaction for Kho quantity change
  -> admin notification
```

The status update and stock transaction are separate stages in the current implementation. This is an architectural risk because a stock failure can leave the status changed.

### Return

```text
Admin form
  -> Page/admin_borrows.php
  -> returned status
  -> transaction restoring Kho.SoLuong
```

## External Dependencies

- Apache `mod_rewrite` and `AllowOverride All`.
- PHP 8.2 with `sqlsrv` and `pdo_sqlsrv`.
- SQL Server database and base schema.
- Stored procedures such as `sp_XemKho`, `sp_XemKhoCaNhan`, `sp_ThemKhoCaNhan`, and `sp_ThongKeTongQuan`.
- Composer/PHPMailer for mail-related legacy features.
- Vite build output for React production behavior.

## Architectural Risks

- Two active frontends and two API clients.
- Three authentication implementations.
- Direct SQL mixed with stored procedures and schema introspection.
- Approval status and inventory updates are not fully atomic.
- Fallback data can hide API/database failures.
- Production source and `dist/` can diverge.
- Relative includes depend on router-controlled working directory.
- Database objects required by runtime are not versioned in the repository.
