# Shreenathji Home Finance — Loan Proposal Generator

Bilingual (English/Gujarati) loan quotation system. Generates comparison PDFs across banks with EMI calculations, charges, and required documents.

## MANDATORY: Context Files

### On Session Start
**Before doing ANY work on ANY prompt**, you MUST read ALL of these files:

1. **`tasks/lessons.md`** — past mistakes and patterns. Apply these to avoid repeating errors.
2. **`tasks/todo.md`** — current task state. Resume incomplete tasks or note completed context.
3. **`.claude/database-schema.md`** — all table schemas, columns, types, and permissions matrix.
4. **`.claude/routes-reference.md`** — all route definitions, HTTP methods, controllers, and permissions.
5. **`.claude/services-reference.md`** — all service methods, validation rules, and business logic.

These files are NOT optional. Skipping them leads to repeated mistakes, wrong assumptions about schema/routes, and lost context. Read them silently — no need to summarize unless the user asks.

### Before Each New Task (even mid-session)
**Re-read the relevant files** before starting each new task or switching context:
- Touching DB/models/migrations? → Re-read `.claude/database-schema.md`
- Touching routes/controllers/middleware? → Re-read `.claude/routes-reference.md`
- Touching services/validation/business logic? → Re-read `.claude/services-reference.md`
- Starting any task? → Re-read `tasks/todo.md` and `tasks/lessons.md`

In long sessions, these files may have been updated by earlier work. Always re-read before acting — never rely on stale memory from earlier in the conversation.

### Keep Files in Sync (after every change)
- Update `tasks/todo.md` with plan/progress for the current task
- Update `tasks/lessons.md` after ANY user correction or discovered pattern
- Update `.claude/database-schema.md` when changing tables, columns, or permissions
- Update `.claude/routes-reference.md` when adding/changing routes or middleware
- Update `.claude/services-reference.md` when modifying service methods or validation rules

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.4, SQLite |
| Frontend | Blade + Bootstrap 5.3 + jQuery 3.7 (local vendor files, no build step) |
| Auth | Laravel Breeze (session-based) |
| PDF | Chrome headless (Windows) / PDF microservice (Linux) |
| Testing | PHPUnit 11 |
| Formatting | Laravel Pint |

## Architecture

```
app/
├── Http/Controllers/       # Thin controllers, logic in services
│   ├── Api/                # ConfigApiController, NotesApiController, SyncApiController
│   ├── Auth/               # Breeze auth controllers
│   ├── DashboardController # Quotation list + activity log
│   ├── QuotationController # CRUD + PDF download
│   ├── UserController      # User CRUD + toggle active
│   ├── PermissionController# Permission matrix management
│   └── SettingsController  # App config (company, banks, tenures, docs, charges)
├── Models/                 # Eloquent models (see Models section)
├── Services/               # Business logic layer (see Services section)
└── Http/Middleware/         # CheckPermission, EnsureUserIsActive
```

## Models & Relationships

```
User ──┬── hasMany → UserPermission
       ├── hasMany → Quotation
       ├── hasMany → createdUsers (self-ref)
       └── belongsTo → creator (self-ref)

Quotation ──┬── belongsTo → User
            ├── hasMany → QuotationBank
            └── hasMany → QuotationDocument

QuotationBank ── hasMany → QuotationEmi

Permission ──┬── hasMany → RolePermission
             └── hasMany → UserPermission

ActivityLog ── belongsTo → User
AppConfig   ── standalone (key-value config store)
BankCharge  ── standalone (reference data)
```

### Key Model Details
- **User**: roles = `super_admin|admin|staff`, has `is_active` flag, `created_by` tracks creator
- **Quotation**: stores `customer_type` (proprietor|partnership_llp|pvt_ltd|all), `selected_tenures` as JSON, `loan_amount` as unsigned bigint
- **QuotationBank**: ROI range (min/max), all charge fields (PF, admin, stamp, notary, advocate, IOM, TC, 2 extras)
- **QuotationEmi**: table name is `quotation_emi` (not quotation_emis), tenure in years
- **AppConfig**: `config_key` + `config_json` pattern, stores company info, banks list, tenures, documents, charges, services, GST

