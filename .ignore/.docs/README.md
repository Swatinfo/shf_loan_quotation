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
| [users.md](users.md) | User CRUD, role hierarchy, activation toggle, permission overrides |
| [quotations.md](quotations.md) | Quotation creation, validation, bank/EMI processing, database persistence |
| [pdf-generation.md](pdf-generation.md) | PDF rendering pipeline, Chrome headless, microservice fallback, HTML template |
| [dashboard.md](dashboard.md) | Dashboard stats, DataTables AJAX, activity log, filtering |
| [settings.md](settings.md) | App configuration, all settings sections, defaults, reset |
| [api.md](api.md) | Public API, config endpoint, notes API, sync API |
| [models.md](models.md) | All Eloquent models, relationships, casts, accessors, custom methods |
| [database.md](database.md) | Full schema reference, migrations, indexes, constraints |
| [frontend.md](frontend.md) | Design system, CSS classes, JS modules, Bootstrap integration |
| [offline-pwa.md](offline-pwa.md) | PWA setup, service worker, IndexedDB, offline sync queue |
| [views.md](views.md) | All Blade views, layouts, sections, partials, UI patterns |
| [workflow-guide.md](workflow-guide.md) | **End-user guide**: Complete loan lifecycle from quotation to completion |
| [workflow-developer.md](workflow-developer.md) | **Developer reference**: Stage system, services, auto-assignment, phase flows |

---

## How to Use

- **Before editing a feature**: Read the relevant document to understand the full scope
- **Before creating new code**: Check if existing patterns/utilities already handle it
- **After making changes**: Update the relevant document if the change affects documented behavior
