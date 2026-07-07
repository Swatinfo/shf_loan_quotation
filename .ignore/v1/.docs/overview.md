# Project Overview

## Project Name & Purpose

**Shreenathji Home Finance (SHF)** -- a bilingual (English/Gujarati) loan management platform for the Indian financial services market. The platform generates comparison PDFs across multiple banks showing EMI calculations, processing charges, and required documents. It includes a full loan task management system with an 11-stage workflow (inquiry through disbursement + OTC clearance), document collection, stage assignments/transfers, two-way queries, notifications, disbursement tracking, and lifecycle timeline.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP ^8.2 |
| Database | SQLite |
| Frontend | Blade + Bootstrap 5.3 + jQuery 3.7 (local vendor files, no build step) |
| Auth | Laravel Breeze (session-based) |
| PDF | Chrome headless (any OS) / mPDF fallback / PDF microservice fallback |
| Testing | PHPUnit ^11.5 |
| Formatting | Laravel Pint ^1.24 |
| Impersonation | lab404/laravel-impersonate ^1.7 |
| Dev Tools | Laravel Boost ^2.2, Laravel Pail ^1.2, IDE Helper ^3.7 |

## Architecture Summary

The application follows the standard Laravel MVC pattern augmented with a services layer for business logic. There is no frontend build step -- all CSS and JavaScript are served as static vendor files from `public/`. Custom styles use the `shf-` prefix and live in `public/css/shf.css`. Custom JavaScript lives in `public/js/shf-app.js` (core) and `public/js/shf-loans.js` (loan module).

Configuration is managed through a `ConfigService` that reads from an `app_config` database table with fallback defaults in `config/app-defaults.php`. The permission system uses a 3-tier resolution: super_admin bypass, user-level grants/denials, and role-based defaults (5-minute cache TTL per user/role).

## Directory Structure

```
app/                  # Application code (Controllers, Models, Services, Middleware)
  Http/
    Controllers/      # 18 web controllers + 3 API controllers + Auth controllers
    Middleware/        # EnsureUserIsActive, permission checks
    Requests/         # Form requests (LoginRequest)
  Models/             # 29 Eloquent models
  Services/           # 13 service classes (business logic layer)
bootstrap/            # Framework bootstrap
config/               # Configuration files (app.php, app-defaults.php, permissions.php)
database/             # Migrations, seeders, factories, SQLite database
public/               # Public assets (CSS, JS, fonts, images -- no build step)
  css/                # shf.css (all custom styles)
  js/                 # shf-app.js, shf-loans.js, vendor JS
resources/
  views/              # Blade templates (@extends/@section pattern)
    layouts/          # App and guest layouts
    auth/             # Authentication views
routes/               # Route definitions (web.php, auth.php, api.php)
storage/              # Logs, cache, generated files
tests/                # PHPUnit test suites
```

## Key Packages

### Production Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `laravel/framework` | ^12.0 | Core framework |
| `laravel/tinker` | ^2.10.1 | REPL for debugging |
| `lab404/laravel-impersonate` | ^1.7 | User impersonation (super_admin or env-flagged) |
| `mpdf/mpdf` | ^8.2 | PDF generation fallback |

### Development Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `laravel/breeze` | ^2.3 | Authentication scaffolding |
| `laravel/boost` | ^2.2 | Development tools and MCP server |
| `laravel/pint` | ^1.24 | Code formatting (PSR-12) |
| `laravel/pail` | ^1.2.2 | Real-time log viewer |
| `laravel/sail` | ^1.41 | Docker development environment |
| `barryvdh/laravel-ide-helper` | ^3.7 | IDE autocompletion helpers |
| `phpunit/phpunit` | ^11.5.3 | Testing framework |
| `mockery/mockery` | ^1.6 | Test mocking |
| `fakerphp/faker` | ^1.23 | Test data generation |
| `nunomaduro/collision` | ^8.6 | CLI error reporting |

## Application Configuration

Key settings in `config/app.php`:

- **Version tracking**: `shf_version` env variable for cache-busting and release tracking
- **PDF generation**: Configurable Chrome path, PDF microservice URL/key, toggle between Chrome headless and microservice
- **Impersonation**: `allow_impersonate_all` flag (false in production, enables dev/testing access)
- **Timezone**: Configurable via `APP_TIMEZONE` env (defaults to UTC)
- **Locale**: Defaults to `en` with `en` fallback
