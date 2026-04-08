# Project Context

## Domain
Shreenathji Home Finance (SHF) — a bilingual loan management platform for the Indian financial services market. Generates comparison PDFs across multiple banks showing EMI calculations, processing charges, and required documents. Includes a full **loan task management system** with 10-stage workflow (inquiry → disbursement), document collection, stage assignments/transfers, two-way queries, notifications, disbursement tracking, and lifecycle timeline.

## Business Logic
- **Customer types**: proprietor, partnership_llp, pvt_ltd, salaried, all — each has different document requirements
- **Indian currency**: `₹ X,XX,XXX` format (Indian comma system: lakh/crore via `NumberToWordsService`)
- **Bilingual**: All user-facing content in English + Gujarati (documents, labels, PDF content)
- **EMI calculation**: Standard reducing balance formula across multiple banks and tenures
- **Activity logging**: `ActivityLog::log($action, $subject, $properties)` for all user-facing actions

## Brand
- **Colors**: Dark gray `#3a3536`, Accent orange `#f15a29`, Light gray `#f8f8f8`
- **Fonts**: Jost (display), Archivo (body) — local woff2 files in `shf.css`
- **CSS prefix**: `shf-` for all custom classes

## Loan Task System
- **10 main stages**: Inquiry → Document Selection → Document Collection → Parallel (CIBIL/Legal/Valuation/Title Search) → Rate & PF → Sanction → Docket → KFS → E-Sign → Disbursement
- **28 Eloquent models** (11 original + 17 loan system)
- **13 services** (5 original + 8 loan system)
- **18 web controllers** + 3 API controllers
- Auto-assignment by role/bank/branch/product, manual transfer with history
- Two-way query system blocks stage completion until resolved
- In-app notifications with bell badge and 60s polling
- Impersonation: `lab404/laravel-impersonate` (super_admin or env-flagged admins)

## Permission System
- 34 permissions in 6 groups (Settings, Quotations, Users, Loans, System)
- 3-level resolution: super_admin bypass → user_permissions grant/deny → role_permissions default
- Middleware: `permission:slug_name` on routes, `active` on all web routes
- Cache: 5-minute TTL per user/role

## Config System
- `ConfigService` reads from `app_config` table with `config/app-defaults.php` fallback
- Never hardcode app settings — always use ConfigService
- AppConfig model uses key-value pattern: `config_key` + `config_json`
