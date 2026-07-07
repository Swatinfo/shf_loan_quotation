# Project Context

## Domain
Shreenathji Home Finance (SHF) — a bilingual loan management platform for the Indian financial services market. Generates comparison PDFs across multiple banks showing EMI calculations, processing charges, and required documents. Includes a full **loan task management system** with 11-stage workflow (inquiry → disbursement + OTC), document collection, stage assignments/transfers, two-way queries, notifications, disbursement tracking, and lifecycle timeline.

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
- **11 main stages**: Inquiry → Document Selection → Document Collection → Parallel (App Number/BSM-OSV/Legal/Technical Valuation/Property Valuation) → Rate & PF → Sanction → Docket → KFS → E-Sign → Disbursement → OTC Clearance
- **29 Eloquent models** (11 original + 17 loan system + Role)
- **13 services** (5 original + 8 loan system)
- **18 web controllers** + 3 API controllers
- Auto-assignment by role/bank/branch/product, manual transfer with history
- Two-way query system blocks stage completion until resolved
- In-app notifications with bell badge and 60s polling
- Impersonation: `lab404/laravel-impersonate` (super_admin or env-flagged admins)

## Unified Role System (7 Roles)
- **super_admin**: Full system access, bypasses all permissions
- **admin**: System administration, settings, user management
- **branch_manager**: Branch-level management, quotations, loan stages
- **bdo**: Business Development Officer (same access as Branch Manager)
- **loan_advisor**: Quotation creation, loan processing stages
- **bank_employee**: Bank-side loan processing only
- **office_employee**: Office operations, valuations, docket review, OTC
- Roles managed via `roles` table + `role_user` pivot (users can have multiple roles)
- No separate "system role" vs "task role" — all unified

## Permission System
- 48 permissions in 6 groups (Settings, Quotations, Users, Loans, Customers, System)
- 3-tier resolution: super_admin bypass → user_permissions grant/deny → role_permission (any role grants)
- Middleware: `permission:slug_name` on routes, `active` on all web routes
- Cache: 5-minute TTL per user/role

## Config System
- `ConfigService` reads from `app_config` table with `config/app-defaults.php` fallback
- Never hardcode app settings — always use ConfigService
- AppConfig model uses key-value pattern: `config_key` + `config_json`
