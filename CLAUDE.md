# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Krayin CRM is a Laravel-based customer relationship management system built on a modular architecture using Konekt Concord. The frontend uses Vue 3 with Tailwind CSS and Vite for bundling.

## Development Commands

### Docker Development (Recommended)
```bash
# Start containers
docker-compose up -d

# Execute commands inside container
docker-compose exec crm php artisan migrate
docker-compose exec crm npm run build
docker-compose exec crm npm run dev    # Watch mode

# Build Admin panel (separate build)
cd packages/Webkul/Admin && npm run build
```

### Laravel Sail (Alternative)
```bash
vendor/bin/sail up -d
vendor/bin/sail artisan migrate
vendor/bin/sail npm run build
vendor/bin/sail artisan test
```

### Testing
```bash
# All tests (Pest framework)
php artisan test

# Single test file
php artisan test tests/Feature/ExampleTest.php

# Filter by test name
php artisan test --filter=testName
```

Tests use SQLite in-memory database. See `tests/Pest.php` for helper functions like `getDefaultAdmin()`, `actingAsSanctumAuthenticatedAdmin()`, and `makeUser()`.

### Code Formatting
```bash
# Use Duster (wraps Pint) - always use Duster, not Pint directly
vendor/bin/duster fix --dirty
```

### Static Analysis
```bash
vendor/bin/larastan analyse
```

## Architecture

### Modular Package System
Core functionality is organized as independent packages in `packages/Webkul/`:

- **Core** - Base utilities and shared functionality
- **Lead** - Lead management, pipeline stages
- **Contact** - Person/organization contacts
- **Activity** - Task tracking, communications
- **Email** - IMAP email sync, Microsoft Graph integration
- **Admin** - Admin panel (Vue 3 + Tailwind CSS, separate Vite build)
- **Attribute** - Custom attributes system
- **DataGrid** - Data grid rendering engine
- **DataTransfer** - Import/export functionality
- **Product** - Product catalog
- **Tag** - Tagging system
- **User** - User management
- **WebForm** - Public form builder
- **Automation** - Workflow automation

Each package contains Controllers, Models, Routes, Migrations, and Config in its `src/` directory.

### Frontend Architecture
Two separate Vite builds:
- Main app: `vite.config.js` at root (port 5173)
- Admin panel: `packages/Webkul/Admin/vite.config.js` (port 5174)

### Key Patterns
- Repository pattern for data access (`app/Repositories/`)
- Form Request classes for validation (not inline controller validation)
- Eloquent API Resources for API responses
- Eager loading required to prevent N+1 queries
- Use `config()` helper, never `env()` directly in application code

## Laravel 10 Specifics

- Middleware registered in `app/Http/Kernel.php`
- Exception handling in `app/Exceptions/Handler.php`
- Model casts use `protected $casts = []` property (not `casts()` method)

## Testing Requirements

- All changes must be tested with Pest
- Use factories when creating models in tests
- Tests should cover happy paths, failure paths, and edge cases
- Run minimal tests with `--filter` before running full suite

## API Authentication

- API keys via `X-API-KEY` header
- Keycloak OAuth2/SSO support
- Laravel Sanctum for token authentication

## Local Development URLs

- CRM: `crm.local.privatescan.nl`
- SSO: `sso.local.privatescan.nl`
- Admin: `http://localhost/admin/login` (admin@example.com / admin123)

## Key Configuration Files

- `config/concord.php` - Package registration
- `phpunit.xml` - Test configuration (SQLite in-memory)
- `.env` - Environment variables (copy from `.env.example`)