## Services (Business Logic Layer)

| Service | Purpose |
|---------|---------|
| `QuotationService` | Validates inputs, processes bank/EMI data, generates PDF, saves to DB in transaction |
| `PdfGenerationService` | Renders bilingual HTML template, converts to PDF (OS-aware strategy) |
| `ConfigService` | Manages app_config table with defaults fallback (`config/app-defaults.php`) |
| `PermissionService` | 3-level permission resolution: SuperAdmin bypass → User override (grant/deny) → Role default. Cached 5min |
| `NumberToWordsService` | Converts numbers to English/Gujarati words (Indian numbering: Crore/Lakh) |

## Permission System

- 18 permissions in 4 groups (Settings, Quotations, Users, System)
- Defined in `config/permissions.php` with role defaults
- Resolution: super_admin always passes → check user_permissions for grant/deny override → fall back to role_permissions
- Middleware: `permission:slug_name` on routes
- Middleware: `active` (EnsureUserIsActive) appended to all web routes
- Cache: 5-minute TTL per user/role, clear via `PermissionService::clearAllCaches()`

## Frontend Design System

- **Brand colors**: Dark gray `#3a3536`, Accent orange `#f15a29`, Light gray `#f8f8f8`
- **Fonts**: Jost (display), Archivo (body) via local woff2 files (`@font-face` in `shf.css`)
- **CSS framework**: Bootstrap 5.3 (local `public/vendor/bootstrap/`)
- **JS**: jQuery 3.7 (local `public/vendor/jquery/`) + Bootstrap Bundle JS
- **CSS classes prefix**: `shf-` (e.g., `shf-section`, `shf-card`, `shf-table`, `shf-badge`, `shf-stat-card`, `shf-toast`)
- **Button classes**: `btn-accent`, `btn-accent-outline`
- **Custom CSS**: `public/css/shf.css`
- **Custom JS**: `public/js/shf-app.js`
- **Layouts**: `resources/views/layouts/app.blade.php` (auth), `guest.blade.php` (login) — use `@extends`/`@yield` pattern (not Blade components)
- **View pattern**: All views use `@extends('layouts.app')` or `@extends('layouts.guest')` with `@section('header')` and `@section('content')`
- **No Vite/build step** — all assets served locally from `public/`

## Key Routes

| Route | Controller | Permission |
|-------|-----------|------------|
| `/dashboard` | DashboardController@index | auth |
| `/quotations/create` | QuotationController@create | create_quotation |
| `POST /quotations/generate` | QuotationController@generate | generate_pdf |
| `/quotations/{id}` | QuotationController@show | auth (own or view_all) |
| `/users` | UserController (resource) | view/create/edit/delete_users |
| `/permissions` | PermissionController | manage_permissions |
| `/settings` | SettingsController | view_settings + section-specific |
| `/activity-log` | DashboardController@activityLog | view_activity_log |
| `GET /api/config/public` | ConfigApiController@public | none (public) |

## Database

- **Default**: SQLite (single file)
- **Session/Cache/Queue**: All database-driven
- **PDFs stored**: `storage/app/pdfs/`
- **Migrations**: Prefixed with `0001_01_01_*` for core tables
- **Seeders**: `PermissionSeeder` seeds all permissions + role defaults

## PDF Generation

- `QuotationController::generate()` accepts JSON POST → `QuotationService::generate()` → `PdfGenerationService::generate()`
- HTML rendered server-side with embedded base64 fonts/images
- Windows: Chrome headless via `exec()` (auto-detects Chrome path or uses `CHROME_PATH` env)
- Linux: cURL to microservice (`PDF_SERVICE_URL` + `PDF_SERVICE_KEY` env vars)
- Output: bilingual multi-page PDF with EMI comparison tables, charges table, document checklist

