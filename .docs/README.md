# Documentation Index

All documentation for the SHF (Shreenathji Home Finance) loan management platform.

## Core Reference (`.claude/`)

| File | Description |
|------|-------------|
| [database-schema.md](../.claude/database-schema.md) | Complete table schemas, columns, constraints, indexes |
| [routes-reference.md](../.claude/routes-reference.md) | All routes with HTTP methods, controllers, permissions |
| [services-reference.md](../.claude/services-reference.md) | All 13 services with methods, validation, business logic |

## Feature Documentation (`.docs/`)

| File | Description |
|------|-------------|
| [quotations.md](quotations.md) | Quotation creation, bank comparison, PDF generation flow |
| [pdf-generation.md](pdf-generation.md) | PDF rendering: Chrome headless, microservice, HTML template |
| [loans.md](loans.md) | Loan CRUD, statuses, visibility, conversion from quotation |
| [workflow-guide.md](workflow-guide.md) | Loan workflow stages (user-facing explanation) |
| [workflow-developer.md](workflow-developer.md) | Workflow internals: stage transitions, parallel processing, auto-assignment |
| [settings.md](settings.md) | ConfigService, settings tabs, app_config table |
| [permissions.md](permissions.md) | 3-tier permission system, 47 slugs, 8 groups |
| [roles.md](roles.md) | 7 unified roles, can_be_advisor, system roles |
| [users.md](users.md) | User CRUD, branches, bank assignments, impersonation |
| [user-assignment.md](user-assignment.md) | Auto-assignment logic, ProductStageUser, findBestAssignee |
| [models.md](models.md) | All 33 Eloquent models with relationships, scopes, methods |
| [database.md](database.md) | Database overview, migration strategy, audit columns |
| [frontend.md](frontend.md) | CSS design system, JS namespaces, responsive patterns |
| [views.md](views.md) | Blade view architecture, layouts, partials, stacks |
| [dashboard.md](dashboard.md) | Dashboard tabs, stat cards, default tab selection |
| [api.md](api.md) | Public and authenticated API endpoints |
| [authentication.md](authentication.md) | Login, password reset, active user enforcement |
| [general-tasks.md](general-tasks.md) | Personal/delegated task system |
| [dvr.md](dvr.md) | Daily Visit Reports, follow-up tracking, visit chains |
| [activity-log.md](activity-log.md) | Activity logging system |
| [offline-pwa.md](offline-pwa.md) | IndexedDB, service worker, offline sync |
