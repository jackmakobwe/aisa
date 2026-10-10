# Deployment guide

A single Linux server or managed platform (for example a VPS with Nginx + PHP-FPM + MySQL, or Laravel Forge or Cloud) is enough for the pilot. Choose the hosting region with legal advice (data protection and cross-border transfer).

## 1. Server requirements

- PHP 8.2+ (FPM) with `pdo_mysql`, `mbstring`, `openssl`, `sodium`, `intl`, `curl`, `xml`, `zip`
- MySQL 8 or MariaDB 10.6+ with encryption at rest (disk or volume encryption)
- Nginx or Apache with TLS (for example Let's Encrypt), HTTP→HTTPS redirect
- Node 20+ to build assets (or build in CI and ship `public/build`)
- A real mail service (SMTP or API) for safety alerts and password resets
- Cron for the Laravel scheduler

## 2. First deploy

```bash
git clone <repo> /var/www/aisa && cd /var/www/aisa
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate          # back up APP_KEY securely and separately from DB backups
php artisan aisa:pepper           # put the line into .env; never change it afterwards
php artisan migrate --force
php artisan aisa:create-admin     # the only way to create the first account
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Point the web root at `public/`. Make `storage/` and `bootstrap/cache/` writable by the PHP user only.

## 3. Production `.env`

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_DRIVER=aisa-database
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=...  DB_DATABASE=aisa  DB_USERNAME=aisa  DB_PASSWORD=<strong, unique>

MAIL_MAILER=smtp      # not "log"
MAIL_FROM_ADDRESS=no-reply@your-domain.example
AISA_SAFETY_ALERT_EMAILS=aisa@your-domain.example,backup@your-domain.example

AISA_AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=<key>
AISA_AI_DAILY_REQUEST_CAP=<from budget>
AISA_RESPONSE_WINDOW_HOURS=<agreed>
AISA_RETENTION_DAYS=<agreed with legal>
AISA_MINOR_POLICY=<approved policy>
AISA_ASSISTANT_NAME=<confirmed name>
TRUSTED_PROXIES=<load balancer IPs, if any>
```

Use a dedicated business email and domain, not personal addresses (project document, section 17).

## 4. Scheduler

```cron
* * * * * cd /var/www/aisa && php artisan schedule:run >> /dev/null 2>&1
```

This runs `aisa:prune` daily (retention). Drafts are generated after the response is sent, so no queue worker is required. If you later move work to queues, run `php artisan queue:work` under a process supervisor.

## 5. Backups and restore

- Daily encrypted database backups (for example `mysqldump --single-transaction | gzip | gpg`), kept off-server with a defined retention.
- Back up `APP_KEY` and `AISA_TICKET_PEPPER` separately, in a password manager or secrets vault. Without `APP_KEY`, encrypted conversations cannot be read; with it plus a backup, they can, so protect both.
- **Test a restore before launch** into a staging environment, and check that conversations decrypt and codes work.
- Backups must respect deletions: document how long backups are kept, and say so in the privacy policy after legal review.

## 6. Monitoring

- Uptime check on `/up` (Laravel's health route).
- Error alerts from `storage/logs` (for example a log shipper, or Sentry configured to send no request bodies).
- Watch **Overview → failed safety alerts** daily; failed alerts are recorded in `notification_deliveries`.
- AI spend alerts in the provider console, plus `AISA_AI_DAILY_REQUEST_CAP`.
- Security updates: `composer audit`, `npm audit`, OS patches.

## 7. Before opening to the public

Run `php artisan aisa:launch-check`. In production, `/start` redirects to "Aisa is not open yet" until every check passes. The checklist covers: verified crisis contacts in at least four categories (emergency included), a real AI provider, a real alert mailbox, at least one responder with 2FA, and the six recorded approvals (legal, under-18 policy, Kiswahili review, safety tests, backup responder, assistant name). Record approvals under **Settings & launch** only after they genuinely happen.

## 8. Updates

```bash
php artisan down --render="errors::503"
git pull && composer install --no-dev --optimize-autoloader && npm ci && npm run build
php artisan migrate --force && php artisan optimize
php artisan up
```

Keep a separate staging environment with its own database and keys.
