# AGENTS.md

This file provides guidance to Codex when working with this repository.

---

# Project Overview

Krayin CRM is a Laravel-based CRM built on a modular architecture using Konekt Concord.
Frontend uses Vue 3, Tailwind CSS and Vite.
This codebase contains PrivateScan-specific customizations.

---

# Development Commands

## Docker Development (Preferred)

```bash
docker-compose up -d
docker-compose exec crm php artisan migrate

# Frontend build (Admin is de enige frontend build)
./build.sh              # production build
./build.sh local        # alleen npm install, geen build

# Of direct:
cd packages/Webkul/Admin && npm install && npm run build
cd packages/Webkul/Admin && npm run dev    # Watch mode
```

### Laravel Sail (Alternative)
```bash
vendor/bin/sail up -d
vendor/bin/sail artisan migrate
vendor/bin/sail artisan test
```

### Testing
```bash
# All tests (Pest framework)
sail artisan test

# Single test file
sail artisan test tests/Feature/ExampleTest.php

# Filter by test name
sail artisan test --filter=testName
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
Admin UI is volledig Vue 3: `createApp` mount over de hele pagina (`#app`). Interactie bouw je als
Vue-component: `<script type="text/x-template" id="v-...-template">` + `app.component('v-...')` binnen
`@pushOnce('scripts')`. Geen plain JS/Blade-interactie of Alpine binnen `#app`.

Er is **één** Vite build: `packages/Webkul/Admin/vite.config.js` (port 5174).
Output gaat naar `public/admin/build/`.

De root heeft geen `package.json` en geen Vite config — die zijn verwijderd omdat
er niets naar bouwde (`config/krayin-vite.php` heeft alleen de `admin` viter actief,
en geen enkele blade gebruikt `@vite`). `./build.sh` bouwt alleen de Admin package.

`resources/css/email-templates.css` wordt níét gebouwd; die wordt direct van disk
gelezen via `resource_path()` in `EmailTemplateRenderingService`.

### Key Patterns
- Repository pattern for data access (`app/Repositories/`)
- Form Request classes for validation (not inline controller validation)
- Eloquent API Resources for API responses
- Eager loading required to prevent N+1 queries
- Use `config()` helper, never `env()` directly in application code

## Laravel Specifics

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
- Admin: `https://crm.local.privatescan.nl/admin/login` (admin@example.com / admin123)

## Key Configuration Files

- `config/concord.php` - Package registration
- `phpunit.xml` - Test configuration (SQLite in-memory)
- `.env` - Environment variables (copy from `.env.example`)

<!-- mb-foundation:start (managed by install.sh, do not edit) -->
# MB Engineering Foundation

Universal guardrails for Laravel projects. Project-specific instructions above take precedence.

## Default: Fast Mode

- Implement directly. Read only the files the change touches; no broad codebase exploration.
- Reuse existing patterns, helpers, components and naming before writing new code.
- No new abstractions, dependencies or refactoring unless the task requires it.
- Smallest change that fully solves the task. Do not touch unrelated code.
- No ADRs, plans, summaries or docs for simple changes. Report briefly what changed.
- Ask only when blocked on a real decision; otherwise pick the conventional option.

## Always

- Business logic outside controllers: controllers validate, authorize, delegate, respond.
- Validate all input at the boundary (Form Requests or equivalent).
- Authorize every action that reads or changes data (policies/gates); never trust IDs from the client.
- Never commit secrets; no secrets in code, logs or responses.
- Write or update focused tests for changed behavior; run only the relevant tests while developing.
- Run the project's formatter/linter on changed files if one is configured.
- Frontend: use the stack the project already has. Blade first; Alpine.js (if present) or plain JS for
  interaction; Vue only when truly needed and already in the project. Never add Vue or Alpine yourself.

## Architecture Mode

Switch only for structural or risky changes: new modules or bounded contexts, public API contract
changes, data model changes affecting multiple modules, auth/tenancy changes, irreversible data
migrations, or new infrastructure. Then read `.mb-foundation/skills/architecture/SKILL.md`, state the trade-off in a
few lines and get confirmation before implementing. Otherwise stay in Fast Mode.

## Skills (read only when relevant)

| Skill | Read when |
|---|---|
| `.mb-foundation/skills/laravel/SKILL.md` | Backend work: Eloquent, services, jobs/queues, migrations, tests |
| `.mb-foundation/skills/frontend/SKILL.md` | Blade views, Alpine.js, JavaScript, Vue, forms, UX |
| `.mb-foundation/skills/architecture/SKILL.md` | Architecture Mode, module boundaries, API contracts |
| `.mb-foundation/skills/security/SKILL.md` | Auth, permissions, tenancy, file uploads, raw SQL, bulk/destructive data operations |

## Laravel Boost MCP (if available)

Use it targeted, not by default on every task:

- `search-docs` when unsure about version-specific framework or package APIs.
- Schema/routes/config tools instead of guessing table columns or route names.
- `tinker`/database query tools for quick verification; read-only unless asked.
- Logs/last-error when debugging a failure.

Skip Boost when the answer is already in the files you are editing.
<!-- mb-foundation:end -->
