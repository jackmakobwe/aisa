# Aisa — Personal Guidance & Support Platform

A bilingual (English / Kiswahili) website where people share personal concerns privately and get help in two layers:

1. **Immediate AI-supported guidance** from Aisa's AI assistant (clearly labelled as an AI).
2. **"Talk to Aisa"**: an optional personal reply from a human, drafted with AI help but always read and approved by the responder.

No account is needed. People return with a long private ticket code. A private dashboard lets Aisa and trained backup responders manage the queue, review AI drafts, reply, and handle safety flags.

> **Status: development build, not launch-ready.** Crisis contacts are not verified, legal pages are drafts, and the Kiswahili wording needs native-speaker review. See [docs/launch-blockers.md](docs/launch-blockers.md). In production the conversation intake stays closed until `php artisan aisa:launch-check` passes.

## Stack

PHP 8.2+ · Laravel 12 · MySQL/MariaDB · Blade + Tailwind CSS 4 + Alpine.js (CSP build) + Lucide icons · Vite · official Anthropic PHP SDK · PHPUnit · Laravel Pint.

## Quick start (local)

Requirements: PHP 8.2+ with `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `sodium`; Composer 2; Node 20+; MySQL 8 or MariaDB 10.4+.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan aisa:pepper              # copy the printed line into .env

# create the database (adjust user/password to your setup)
mysql -u root -e "CREATE DATABASE aisa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

php artisan migrate --seed           # fictional development data; prints a dev admin password
npm run build                        # or: npm run dev (hot reload)
php artisan serve                    # http://localhost:8000
```

- Public site: <http://localhost:8000>
- Dashboard: <http://localhost:8000/admin> (sign in with the printed `admin@aisa.test` password, then set up an authenticator app; two-factor sign-in is mandatory).

To create real accounts instead of using the seeder: `php artisan aisa:create-admin` (or `--role=responder`).

On Windows with npm ≥ 11 you may see `allow-scripts` warnings for esbuild. The build still works; run `npm approve-scripts esbuild` if Vite cannot find its binary.

## Testing without an API key

`AISA_AI_PROVIDER=mock` (the default in `.env.example`) uses a **development mock** that never calls a model. Its replies start with *"[Simulated reply for development — not a real AI response]"*, are stored with `is_mock = true`, and are shown with a "Simulated" badge. Every page also shows a development banner. The app refuses to start the mock when `APP_ENV=production`.

## Using a real AI model

Set in `.env`:

```dotenv
AISA_AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-ant-...
AISA_AI_MODEL=claude-opus-5-5
```

Details, cost controls and data terms are in [docs/ai-provider.md](docs/ai-provider.md). Calls are only made from the server (`app/Services/Ai`), and the key never reaches the browser.

## Running the tests

```bash
php artisan test            # 152 tests: public flow, codes, safety, admin, security
vendor/bin/pint --test      # code style
```

Tests use SQLite in memory and a scriptable fake AI provider. See [docs/test-report.md](docs/test-report.md).

## Useful commands

| Command | What it does |
|---|---|
| `php artisan aisa:safety-eval [--rules-only] [--only=id,id]` | Runs the fictional safety test set through the rules and the configured AI; fails on any miss |
| `php artisan aisa:launch-check` | Lists launch blockers; exits non-zero until all pass |
| `php artisan aisa:create-admin [--role=responder]` | Creates a dashboard account (the only way to create the first admin) |
| `php artisan aisa:prune` | Deletes conversations past the retention period (scheduled daily at 02:30) |
| `php artisan aisa:pepper` | Generates `AISA_TICKET_PEPPER` |
| `php artisan schedule:work` | Runs the scheduler locally |

## Documentation

| Document | Contents |
|---|---|
| [docs/architecture.md](docs/architecture.md) | Architecture, folder structure, request flows, ER diagram |
| [docs/ai-provider.md](docs/ai-provider.md) | AI configuration, prompts, safety design, cost control |
| [docs/safety-testing.md](docs/safety-testing.md) | Safety layers, test set, how to test a real model before launch |
| [docs/privacy-and-retention.md](docs/privacy-and-retention.md) | What is stored, encryption, retention, deletion, Quick exit limits |
| [docs/security-checklist.md](docs/security-checklist.md) | Security controls and their status |
| [docs/deployment.md](docs/deployment.md) | Production deployment, HTTPS, backups, monitoring |
| [docs/test-report.md](docs/test-report.md) | What the automated tests cover and the latest result |
| [docs/launch-blockers.md](docs/launch-blockers.md) | Unfinished features, open decisions and launch blockers |
| [PROJECT_CHECKLIST.md](PROJECT_CHECKLIST.md) | Implementation checklist against the project document |
