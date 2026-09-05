# Deployment and operations

## Topology

Use one HTTPS origin with its document root at `clinic-backend/public`. Laravel serves the API, sitemap, built frontend assets, permanent redirects, and frontend HTML with escaped public fallback content and per-page metadata. Keep `clinic-frontend/dist` beside the backend on the server. An Nginx/Apache static alias for built `/assets/*` may be used for efficiency. Expose `/storage/*` only for approved gallery assets. The source workspace and `.local` directory must not be web document roots.

Laravel 12 was chosen to match the available PHP 8.2 runtime. Review supported framework/runtime releases and apply security updates before production deployment. Framework references: [Laravel releases](https://laravel.com/docs/12.x/releases), [deployment](https://laravel.com/docs/12.x/deployment), [Sanctum](https://laravel.com/docs/12.x/sanctum).

Install locked dependencies with `composer install --no-dev --optimize-autoloader` and `npm ci`. Build using `npm run build`. Use a dedicated MySQL 8 database/user and run `php artisan migrate --force`. Run seeders on first installation only, then configure content through the admin UI. Create the administrator interactively using `php artisan clinic:admin`.

## Required environment settings

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-clinic.example
FRONTEND_URL=https://your-clinic.example
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true
LOG_LEVEL=warning
DB_CONNECTION=mysql
QUEUE_CONNECTION=database
```

Set `APP_KEY` once and retain it securely with backup recovery material. Do not regenerate it during updates. Store SMTP and database passwords only in environment/secrets configuration.

Use Laravel `storage:link` for public gallery images where supported. The local API also provides a validated image-serving route, so Windows local use does not require a symlink. Keep storage permissions limited to the application user. Restrict upload request size to 4 MB plus multipart overhead.

## HTTP configuration

- HTTPS with secure cookies, HSTS after verifying HTTPS, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, and `Referrer-Policy: no-referrer`.
- A production Content-Security-Policy appropriate to built assets: `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'`. Inline styles are currently used for report bars. Self-host fonts if third-party font requests are not desired.
- The API relies on same-origin cookies and CSRF. No permissive cross-origin configuration is needed. If using separate origins, configure an explicit trusted frontend/Sanctum/CORS allow-list rather than `*`.
- Redact access-log paths for `/api/public/appointments/*`, which contain bearer tokens. Never log request bodies, passwords, booking reasons, session cookies or management links. The frontend management token is held in the URL fragment rather than sent with the initial page request.
- Cache static hashed assets for a year, but do not cache admin/auth/booking-summary responses. Do not cache the SPA shell permanently across releases.
- Serve sitemap from Laravel and add its final public URL to robots.txt. Verify the Laravel-rendered public HTML and add approved structured data before SEO acceptance.

## Workers and monitoring

Run `php artisan queue:work --tries=3` under a process supervisor. Run `php artisan schedule:run` every minute. Restart workers after deployments with `php artisan queue:restart`. Monitor `/up`, failed jobs, notification errors, application errors, disk usage, and backup completion. Avoid patient identifiers in third-party monitoring tags.

## Backup and restore procedure

1. Schedule daily MySQL logical backups using a dedicated backup user and a MySQL client matching the server version. Use `mysqldump --single-transaction --routines --triggers --result-file=...` with credentials from a protected client option file, not command-line passwords.
2. Back up `storage/app/public` and the encrypted secrets/recovery material. Encrypt backups at rest, restrict access, and keep an off-host copy with clinic-approved retention.
3. Restore into a **new isolated database**. Import the SQL backup, restore public media, set the matching application key and environment, then run migrations if necessary.
4. Verify login, a known appointment, availability, public content, gallery, and notification queue without sending messages to real patients. Record restore duration and missing-data window.
5. Test recovery before launch and periodically thereafter. The local build does not provision a backup service or claim an agreed RPO/RTO.

For local SQLite, stop application writers before copying `database/database.sqlite` (and any WAL files if applicable), or use SQLite’s backup API. Never replace an active production database with a development database.
