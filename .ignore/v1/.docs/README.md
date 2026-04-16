# Project Documentation Index

## Shreenathji Home Finance - Loan Management Platform

This `.docs` folder contains comprehensive documentation for every module and feature of the application. Use this as the primary reference when building, editing, or debugging any part of the system.

---

## Document Index

| Document | Description |
|----------|-------------|
| [overview.md](overview.md) | Project overview, tech stack, architecture, directory structure |
| [authentication.md](authentication.md) | Auth system, login/logout, password reset, session management |
| [permissions.md](permissions.md) | Role-based access control, permission resolution, middleware, caching |
| [users.md](users.md) | User CRUD, role management, activation, impersonation, branch/bank/location assignment |
| [quotations.md](quotations.md) | Quotation creation, validation, bank/EMI processing, database persistence |
| [pdf-generation.md](pdf-generation.md) | PDF rendering pipeline, Chrome headless, microservice fallback, HTML template |
| [dashboard.md](dashboard.md) | Dashboard stats, DataTables AJAX, activity log, filtering |
| [settings.md](settings.md) | App configuration, quotation settings, loan settings, workflow config, ConfigService |
| [api.md](api.md) | Public API, config endpoint, notes API, sync API |
| [models.md](models.md) | All 30 Eloquent models, relationships, casts, accessors, constants |
| [database.md](database.md) | Entity-relationship overview, FK chains, indexes, audit columns, SQLite notes |
| [frontend.md](frontend.md) | Design system, CSS classes, JS modules, Bootstrap integration, responsive patterns |
| [views.md](views.md) | All Blade views, layouts, sections, partials, UI patterns |
| [offline-pwa.md](offline-pwa.md) | PWA setup, service worker, IndexedDB, offline sync queue |
| [workflow-guide.md](workflow-guide.md) | **End-user guide**: Complete loan lifecycle from quotation to completion |
| [workflow-developer.md](workflow-developer.md) | **Developer reference**: Stage system, services, auto-assignment, phase flows |

## Reference Files (`.claude/`)

| File | Description |
|------|-------------|
| [database-schema.md](../.claude/database-schema.md) | Full per-table schema reference (columns, types, FKs, defaults) |
| [routes-reference.md](../.claude/routes-reference.md) | Complete route definitions with middleware and permissions |
| [services-reference.md](../.claude/services-reference.md) | All 13 services with method signatures, validation rules, business logic |

---

## How to Use

- **Before editing a feature**: Read the relevant document to understand the full scope
- **Before creating new code**: Check if existing patterns/utilities already handle it
- **After making changes**: Update the relevant document if the change affects documented behavior
