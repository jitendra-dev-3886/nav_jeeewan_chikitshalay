# Local validation — 5 September 2026

- Frontend TypeScript check and Vite production build: passed.
- Frontend lint: passed without warnings after the final fixes.
- Backend feature suite: **23 tests, 113 assertions passed on SQLite**.
- Same backend feature suite: **23 tests, 113 assertions passed on MySQL**.
- Concurrent booking test: **8 independent requests, exactly 1 successful booking and 7 conflicts**, verified on both databases using isolated test data.
- Chrome desktop/mobile smoke checks: public pages, available-slot progression, accessible field labels, no horizontal overflow, staff session login, staff screen loading, real HTTP CSRF rejection, and no uncaught browser exceptions.
- Production frontend is built into `clinic-frontend/dist` and served by Laravel. Public fallback content and page metadata are also included in the HTML response.
- PowerShell startup/shutdown scripts: parsed without syntax errors.

The browser smoke suite does not submit real patient bookings. Booking creation, cancellation, rescheduling, state transitions, privacy, capacity, uploads, redirects, publication, notifications, and permissions are covered by the API suite. External SMTP/SMS/WhatsApp delivery, other browser engines, a full screen-reader/contrast audit, load targets, production monitoring, and backup restoration need validation in the launch environment.

Tests use `nav_jeevan_clinic_feature_test` and `nav_jeevan_clinic_concurrency_test` on MySQL. The running clinic uses `nav_jeevan_clinic`; feature test transactions do not affect it.

Run the MySQL suite from `clinic-backend`:

```powershell
php vendor/phpunit/phpunit/phpunit -c phpunit.mysql.xml
```

Use that test configuration only with the explicitly named disposable test database. Never point it at clinic data.
