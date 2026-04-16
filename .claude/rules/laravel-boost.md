# Laravel Boost Guidelines

## Project-Specific Overrides (take precedence over Boost defaults below)

- **Inline validation**: This project uses inline validation in controllers, NOT Form Request classes
- **No build step**: Frontend is local vendor files (Bootstrap 5.3 + jQuery 3.7) — no npm/Vite/build required
- **No factories/seeders**: Only create factories or seeders when explicitly asked
- **ConfigService**: Use `ConfigService` for app config, not `config()` or `env()` directly

---

<laravel-boost-guidelines>
=== foundation rules ===

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application.

### Packages & Versions
- php ^8.2, laravel/framework ^12.0, laravel/breeze ^2.3, laravel/boost ^2.2, laravel/pint ^1.24, phpunit/phpunit ^11.5.3
- lab404/laravel-impersonate ^1.7, mpdf/mpdf ^8.2, laravel/tinker ^2.10.1
- Dev: barryvdh/laravel-ide-helper ^3.7, fakerphp/faker ^1.23, laravel/pail ^1.2.2, laravel/sail ^1.41, mockery/mockery ^1.6, nunomaduro/collision ^8.6

### Conventions
- Follow existing code conventions. Check sibling files for structure, approach, and naming.
- Use descriptive names. Check for existing components before creating new ones.
- Stick to existing directory structure; only change dependencies with approval.
- Create documentation files only if explicitly requested.

=== boost rules ===

### Laravel Boost MCP Tools
- Use `list-artisan-commands` to check available Artisan command parameters
- Use `get-absolute-url` when sharing project URLs
- Use `tinker` for PHP debugging / Eloquent queries; `database-query` for read-only DB access
- Use `database-schema` to inspect table structure before writing migrations
- Use `browser-logs` for recent browser errors/exceptions
- Use `search-docs` before making code changes — passes installed package versions automatically
  - Use broad topic queries: `['rate limiting', 'routing']` (no package names in queries)
  - Syntax: word search (auto-stemming), AND logic, `"quoted phrases"`, mixed, multiple queries

=== php rules ===

### PHP
- Always use curly braces for control structures, even single-line bodies
- Use PHP 8 constructor property promotion; no empty zero-param constructors
- Always use explicit return types and type hints
- Enum keys should be TitleCase
- Prefer PHPDoc blocks over inline comments

=== laravel/core rules ===

### Laravel Core
- Use `php artisan make:` with `--no-interaction` to create files
- Prefer Eloquent relationships over raw queries; avoid `DB::`, prefer `Model::query()`
- Use eager loading to prevent N+1 problems
- Use named routes and `route()` for URL generation
- Use `config('key')` not `env('KEY')` outside config files
- Use `ShouldQueue` for time-consuming operations

### Testing
- Use factories with custom states for test models
- Use `php artisan make:test --phpunit {name}` (feature) or `--unit` (unit)
- Run minimal tests with `--filter` before finalizing

=== laravel/v12 rules ===

### Laravel 12 Structure
- Middleware configured in `bootstrap/app.php` via `Application::configure()->withMiddleware()`
- `bootstrap/providers.php` for service providers
- Console commands auto-registered from `app/Console/Commands/`
- When modifying columns in migrations, include ALL previous attributes
- Model casts use `casts()` method, not `$casts` property

=== pint/core rules ===

### Pint
- Run `vendor/bin/pint --dirty --format agent` after modifying PHP files

</laravel-boost-guidelines>