## Project-Specific Conventions

- **Bilingual content**: Always include both English and Gujarati where the app expects it (documents, labels, PDF content)
- **Indian currency formatting**: Use `₹ X,XX,XXX` format (Indian comma system via `NumberToWordsService`)
- **Activity logging**: Use `ActivityLog::log($action, $subject, $properties)` for all user-facing actions
- **Config management**: Never hardcode app settings — use `ConfigService` which reads from `app_config` table with `config/app-defaults.php` fallback
- **Inline validation**: Current controllers use inline validation (not Form Requests). Follow existing pattern unless refactoring
- **Customer types**: `proprietor`, `partnership_llp`, `pvt_ltd`, `all` — each has different document requirements
- **Legacy directory**: `/legacy/` contains old PHP code for reference only — do not modify

## Keeping Documentation in Sync

When you change code that affects architecture, models, routes, services, permissions, or validation rules, you **must** also update the corresponding reference files:
- `.claude/database-schema.md` — table schemas, columns, permissions matrix
- `.claude/routes-reference.md` — route definitions, methods, permissions
- `.claude/services-reference.md` — service methods, validation rules, business logic
- `tasks/lessons.md` — update after any user correction or discovered pattern
- `tasks/todo.md` — update task progress as you work

This ensures future sessions have accurate context. Do this as part of the same change, not as a separate step.

## Workflow Orchestration

### 1. Plan Mode Default
- Enter plan mode for ANY non-trivial task (3+ steps or architectural decisions)
- If something goes sideways, STOP and re-plan immediately — don't keep pushing
- Use plan mode for verification steps, not just building
- Write detailed specs upfront to reduce ambiguity

### 2. Subagent Strategy
- Use subagents liberally to keep main context window clean
- Offload research, exploration, and parallel analysis to subagents
- For complex problems, throw more compute at it via subagents
- One task per subagent for focused execution

### 3. Self-Improvement Loop
- After ANY correction from the user: update `tasks/lessons.md` with the pattern
- Write rules for yourself that prevent the same mistake
- Ruthlessly iterate on these lessons until mistake rate drops
- Review `tasks/lessons.md` at session start for relevant patterns

### 4. Verification Before Done
- Never mark a task complete without proving it works
- Diff behavior between main and your changes when relevant
- Ask yourself: "Would a staff engineer approve this?"
- Run tests, check logs, demonstrate correctness

### 5. Demand Elegance (Balanced)
- For non-trivial changes: pause and ask "is there a more elegant way?"
- If a fix feels hacky: "Knowing everything I know now, implement the elegant solution"
- Skip this for simple, obvious fixes — don't over-engineer
- Challenge your own work before presenting it

### 6. Autonomous Bug Fixing
- When given a bug report: just fix it. Don't ask for hand-holding
- Point at logs, errors, failing tests — then resolve them
- Zero context switching required from the user
- Go fix failing CI tests without being told how

## Task Management

1. **Plan First**: Write plan to `tasks/todo.md` with checkable items
2. **Verify Plan**: Check in before starting implementation
3. **Track Progress**: Mark items complete as you go
4. **Explain Changes**: High-level summary at each step
5. **Document Results**: Add review section to `tasks/todo.md`
6. **Capture Lessons**: Update `tasks/lessons.md` after corrections

## Core Principles

- **Simplicity First**: Make every change as simple as possible. Impact minimal code.
- **No Laziness**: Find root causes. No temporary fixes. Senior developer standards.
- **Minimal Impact**: Changes should only touch what's necessary. Avoid introducing bugs.

## Environment Variables (PDF-specific)

```
CHROME_PATH=       # Optional: explicit Chrome path for Windows PDF generation
PDF_SERVICE_URL=   # Linux: URL of PDF microservice
PDF_SERVICE_KEY=   # Linux: API key for PDF microservice
```

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.16
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/breeze (BREEZE) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
