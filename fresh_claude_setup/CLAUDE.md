# CLAUDE.md

## Project Overview

<!-- Replace with your project description. Keep it under 3 lines. -->
[PROJECT_NAME] — [brief description of what this project does, who it serves, and key capabilities].

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | [e.g., Laravel 12, PHP 8.4, MySQL] |
| Frontend | [e.g., Blade + Bootstrap 5.3 + jQuery 3.7] |
| Auth | [e.g., Laravel Breeze, session-based] |
| Testing | [e.g., PHPUnit 11, Laravel Pint] |
<!-- Add/remove rows as needed -->

## Development Commands

```bash
# Start dev server
[your command here]

# Run all tests
[your command here]

# Run specific test
[your command here]

# Format code
[your command here]

# Run migrations
[your command here]
```

## Key Conventions

<!-- List project-wide conventions Claude must follow. Examples: -->
- **[Convention 1]**: [e.g., All user-facing text must be bilingual]
- **[Convention 2]**: [e.g., Use ConfigService for app config, not env() directly]
- **[Convention 3]**: [e.g., CSS prefix `xyz-` for all custom classes]
- **[Convention 4]**: [e.g., Inline validation in controllers, not Form Requests]

## Roles / User Types

<!-- List all user roles or types in the system -->
- **[role_1]**: [description]
- **[role_2]**: [description]

## Mandatory Pre-Read Gate

Before writing ANY code, read the relevant docs for your task:

| Task | Read FIRST |
|------|-----------|
| [Feature area 1] | `.docs/[relevant-file].md` + `.claude/services-reference.md` |
| [Feature area 2] | `.docs/[relevant-file].md` |
| DB/Models/Migrations | `.claude/database-schema.md` + `.docs/models.md` |
| Routes/Controllers | `.claude/routes-reference.md` |
| Frontend (CSS/JS) | `.docs/frontend.md` + `.docs/views.md` |
| Permissions | `.docs/permissions.md` |
<!-- Add rows for each major feature area in your project -->

**Always read** (every task): `tasks/lessons.md` + `tasks/todo.md`

## Reference Documentation

| Area | File(s) |
|------|---------|
| All docs index | `.docs/README.md` |
| Database schema | `.claude/database-schema.md` |
| Routes & middleware | `.claude/routes-reference.md` |
| Services & validation | `.claude/services-reference.md` |
| Models & relationships | `.docs/models.md` |
<!-- Add rows as you create .docs/ files -->

## Source of Truth Files

<!-- List the canonical files for key areas -->
- `[path/to/main.css]` — All custom CSS classes
- `[path/to/main.js]` — Core custom JS
- `[path/to/config]` — Default config values

Rules auto-loaded from `.claude/rules/`: `pre-read-gate.md`, `coding-feedback.md`, `project-context.md`, `workflow.md`
