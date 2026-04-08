# MANDATORY Pre-Read Before Writing Any Code

Before generating ANY code, read the relevant reference files. **Do NOT guess or improvise.**

| What you're working on | Read FIRST |
|------------------------|-----------|
| Quotation creation/editing | `.docs/quotations.md` + `.claude/services-reference.md` |
| PDF generation/template | `.docs/pdf-generation.md` + `.claude/services-reference.md` |
| Permission changes | `.docs/permissions.md` + `.claude/database-schema.md` |
| Settings/Config tabs | `.docs/settings.md` |
| Frontend (CSS/JS) | `.docs/frontend.md` + `.docs/views.md` |
| Dashboard/DataTables | `.docs/dashboard.md` |
| API endpoints | `.docs/api.md` |
| DB/Models/Migrations | `.claude/database-schema.md` + `.docs/models.md` + `.docs/database.md` |
| Routes/Controllers | `.claude/routes-reference.md` |
| Services/Validation | `.claude/services-reference.md` |
| Offline/PWA/ServiceWorker | `.docs/offline-pwa.md` |
| User management | `.docs/users.md` + `.docs/permissions.md` |
| Authentication/Login | `.docs/authentication.md` |
| Any Blade view edit | `.docs/views.md` + `.docs/frontend.md` |
| Loan CRUD/management | `.docs/views.md` + `.claude/services-reference.md` + `.claude/routes-reference.md` |
| Loan stages/workflow | `.claude/services-reference.md` + `.claude/database-schema.md` + `.docs/models.md` |
| Loan documents | `.claude/services-reference.md` + `.docs/models.md` |
| Loan settings/workflow config | `.docs/settings.md` + `.claude/routes-reference.md` |
| Notifications | `.claude/services-reference.md` + `.docs/models.md` |
| Disbursement | `.claude/services-reference.md` + `.docs/models.md` |
| Impersonation | `.docs/users.md` |

## Always Read (every task)
1. `tasks/lessons.md` — past corrections to avoid repeating
2. `tasks/todo.md` — current task state

## Source of Truth Files
- `public/css/shf.css` — ALL custom CSS classes (`shf-*` prefix)
- `public/js/shf-app.js` — Core custom JS
- `public/js/shf-loans.js` — Loan module JS
- `config/app-defaults.php` — Default config values
- `config/permissions.php` — Permission definitions and role defaults

**NEVER create new patterns when existing ones exist. NEVER use Tailwind classes. ALWAYS check `.docs/frontend.md` before building any UI component.**
