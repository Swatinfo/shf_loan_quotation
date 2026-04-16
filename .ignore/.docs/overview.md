# Project Overview

## What It Does

Shreenathji Home Finance (SHF) is a bilingual (English/Gujarati) loan management platform. It generates comparison PDF documents for loan proposals across multiple banks, calculates EMIs, compares charges, and lists required documents per customer type. It also includes a full **loan task management system** with 10-stage workflow, document collection, stage assignments/transfers, notifications, disbursement tracking, and a lifecycle timeline.

## Tech Stack

| Layer | Technology | Details |
|-------|-----------|---------|
| Backend | Laravel 12, PHP 8.4 | MVC with service layer |
| Database | SQLite | Single file, session/cache/queue also DB-driven |
| Frontend | Blade + Bootstrap 5.3 + jQuery 3.7 | Local vendor files, no build step |
| Auth | Laravel Breeze | Session-based, registration disabled |
| PDF | Chrome headless (any OS) / Microservice fallback | Three-tier: env flag → Chrome → microservice |
| Offline | PWA + Service Worker + IndexedDB | Full offline quotation creation + sync |
| Testing | PHPUnit 11 | Feature + unit tests |
| Formatting | Laravel Pint | Auto-formatting on dirty files |

## Architecture

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── ConfigApiController.php    # Public config endpoint
│   │   │   ├── NotesApiController.php     # Additional notes CRUD
│   │   │   └── SyncApiController.php      # Offline sync endpoint
│   │   ├── Auth/                          # Breeze auth controllers (8 files)
│   │   ├── DashboardController.php        # Dashboard + DataTables + activity log
│   │   ├── QuotationController.php        # Quotation CRUD + PDF download
│   │   ├── LoanController.php            # Loan CRUD + status + timeline
│   │   ├── LoanConversionController.php   # Quotation → Loan conversion
│   │   ├── LoanStageController.php        # Stage workflow (status, assign, transfer, query)
│   │   ├── LoanDocumentController.php     # Document collection tracking
│   │   ├── LoanDisbursementController.php # Disbursement processing
│   │   ├── LoanValuationController.php    # Property valuation
│   │   ├── LoanRemarkController.php       # Loan remarks
│   │   ├── LoanSettingsController.php     # Loan settings (banks, branches, user roles)
│   │   ├── WorkflowConfigController.php   # Workflow config (products, stages, branches)
│   │   ├── NotificationController.php     # In-app notifications
│   │   ├── ImpersonateController.php      # User impersonation search
│   │   ├── UserController.php             # User CRUD + toggle active
│   │   ├── PermissionController.php       # Permission matrix management
│   │   ├── SettingsController.php         # Quotation settings (8 update methods)
│   │   └── ProfileController.php          # User profile edit/delete
│   └── Middleware/
│       ├── CheckPermission.php            # Route-level permission check
│       └── EnsureUserIsActive.php         # Block inactive users
├── Models/                                # 28 Eloquent models
├── Traits/
│   └── HasAuditColumns.php               # Auto-fill updated_by/deleted_by
├── Services/
│   ├── QuotationService.php               # Quotation business logic
│   ├── PdfGenerationService.php           # HTML rendering + PDF conversion
│   ├── ConfigService.php                  # DB-backed config with defaults
│   ├── PermissionService.php              # 3-tier permission resolution
│   ├── NumberToWordsService.php           # Indian numbering (EN/GU)
│   ├── LoanStageService.php              # Workflow engine (stages, assignment, transfer)
│   ├── LoanConversionService.php         # Quotation-to-loan conversion
│   ├── LoanDocumentService.php           # Document collection management
│   ├── StageQueryService.php             # Two-way query system
│   ├── NotificationService.php           # In-app notification delivery
│   ├── RemarkService.php                 # Loan remarks
│   ├── DisbursementService.php           # Disbursement processing + OTC
│   └── LoanTimelineService.php           # Lifecycle timeline builder
```

## Key Design Decisions

1. **Service Layer Pattern**: Controllers are thin; business logic lives in Services
2. **No Build Step**: All CSS/JS served directly from `public/` — no Vite, Webpack, or npm build
3. **Bilingual Throughout**: Every user-facing string has English + Gujarati variants
4. **Indian Formatting**: Currency uses `₹ X,XX,XXX` format; numbers use Crore/Lakh system
5. **Offline-First PWA**: Full quotation creation works offline via IndexedDB + Service Worker
6. **Config in DB**: App settings stored in `app_config` table with `config/app-defaults.php` fallback
7. **Permission System**: 3-tier resolution (super_admin bypass → user override → role default) with 5-min cache

## Directory Quick Reference

| Path | Purpose |
|------|---------|
| `public/css/shf.css` | Main design system CSS |
| `public/js/shf-app.js` | Core jQuery utilities |
| `public/js/pdf-renderer.js` | Client-side PDF rendering |
| `public/js/offline-manager.js` | IndexedDB sync manager |
| `public/js/config-loader.js` | Async config fetcher |
| `public/js/config-defaults.js` | Hardcoded default config |
| `public/js/config-translations.js` | Bilingual translations |
| `public/js/shf-loans.js` | Loan module interactions |
| `public/js/password-ui.js` | Password strength UI |
| `config/permissions.php` | Permission definitions + role defaults |
| `config/app-defaults.php` | Default app settings (company, banks, docs, etc.) |
| `storage/app/pdfs/` | Generated PDF storage |
| `sw.js` | Service Worker (project root) |
| `manifest.json` | PWA manifest (project root) |
| `legacy/` | Old PHP code — reference only, do not modify |

## Environment Variables

| Variable | Purpose | Required |
|----------|---------|----------|
| `CHROME_PATH` | Explicit Chrome binary path for PDF generation | No (auto-detected on Windows) |
| `PDF_USE_MICROSERVICE` | Force microservice for PDF generation | No (default: false) |
| `PDF_SERVICE_URL` | URL of PDF microservice | Only if using microservice |
| `PDF_SERVICE_KEY` | API key for PDF microservice | Only if using microservice |
| `ALLOW_IMPERSONATE_ALL` | Allow all admins to impersonate users | No (default: false) |

## Customer Types

| Key | English | Gujarati |
|-----|---------|----------|
| `proprietor` | Proprietor | માલિકી |
| `partnership_llp` | Partnership / LLP | ભાગીદારી / LLP |
| `pvt_ltd` | Private Limited | પ્રાઇવેટ લિમિટેડ |
| `salaried` | Salaried | પગારદાર |
| `all` | All Types Combined | તમામ પ્રકાર |

Each customer type has its own set of required documents (bilingual) defined in `config/app-defaults.php`.
