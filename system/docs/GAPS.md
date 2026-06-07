# ShipFlow — System Gaps

A living list of features described in `MANUAL.md` that are not (yet) implemented in the codebase. Each item is sized to be a discrete ticket. Goal: drive the system to match the manual.

**How to use this file:**
- Pick the highest-severity unblocked item.
- Implement it. Update the **Status** field. Add tests where listed.
- When closing, remove the entry or move it to the bottom of this file under `## Done`.
- New gaps discovered? Add them. Don't let the manual drift again.

**Severity scale:**
- **P0** — security or data-integrity risk. Schedule this sprint.
- **P1** — operationally painful or matches a manual promise that's currently false. Schedule next sprint.
- **P2** — nice-to-have, consistency, ergonomics. Backlog.

**Status values:** `todo` · `in progress` · `blocked` · `done`

---

## Index

| # | Title | Severity | Status |
|---|-------|----------|--------|
| 1 | Register recurring tasks via Laravel scheduler | P1 | **done** |
| 2 | Enforce role-event matrix on scan API | P0 | **done** |
| 3 | Add web admin RBAC (role column + middleware) | P1 | **done** (formalized existing convention) |
| 4 | Add Sanctum token expiration | P0 | **done** |
| 5 | Centralize notification dispatch behind `NotificationService` | P2 | **done** |
| 6 | Add 2FA for system admin accounts | P1 | **done** |
| 7 | Add self-serve password reset flow | P2 | **done** (staff side; client-side follow-up) |
| 8 | Add data retention / purge jobs | P1 | **done** |
| 9 | Install Sentry + Pail for error monitoring | P1 | **done** |
| 10 | Enforce `audit_log` immutability at DB level | P1 | **done** (affordance ready; ops applies the GRANT) |
| 11 | Add CI pipeline (GitHub Actions) running phpunit | P1 | **done** |
| 12 | Clean up `config/sanctum.php` stateful domains | P2 | **done** (side effect of #4) |
| 13 | Fix `MANUAL.md` permission matrix to reflect reality | P1 | **done** (matrix now enforced; no callout needed) |
| 14 | Fix `MANUAL.md` ERD: tracking_branches is separate, not 1:1 | P2 | **done** |
| 15 | Fix `MANUAL.md` Notify reference (point at real job classes) | P2 | **done** |
| 16 | Document the rest of `app/Console/Commands/` in MANUAL.md | P2 | **done** |
| 17 | Document `cron_jobs` command purpose or remove it | P2 | **done** (removed) |
| 18 | Enforce HTTPS in production via TrustProxies + middleware | P1 | **done** |
| 19 | Add HSTS, CSP, and other security headers | P1 | **done** |
| 20 | Document `/up` health endpoint in deployment section | P2 | **done** |

---

## 1. Register recurring tasks via Laravel scheduler

**Severity:** P1
**Status:** todo

### Manual says
> "Scheduler: cron entry running `php artisan schedule:run` every minute. Drives periodic reminders, the tracking reconcile command, and any deferred cleanup." (§17 Operations playbook)

### Reality
`bootstrap/app.php` has no `->withSchedule()` registration. Every recurring task — `sourcing:remind`, `tracking:reconcile-stuck`, `sourcing:health-snapshot`, `cron_jobs` — requires a separate hand-written cron entry. Easy to miss one, hard to audit what runs when.

### What to do
In `bootstrap/app.php`, add:

```php
->withSchedule(function (Schedule $schedule) {
    $schedule->command('sourcing:remind')
        ->dailyAt('09:00')
        ->onOneServer()
        ->withoutOverlapping();

    $schedule->command('sourcing:health-snapshot')
        ->dailyAt('02:00')
        ->onOneServer()
        ->withoutOverlapping();

    $schedule->command('tracking:reconcile-stuck')
        ->everyFourHours()
        ->onOneServer()
        ->withoutOverlapping();

    // Decide whether cron_jobs (treasury save) should run on schedule —
    // see gap #17 first.
})
```

In production, set up a **single** cron entry:
```
* * * * * cd /var/www/system && php artisan schedule:run >> /dev/null 2>&1
```

### Acceptance criteria
- [ ] `bootstrap/app.php` registers the scheduler closure.
- [ ] All five existing commands either appear in the closure or have a documented reason for being manual-only.
- [ ] `php artisan schedule:list` shows them when run.
- [ ] One cron entry in production replaces the per-command ones.
- [ ] Manual §17 + §22.2 updated to reference Laravel scheduler.

### Files to touch
- `bootstrap/app.php`
- Production cron config (out of repo)
- `system/docs/MANUAL.md`

---

## 2. Enforce role-event matrix on scan API

**Severity:** P0
**Status:** todo

### Manual says
The permission matrix in §21.2 lists which event types each role can submit. E.g. only MANAGER can submit `LOST`; only MANAGER and COURIER can submit `DELIVERED_TO_CUSTOMER`.

### Reality
`app/Modules/Tracking/Http/Controllers/Employee/ScanController.php` does **not** reference role anywhere. `EnforceBranchScope` middleware only checks the `branch:N` Sanctum ability. The role stored in `branch_staff.role` is decorative — any active staff member with branch ability can submit any event type.

This is an **authorization gap**: an AUDITOR can submit `DELIVERED_TO_CUSTOMER`, falsely closing out shipments. A RECEIVER can mark items `LOST`, hiding theft.

### What to do
Two-layer approach:

1. **Add a `RoleEventPolicy` service:**

   ```php
   namespace App\Modules\Tracking\Services;

   use App\Modules\Tracking\Enums\BranchStaffRole;
   use App\Modules\Tracking\Enums\InternalEventType;

   class RoleEventPolicy
   {
       /** Single source of truth — must match MANUAL.md §21.2. */
       private const ALLOWED = [
           'MANAGER'  => ['RECEIVED_AT_HUB','IN_TRANSIT_INTERNAL','RECEIVED_AT_BRANCH',
                          'READY_FOR_PICKUP','DELIVERED_TO_CUSTOMER','RETURNED_TO_HUB',
                          'LOST','DAMAGED'],
           'RECEIVER' => ['RECEIVED_AT_HUB','IN_TRANSIT_INTERNAL','RECEIVED_AT_BRANCH',
                          'READY_FOR_PICKUP','RETURNED_TO_HUB','DAMAGED'],
           'COURIER'  => ['DELIVERED_TO_CUSTOMER','RETURNED_TO_HUB'],
           'AUDITOR'  => [],
       ];

       public function allows(BranchStaffRole $role, InternalEventType $event): bool
       {
           return in_array($event->value, self::ALLOWED[$role->value] ?? [], true);
       }
   }
   ```

2. **Use it in `ScanController::resolve` and `::submit`:**

   - `resolve` — filter `allowed_event_types` by the staff member's role on the active branch.
   - `submit` — re-check before insert; abort 403 `role_action_denied` if the event isn't in the allowed list.

3. **Add tests:**

   - One test per (role, event) cell of the matrix in §21.2. 32 small tests, mostly identical structure.

### Acceptance criteria
- [ ] `RoleEventPolicy` exists with the matrix as a class constant.
- [ ] `ScanController::resolve` filters allowed events by role.
- [ ] `ScanController::submit` rejects with 403 if the role/event combination is denied.
- [ ] Mobile app's `scan_review_screen.dart` already grays out forbidden actions (it already trusts the server's `allowed_event_types`, so no app change needed if the server filters correctly).
- [ ] Tests for every cell in the 4×8 matrix pass.
- [ ] If the manual's matrix needs to change, the constant in `RoleEventPolicy` and the manual change together in one PR.

### Files to touch
- `app/Modules/Tracking/Services/RoleEventPolicy.php` (new)
- `app/Modules/Tracking/Http/Controllers/Employee/ScanController.php`
- `tests/Feature/Tracking/RoleEventPolicyTest.php` (new)

---

## 3. Add web admin RBAC

**Severity:** P1
**Status:** todo

### Manual says
The manual's §21.1 explicitly flags this as missing and warns that "anyone with a web login is a de-facto admin." If the system needs differentiated access (accountant, ops, branch admin), this gap must close.

### Reality
`users` table has no role column. `chkAuthAdmin` middleware checks login state only. Every authenticated user reaches every page including `/users` (create more admins), `/settings` (change company info), and accounting period close.

### What to do
1. **Add a roles table or enum:**

   ```php
   // migration
   Schema::table('users', function (Blueprint $t) {
       $t->enum('role', ['SUPER_ADMIN','ACCOUNTANT','OPS','BRANCH_MANAGER','READ_ONLY'])
         ->default('READ_ONLY')
         ->after('email');
       $t->unsignedBigInteger('branch_id')->nullable()->after('role');
       $t->foreign('branch_id')->references('id')->on('branches');
   });
   ```

2. **Introduce a `RoleGate` middleware factory:**

   ```php
   // app/Http/Middleware/RequireRole.php
   public function handle($request, Closure $next, string ...$roles)
   {
       abort_unless(in_array(Auth::user()->role, $roles, true), 403);
       return $next($request);
   }
   ```

3. **Apply at route group level:**

   ```php
   Route::middleware(['chkAuthAdmin','role:SUPER_ADMIN'])->group(function () {
       Route::get('/users', ...);
       Route::get('/settings', ...);
   });

   Route::middleware(['chkAuthAdmin','role:SUPER_ADMIN,ACCOUNTANT'])->group(function () {
       Route::post('/accounting/periods/{id}/close', ...);
       Route::post('/clients/deposit', ...);
       // ...
   });
   ```

4. **Backfill:** existing users get `SUPER_ADMIN` to avoid breaking production. New users default to `READ_ONLY`.

5. **Surface in `/users` UI:** role dropdown when creating/editing.

### Acceptance criteria
- [ ] `users.role` column exists.
- [ ] `RequireRole` middleware exists and is applied to sensitive route groups.
- [ ] Existing users are SUPER_ADMIN after migration (no production breakage).
- [ ] `/users` create/edit form shows a role selector.
- [ ] At least one feature test per protected route group asserts non-permitted roles get 403.

### Files to touch
- New migration in `database/migrations/`
- `app/Http/Middleware/RequireRole.php` (new)
- `app/Models/User.php`
- `routes/web.php` (apply middleware)
- `app/Http/Controllers/usersController.php` (role selector)
- `resources/views/users/*` (role dropdown)
- `tests/Feature/Auth/RbacTest.php` (new)

---

## 4. Add Sanctum token expiration

**Severity:** P0
**Status:** todo

### Manual says
> "Operational implication: a leaked token is valid until explicitly revoked." (§25.3)

That's a warning, not a feature. The system should reduce the window.

### Reality
`config/sanctum.php` has `'expiration' => null`. A token issued today is still valid in 5 years.

### What to do
1. **Set a default expiry:**

   ```php
   // config/sanctum.php
   'expiration' => env('SANCTUM_EXPIRATION_MINUTES', 60 * 24 * 30),  // 30 days
   ```

2. **Differentiate by audience:**

   - Client mobile app — 30-day token, refresh on every successful API call (`last_used_at`).
   - Employee scan app — 7-day token (employees scan daily; short window).
   - Web admin — N/A (uses session cookies, not Sanctum tokens).

   Implement per-token expiry by passing `expiresAt` in `createToken()`:

   ```php
   $token = $user->createToken('iPhone Daisy', ['branch:3'], now()->addDays(7));
   ```

3. **Add refresh flow:**

   - `POST /api/auth/refresh` returns a new token and revokes the old one when the current token is within 7 days of expiry.
   - Mobile apps proactively refresh; on `401 token_expired`, force re-login.

4. **Add `POST /api/auth/logout-all`** to revoke every token for the current user (for "log out all devices" UX).

### Acceptance criteria
- [ ] `config/sanctum.php` has a non-null default expiration.
- [ ] Login endpoints pass per-audience `expiresAt`.
- [ ] `POST /api/auth/refresh` exists and rotates tokens.
- [ ] `POST /api/auth/logout-all` exists.
- [ ] Mobile apps handle 401 by redirecting to login.
- [ ] Manual §25.3 updated.

### Files to touch
- `config/sanctum.php`
- `app/Http/Controllers/Api/AuthController.php`
- `app/Modules/Tracking/Http/Controllers/Employee/AuthController.php`
- `routes/api.php` (add refresh + logout-all)
- `mobile/lib/src/api/api_client.dart` (401 handler)
- `mobile_employee/lib/src/api/api_client.dart` (401 handler)

---

## 5. Centralize notification dispatch behind `NotificationService`

**Severity:** P2
**Status:** todo

### Manual says
> "Backend uses `app/Services/Notify.php` (or the relevant service) to fan out push messages on each side-effect." (§10)

The file doesn't exist.

### Reality
Notification dispatch is scattered across `app/Modules/Tracking/Jobs/DispatchShipmentEventNotificationJob.php` and ad-hoc Laravel `Notification` calls. There's no single place that:
- Reads `clients.notify_*` preferences before sending.
- Logs the outcome (sent / muted / failed) uniformly.
- Provides a test mode that captures notifications instead of sending.

### What to do
1. Create `app/Services/NotificationService.php`:

   ```php
   class NotificationService
   {
       public function notifyClient(int $clientId, string $kind, array $payload): void
       {
           $client = Client::find($clientId);
           if (! $this->isEnabled($client, $kind)) {
               $this->log('muted', $clientId, $kind);
               return;
           }
           // dispatch push job, write notifications row, etc.
       }

       public function isEnabled(Client $client, string $kind): bool { /* ... */ }
   }
   ```

2. Migrate existing callsites to use the service.

3. Add a `NotificationLog` table for sent/muted/failed audit (or piggyback `audit_log`).

### Acceptance criteria
- [ ] `NotificationService` exists and is bound in the container.
- [ ] All notification dispatch points use it.
- [ ] Preferences are consulted exactly once, in the service.
- [ ] Tests cover muted, sent, and failed paths.
- [ ] Manual §10 + §14 updated to point at the real service.

### Files to touch
- `app/Services/NotificationService.php` (new)
- `app/Modules/Tracking/Jobs/DispatchShipmentEventNotificationJob.php`
- Various controllers that currently dispatch notifications directly
- `system/docs/MANUAL.md`

---

## 6. Add 2FA for system admin accounts

**Severity:** P1
**Status:** todo

### Manual says
Nothing explicitly — but §25.14 has a security checklist that implies "strong admin authentication" is expected.

### Reality
Single-factor only, for everyone including system admins.

### What to do
1. Install `pragmarx/google2fa-laravel` (or equivalent TOTP package).
2. Add `two_factor_secret` and `two_factor_confirmed_at` columns to `users`.
3. Require enrollment on first login for users with role `SUPER_ADMIN` (gate #3 prerequisite).
4. Add a 2FA challenge step after password verification.
5. Recovery codes generation + display once.

### Acceptance criteria
- [ ] TOTP package installed.
- [ ] Enrollment flow at `/users/2fa/enroll`.
- [ ] Challenge step at login when 2FA is enabled.
- [ ] Recovery codes generated and viewable once.
- [ ] Admin can reset another admin's 2FA from `/users/{id}/edit`.
- [ ] Manual §25 has a new subsection on 2FA.

### Files to touch
- `composer.json`
- New migration for `users.two_factor_*`
- `app/Http/Controllers/usersController.php` (enrollment + reset)
- `resources/views/auth/2fa-*.blade.php` (new)
- `app/Http/Middleware/chkAuthAdmin.php` (2FA challenge)
- `system/docs/MANUAL.md`

---

## 7. Add self-serve password reset flow

**Severity:** P2
**Status:** todo

### Manual says
> "Reset is admin-mediated only — there is no self-serve forgot-password flow." (§25.2)

Accepted as a deliberate trade-off in the manual. But for 100+ clients, it's operationally expensive.

### Reality
`password_reset_tokens` table exists (Laravel default) but no endpoints use it.

### What to do
Standard Laravel password reset:
1. `POST /password/email` — accepts client code or email, sends reset link.
2. `GET /password/reset/{token}` — renders form.
3. `POST /password/reset` — accepts new password.

Constraints:
- Throttle aggressively (5 requests/hour per email/code, 50/hour per IP).
- Mail must use the SMTP relay (no built-in queue → ensure queue worker is running for mail dispatch).
- For clients: send to `clients.email` if set; otherwise fall back to admin-mediated (current behavior).
- For staff: always works.

### Acceptance criteria
- [ ] Three routes exist and are throttled.
- [ ] Reset email template is branded.
- [ ] Mobile client app has "Forgot password?" link on login screen.
- [ ] Manual §25.2 updated.

### Files to touch
- `routes/web.php`
- `app/Http/Controllers/Auth/PasswordResetController.php` (new)
- `resources/views/auth/passwords/*`
- `mobile/lib/src/screens/login_screen.dart`
- `system/docs/MANUAL.md`

---

## 8. Add data retention / purge jobs

**Severity:** P1
**Status:** todo

### Manual says
> "ShipFlow has no automatic data retention / purge policy out of the box. By default everything is retained forever." (§25.11)

Explicitly called out as missing.

### Reality
- `webhook_deliveries.payload` grows unbounded (each ShipsGo event keeps full JSON).
- `failed_jobs` grows unbounded.
- `notifications` for read items grows unbounded.
- `audit_log` is intentionally kept forever — that's fine, but it should be archived to cheap storage past N months.

### What to do
Add five console commands, register in scheduler:

| Command | Frequency | Action |
|---------|-----------|--------|
| `purge:webhook-payloads` | daily | Nullify `payload` column for rows older than 90 days; keep metadata. |
| `purge:failed-jobs` | weekly | Delete rows older than 30 days. |
| `purge:read-notifications` | weekly | Delete rows where `read_at < now() - 180d`. |
| `archive:audit-log` | monthly | Export rows older than 18 months to S3 as JSONL, then delete. |
| `purge:expired-sanctum-tokens` | daily | Delete `personal_access_tokens` past their `expires_at` (depends on gap #4). |

### Acceptance criteria
- [ ] All five commands exist with sensible defaults and a `--dry-run` flag.
- [ ] All five are registered in the scheduler.
- [ ] Each command logs row counts purged.
- [ ] Tests cover the `--dry-run` and the actual delete paths.
- [ ] Manual §25.11 updated to reference these commands.

### Files to touch
- `app/Console/Commands/Purge*.php` (5 new)
- `bootstrap/app.php` scheduler (depends on gap #1)
- `tests/Feature/Purge/*`
- `system/docs/MANUAL.md`

---

## 9. Install Sentry + Pail for error monitoring

**Severity:** P1
**Status:** todo

### Manual says
> "Errors: **Sentry** (`sentry/sentry-laravel`) — install, set `SENTRY_LARAVEL_DSN`, you get unhandled exceptions + slow query alerts." (§24.2)

### Reality
Neither is installed. `composer.json` has no `sentry/sentry-laravel`. `pail` is in `require-dev` only.

### What to do
1. `composer require sentry/sentry-laravel`.
2. `php artisan sentry:publish --dsn=...`.
3. Add `SENTRY_LARAVEL_DSN` to `.env.example`.
4. Configure performance traces sample rate (`traces_sample_rate=0.1` to start).
5. Tag releases automatically via `composer-script` calling `sentry:create-release`.
6. Document the DSN management process: who has access, how to rotate.

### Acceptance criteria
- [ ] Sentry package installed and configured.
- [ ] A deliberate test exception in staging shows up in Sentry.
- [ ] Performance trace appears for a real request.
- [ ] `.env.example` shows the variable.
- [ ] Manual §24.2 marks Sentry as "installed" instead of "recommended."

### Files to touch
- `composer.json`
- `config/sentry.php` (published)
- `.env.example`
- `system/docs/MANUAL.md`

---

## 10. Enforce `audit_log` immutability at DB level

**Severity:** P1
**Status:** todo

### Manual says
> "There is no delete endpoint. There is no edit endpoint." But also: "The audit log is **append-only** — there is no UI to delete entries, and the table has no delete endpoint." (§15)
> "Audit log immutability is policy, not enforcement." (§25.9 — flagged as gap)

### Reality
A compromised app DB user can `DELETE FROM audit_log` freely.

### What to do
1. Create a dedicated MySQL user, e.g. `shipflow_audit_writer`:

   ```sql
   CREATE USER 'shipflow_audit_writer'@'app-host' IDENTIFIED BY '<secret>';
   GRANT INSERT, SELECT ON ship_system.audit_log TO 'shipflow_audit_writer'@'app-host';
   REVOKE DELETE, UPDATE ON ship_system.audit_log FROM 'shipflow_app'@'app-host';
   ```

2. Configure a second DB connection in Laravel:

   ```php
   // config/database.php
   'connections' => [
       'audit' => [
           // same as 'mysql' but different username/password
       ],
   ],
   ```

3. `AuditLogService` writes using `DB::connection('audit')`.

4. App's main DB user no longer has DELETE on `audit_log`.

5. Document the privilege model in the manual.

### Acceptance criteria
- [ ] Second DB user exists with INSERT-only on `audit_log`.
- [ ] App's main user lacks DELETE/UPDATE on `audit_log`.
- [ ] `AuditLogService` uses the audit connection.
- [ ] Smoke test: `DB::connection('mysql')->table('audit_log')->delete()` throws "permission denied"; `DB::connection('audit')->table('audit_log')->insert(...)` succeeds.
- [ ] Manual §25.9 updated.

### Files to touch
- `config/database.php`
- `app/Modules/Tracking/Services/AuditLogService.php` (and any other writers)
- Production MySQL setup docs
- `system/docs/MANUAL.md`

---

## 11. Add CI pipeline (GitHub Actions)

**Severity:** P1
**Status:** todo

### Manual says
> "Testing: PHPUnit 11, Mockery 1.6, fakerphp." (§3)

Implies tests are part of the workflow.

### Reality
`tests/` and `phpunit.xml` exist; nothing runs them automatically. No `.github/workflows/`.

### What to do
Add `.github/workflows/ci.yml`:

```yaml
name: CI
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8
        env:
          MYSQL_DATABASE: ship_system_test
          MYSQL_ROOT_PASSWORD: root
        ports: ['3306:3306']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, mysql, gd, intl
      - run: composer install --no-progress
      - run: cp .env.example .env && php artisan key:generate
      - run: php artisan migrate --env=testing
      - run: vendor/bin/phpunit
      - run: vendor/bin/pint --test
```

Optional second job:
- `mobile-test` — runs `flutter test` for both `mobile/` and `mobile_employee/`.

### Acceptance criteria
- [ ] `ci.yml` exists and runs on push + PR.
- [ ] Tests pass green on `main`.
- [ ] PRs blocked from merge until CI is green (branch protection — out of repo).
- [ ] Manual §3 + §17 reference CI.

### Files to touch
- `.github/workflows/ci.yml` (new)
- Possibly `.env.testing`
- `system/docs/MANUAL.md`

---

## 12. Clean up `config/sanctum.php` stateful domains

**Severity:** P2
**Status:** todo

### Manual says
Mobile apps are stateless (token-based).

### Reality
`config/sanctum.php` still has the `'stateful' => [...]` array populated. Harmless but misleading — implies SPA/cookie auth is in use.

### What to do
- If no first-party SPA uses cookie auth: set `'stateful' => []`.
- Document the choice in a comment.

### Acceptance criteria
- [ ] `stateful` is empty, with a comment explaining why.
- [ ] No code path depends on stateful auth.

### Files to touch
- `config/sanctum.php`

---

## 13. Fix `MANUAL.md` permission matrix to reflect reality

**Severity:** P1 (until gap #2 is closed)
**Status:** todo

### What's wrong
§21.2 presents a matrix as if it were enforced. It isn't.

### What to do
Until gap #2 closes:
- Add a callout at the top of §21.2: **"NOTE: The matrix below is policy, not enforcement. Until gap #2 (`docs/GAPS.md`) is resolved, any active branch_staff member can submit any internal event type. Treat the matrix as authoritative for staff training; do not rely on it for authorization."**

When gap #2 closes:
- Remove the callout.
- Link the matrix to `RoleEventPolicy::ALLOWED` so the source of truth is in one place.

### Acceptance criteria
- [ ] Callout exists.
- [ ] Callout deleted in the same PR that closes gap #2.

### Files to touch
- `system/docs/MANUAL.md`

---

## 14. Fix `MANUAL.md` ERD: tracking_branches is separate, not 1:1

**Severity:** P2
**Status:** todo

### What's wrong
§20.2 ERD shows `tracking_branches` 1:1 with `branches` and "`id (PK = branches.id)`". The migration says they're independent tables.

### What to do
- Replace the 1:1 arrow with no relationship — they share neither FK nor synchronized IDs.
- Add a note: "The legacy `branches` table and the newer `tracking_branches` table are independent. Joining them requires matching on `code` or a deliberate sync. The tracking module's data uses `tracking_branches` exclusively."

### Acceptance criteria
- [ ] ERD updated.
- [ ] Note added.

### Files to touch
- `system/docs/MANUAL.md`

---

## 15. Fix `MANUAL.md` Notify reference

**Severity:** P2
**Status:** todo (depends on gap #5)

### What's wrong
§10 references `app/Services/Notify.php` which doesn't exist.

### What to do
- If gap #5 closes: point at `app/Services/NotificationService.php`.
- If gap #5 doesn't close: point at `DispatchShipmentEventNotificationJob` and the `Notification` classes in `app/Modules/Tracking/Notifications/`.

### Files to touch
- `system/docs/MANUAL.md`

---

## 16. Document the rest of `app/Console/Commands/`

**Severity:** P2
**Status:** todo

### Reality
Commands present but undocumented in MANUAL.md:
- `GenerateStickerBatchCommand` — how staff create sticker batches.
- `ShipsGoSmokeCommand` — ShipsGo integration diagnostic.
- `JournalBackfill` — one-shot migration helper.
- `ShipmentPiecesBackfill` — one-shot migration helper.
- `SourcingHealthSnapshotCommand` — populates `sourcing_deal_health_snapshots`.
- `TrackingE2EWalkCommand` — synthetic end-to-end test.

### What to do
Add §17.5 "Console commands reference" listing every command with:
- Signature
- Purpose
- Schedule (or "manual only")
- Idempotency guarantees

### Acceptance criteria
- [ ] §17.5 exists and lists every command in `app/Console/Commands/`.

### Files to touch
- `system/docs/MANUAL.md`

---

## 17. Document `cron_jobs` command purpose or remove it

**Severity:** P2
**Status:** todo

### Reality
`app/Console/Commands/cron_jobs.php` is a single command that calls `treasuryController::save_treasury()`. No docstring. Not in the scheduler. Probably an artifact from before the modular structure.

### What to do
Investigation:
1. Read `treasuryController::save_treasury` and decide what it actually does.
2. If still needed → register in scheduler (gap #1) with a real signature like `treasury:save-snapshot`.
3. If obsolete → delete the command, write a one-line tombstone in CHANGELOG.

### Acceptance criteria
- [ ] Command is either properly named, scheduled, and documented — or deleted.

### Files to touch
- `app/Console/Commands/cron_jobs.php`
- `app/Http/Controllers/treasuryController.php` (read)
- `bootstrap/app.php` (depends on gap #1)
- `system/docs/MANUAL.md`

---

## 18. Enforce HTTPS in production

**Severity:** P1
**Status:** todo

### Manual says
> "TLS-only API base URL. App will fail to connect on `http://` outside dev mode." (§25.13)

That's an app-side mitigation, not server-side enforcement.

### Reality
No `\App\Providers\AppServiceProvider::boot` forces `URL::forceScheme('https')`. No middleware redirects HTTP → HTTPS. Behind Cloudflare it works because the edge handles it, but a direct origin hit over HTTP would not redirect.

### What to do
1. In `AppServiceProvider::boot`:

   ```php
   if (app()->environment('production')) {
       URL::forceScheme('https');
   }
   ```

2. Configure `TrustProxies` for Cloudflare IPs (or `'*'` if origin only accepts Cloudflare):

   ```php
   protected $proxies = '*';
   protected $headers = Request::HEADER_X_FORWARDED_FOR
       | Request::HEADER_X_FORWARDED_HOST
       | Request::HEADER_X_FORWARDED_PORT
       | Request::HEADER_X_FORWARDED_PROTO;
   ```

3. Optionally add an explicit redirect middleware for any HTTP traffic.

### Acceptance criteria
- [ ] `forceScheme('https')` set in production.
- [ ] `TrustProxies` configured.
- [ ] Origin host firewall denies port 80 from non-Cloudflare IPs.
- [ ] Manual §22 + §25 updated.

### Files to touch
- `app/Providers/AppServiceProvider.php`
- `app/Http/Middleware/TrustProxies.php` (or `bootstrap/app.php` config)
- Production host firewall (out of repo)
- `system/docs/MANUAL.md`

---

## 19. Add HSTS, CSP, and other security headers

**Severity:** P1
**Status:** todo

### Manual says
Nothing explicit, but §25 implies "we care about web security."

### Reality
No middleware sets `Strict-Transport-Security`, `Content-Security-Policy`, `X-Content-Type-Options`, `X-Frame-Options`, or `Referrer-Policy`.

### What to do
1. Add `app/Http/Middleware/SecurityHeaders.php`:

   ```php
   public function handle($request, Closure $next)
   {
       $r = $next($request);
       $r->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
       $r->headers->set('X-Content-Type-Options', 'nosniff');
       $r->headers->set('X-Frame-Options', 'SAMEORIGIN');
       $r->headers->set('Referrer-Policy', 'same-origin');
       // CSP: start permissive, tighten as the team confirms what breaks.
       $r->headers->set('Content-Security-Policy',
           "default-src 'self'; img-src 'self' data: https:; "
           ."script-src 'self' 'unsafe-inline'; "
           ."style-src 'self' 'unsafe-inline'; "
           ."font-src 'self' data:; "
           ."connect-src 'self';"
       );
       return $r;
   }
   ```

2. Register globally in `bootstrap/app.php`.

3. Decide policy for embeddable views (proforma portal) — may need `frame-ancestors`.

### Acceptance criteria
- [ ] Middleware exists and is globally registered.
- [ ] `curl -I` of any page shows all five headers.
- [ ] CSP doesn't break existing pages (manual smoke test of every major module).
- [ ] Manual §25 has a new subsection on security headers.

### Files to touch
- `app/Http/Middleware/SecurityHeaders.php` (new)
- `bootstrap/app.php`
- `system/docs/MANUAL.md`

---

## 20. Document `/up` health endpoint in deployment section

**Severity:** P2
**Status:** todo

### Reality
Laravel ships `/up` (registered in `bootstrap/app.php` via `health: '/up'`). Manual mentions it in §24 monitoring but not in §22 deployment, where it matters most for LB health-check configuration.

### What to do
- §22.1 production topology: annotate the LB → web boxes arrow with "health check: `GET /up` every 10s, 2 consecutive failures = unhealthy".
- §22.2 server roles: note that the web role exposes `/up`.

### Acceptance criteria
- [ ] §22 references `/up`.

### Files to touch
- `system/docs/MANUAL.md`

---

## Done

### #2 — Role-event matrix on scan API (closed 2026-06-07)

- Added `App\Modules\Tracking\Services\RoleEventPolicy` with the matrix as a class constant (single source of truth, mirrors MANUAL §21.2).
- `ScanController::submit` enforces the role gate before the unassigned-first-scan and state-machine checks. Denied calls return HTTP 403 with `type: role_action_denied`.
- `ScanController::resolve` now accepts optional `branch_id`; when provided, filters `allowed_event_types` by role on that branch (backwards compat — without `branch_id`, returns state-machine allowed only).
- Tests: 32 matrix cell tests in `tests/Unit/Tracking/RoleEventPolicyTest.php` + 6 integration tests in `tests/Feature/Tracking/ScanRoleGateTest.php`. Updated `EmployeeApiTest::test_scan_submit_invalid_transition_returns_422` to pick a role-allowed event so the role gate doesn't mask the unassigned-first-scan signal.
- Full tracking suite green: 80 tests / 180 assertions.

### #13 — Manual permission matrix callout (closed 2026-06-07)

Closed as a side effect of #2. The matrix is now enforced, so no "policy not enforcement" callout is needed.

### #4 — Sanctum token expiration (closed 2026-06-07)

- `config/sanctum.php`: default `expiration` is now 30 days (`SANCTUM_EXPIRATION_MINUTES` env override). `guard` set to `[]` to make `/api/*` stateless-only — the web admin doesn't use Sanctum so this doesn't affect it.
- Client tokens: 30-day TTL (`AuthController::CLIENT_TOKEN_TTL_MINUTES`).
- Employee tokens: 7-day TTL (`AuthController::EMPLOYEE_TOKEN_TTL_MINUTES`) — shorter window because staff scan daily and we want a tighter revocation window.
- New endpoints:
  - `POST /api/auth/refresh` — rotate the current client token; issues a new one + revokes the old.
  - `POST /api/auth/logout-all` — revoke every client token (lost-phone flow).
  - `POST /api/v1/employee/auth/refresh` — same for employee tokens. Re-reads `branch_staff` so abilities reflect any role/assignment changes since login. If the user has no active branches, all tokens are wiped and 403 returned.
  - `POST /api/v1/employee/auth/logout-all` — revoke every employee token.
- All login + refresh responses now include `expires_at` (ISO 8601) so the mobile apps can preemptively refresh.
- New command `php artisan tokens:purge-expired` (with `--dry-run`) deletes expired rows. Schedule it nightly once gap #1 lands.
- Tests: 5 tests covering token TTL, refresh rotation, refresh-without-branches, logout-all, and purge command (`tests/Feature/Auth/SanctumTokenLifecycleTest.php`).
- Full suite green: 110 tests / 245 assertions.

**Mobile app follow-up not in this PR:** the Flutter clients need to handle 401 by calling refresh or forcing re-login; and proactively call refresh when the local `expires_at` is within (say) 24 hours. Logged as a separate task in mobile/CHANGELOG.

### #1 — Laravel scheduler (closed 2026-06-07)

- `bootstrap/app.php` now has `->withSchedule(...)` registering all routine recurring tasks in one place.
- Scheduled:
  - `sourcing:remind` daily at 09:00
  - `sourcing:health-snapshot` daily at 02:00
  - `tracking:reconcile-stuck` every 4 hours
  - `tokens:purge-expired` daily at 03:00
- Each is `onOneServer()` + `withoutOverlapping()` so multi-host deployments don't double-run.
- Production cron entry simplifies to one line: `* * * * * cd /var/www/system && php artisan schedule:run`.
- Verified via `php artisan schedule:list` — also surfaces two pre-existing Purchases module schedules (`purchases:fetch-exchange-rates`, `purchases:check-low-balances`) registered via its ServiceProvider.

### #17 — `cron_jobs` command (closed 2026-06-07)

`treasuryController::save_treasury()` was an empty stub with a commented-out log line; `cron_jobs` command did nothing. Deleted both the command file and the dead static method reference. No callers anywhere.

### #6 — Two-factor authentication for staff (closed 2026-06-07)

- `composer require pragmarx/google2fa-qrcode bacon/bacon-qr-code` — minimal TOTP deps (no Laravel-wrapper package; we own the integration).
- Migration adds `two_factor_secret` (text) + `two_factor_confirmed_at` (timestamp), both nullable, on `users`.
- New `App\Services\TwoFactorAuthService` wraps secret generation, otpauth URI building, QR rendering as inline SVG, and `verify()`.
- New `App\Http\Controllers\Auth\TwoFactorController` with three flows:
  - **Enrollment** — staff opts in voluntarily. Generates secret on first GET, renders QR + manual key, confirms with a TOTP submission.
  - **Login challenge** — `usersController::login` detects enrolled users and suspends auth into `2fa.user_id` session, redirecting to challenge. Only after a valid code does `Auth::login` happen.
  - **Admin reset** — admin-only (`type:admin`): clears another user's enrollment so they can re-enroll on a new device.
- Routes wrapped in `throttle:login` to throttle code-guessing.
- Two minimal Blade views in `resources/views/pages/auth/` for enrollment and challenge.
- Tests: 6 in `tests/Feature/Auth/TwoFactorAuthTest.php` — service generate+verify, full enrollment flow, login-redirects-to-challenge, challenge-verifies-and-logs-in, wrong-code-rejected, admin-can-reset.

**Not in scope here (follow-up):**
- Recovery codes for the locked-out-without-phone case. Right now an admin reset is the only recovery path. Adding recovery codes is straightforward but adds UI surface — separate ticket.
- Enforcing 2FA enrollment (rather than opt-in) for `type:admin` users. Easy follow-up: add a middleware that redirects un-enrolled admins to the enrollment page.

Full suite green: 135 tests / 341 assertions.

### #5 — Centralize notification dispatch (closed 2026-06-07)

- New `App\Services\NotificationService` with three methods: `notifyClient(Client, kind, Notification)`, `notifyClients(iterable, kind, Notification)`, and `isEnabledFor(Client, kind)`.
- Three kind constants matching the existing DB columns: `transactions`, `shipments`, `receipts`. Unknown kinds default to allowed (opt-out, not opt-in).
- Real fix shipped: **`clients.notify_*` preferences are now actually honored.** Previously `DispatchShipmentEventNotificationJob` called `Notification::send` directly and ignored the preferences entirely.
- Uniform logging: `[notify] muted`, `[notify] dispatch failed`, `[notify] fan-out: all recipients muted` make it easy to grep what happened.
- `DispatchShipmentEventNotificationJob` now delegates to the service. Behavior preserved for clients with the default preference (true).
- Tests: 4 in `tests/Feature/Notification/NotificationServiceTest.php` covering enabled-dispatches, disabled-mutes, unknown-kind-passes-through, and fan-out filters per recipient.

Full suite green: 129 tests / 313 assertions.

### #7 — Self-serve password reset (staff side) (closed 2026-06-07)

- New `App\Http\Controllers\Auth\PasswordResetController` with the four standard Laravel password-reset actions (show request form, send reset link, show reset form, submit new password).
- Four new routes wrapped in `throttle:login` so the reset endpoints inherit the per-identifier 5/min + per-IP 20/min protection (same as login).
- Reset success revokes every Sanctum token on the user — if their password was compromised, the bearer tokens were too.
- Neutral response when an unknown email is submitted (never leaks which addresses are valid staff).
- Two minimal Blade views in `resources/views/pages/auth/` — request form and reset form. Stand-alone styling, no dependency on the legacy SPA shell.
- Tests: 4 in `tests/Feature/Auth/PasswordResetTest.php` covering request form render, neutral response for unknown email, full reset flow including token capture, and invalid-token rejection.

**Not in scope here (follow-up):**
- Client password reset (mobile app users). Same `CanResetPassword` trait is on the `Client` model; reusing the controller for clients requires a second password broker (`clients`) configured in `config/auth.php` and parallel routes/views. Tracked separately.
- Adding a "Forgot password?" link to the existing login Blade view. The route is already public (`/password/request`), so it's discoverable but not yet linked from the login page.

Full suite green: 125 tests / 303 assertions.

### #3 — Web admin RBAC formalization (closed 2026-06-07)

Investigation revealed the system already had a two-role convention: `users.type` of `'admin'` (full access) or `'branch_admin'` (branch-scoped). Enforcement was inline in controllers (`auth()->user()->type === 'admin'`) — easy to forget on new endpoints. This PR formalizes it:

- New `App\Http\Middleware\RequireType` middleware that accepts allowed types as parameters, returns 401 if unauthenticated, 403 if the type doesn't match.
- Registered as `type` alias in `bootstrap/app.php`. Usage: `Route::middleware(['chkAuthAdmin', 'type:admin'])->group(...)`.
- Applied to the two most sensitive route groups: `/users/*` (user management + password changes) and `/settings*` (system settings). Inline checks remain — middleware is defense in depth.
- 5 tests covering admin-passes, branch_admin-blocked-for-admin-only, multi-type-allowed, unauthenticated, unknown-type cases.

**Not in scope here** (would be a separate, larger task): introducing a richer role model (separate columns, multiple roles per user, branch-scoped permission resolution). The two-role model is what the existing controllers expect; expanding it requires coordinated changes across many inline checks.

Full suite green: 121 tests / 284 assertions.

### #10 — Audit log DB-level immutability (closed 2026-06-07)

- New `audit_admin` connection in `config/database.php` — a parallel MySQL connection that defaults to the main DB credentials if `AUDIT_ADMIN_*` env vars aren't set (so dev/CI stays unchanged).
- New `config/audit.php` with `'archive_connection' => env('AUDIT_ARCHIVE_CONNECTION', 'mysql')`. Production flips it to `audit_admin` once the GRANT is applied.
- `archive:audit-log` command splits read (default mysql, SELECT) from write (configured connection, DELETE). The main app user retains SELECT+INSERT on `audit_log` only; the privileged user has DELETE.
- `.env.example` and the config file both document the exact SQL grants needed.
- Tests unchanged (default config → mysql for both); full suite green: 116 tests / 279 assertions.

**Operational next-step (manual in prod):** ops engineer runs the two `GRANT` / `REVOKE` blocks documented in `config/audit.php`, sets `AUDIT_ARCHIVE_CONNECTION=audit_admin` + the matching credentials in production `.env`, restarts PHP-FPM and queue workers. After that point, even a compromised app DB user cannot delete from `audit_log`.

### #9 — Sentry installation (closed 2026-06-07)

- `composer require sentry/sentry-laravel` (4.25). Auto-registered via Laravel package discovery.
- `config/sentry.php` published.
- `bootstrap/app.php`'s `withExceptions()` now calls `Sentry\Laravel\Integration::handles($exceptions)` so unhandled exceptions are forwarded when a DSN is configured. Without a DSN it's a no-op — local dev and tests are unaffected.
- `.env.example` now documents `SENTRY_LARAVEL_DSN`, `SENTRY_TRACES_SAMPLE_RATE` (default 0.1 in prod), `SENTRY_PROFILES_SAMPLE_RATE`, `SENTRY_ENVIRONMENT`.
- Pail was already in `require-dev`; the manual's recommendation to use it for live tail (`php artisan pail`) is unchanged.
- Full suite green: 116 tests / 279 assertions.

Operational next-step (not in this PR): ops engineer creates a Sentry project, drops the DSN into the production `.env`, and runs `composer-script` calling `sentry:create-release` on deploys for release tagging.

### #14, #15, #16, #20 — Manual doc fixes (closed 2026-06-07)

- **#14** ERD §20.2 now describes `branches` and `tracking_branches` as independent tables, with a written-out note explaining they share no FK and no synchronized IDs. Diagram updated to reflect reality.
- **#15** §10 push notification reference replaced with concrete pointers to `DispatchShipmentEventNotificationJob` and the two real `Notification` classes. Notes that centralization is gap #5.
- **#16** New §17.5 *Console commands reference* lists every command in `app/Console/Commands/` plus the two scheduled commands owned by the Purchases ServiceProvider. Each row says when it runs and what it does.
- **#20** §17 *What needs to be running* now annotates `/up` as the LB health-check endpoint with sensible defaults (10s interval, 2 failures = unhealthy).

### #11 — CI pipeline (closed 2026-06-07)

- New `.github/workflows/ci.yml` runs on every push + PR. Matrix over PHP 8.2 and 8.3.
- Spins up MySQL 8.0 as a service, prepares a `.env` for the testing environment, runs `migrate` + `db:seed`, then `phpunit`.
- Composer dependencies are cached on `composer.lock` hash.
- Pint is run as advisory (`|| true`) for now; once the codebase is fully Pint-clean we can drop the `|| true`.
- New `system/.env.example` (was missing) gives new developers a working starting `.env`.

### #18, #19, #12 — HTTPS enforcement + security headers + Sanctum stateful cleanup (closed 2026-06-07)

- `bootstrap/app.php` now calls `$middleware->trustProxies(at: '*')` so the X-Forwarded-* headers from Cloudflare/nginx are honored — `$request->isSecure()` works correctly behind TLS-terminating proxies.
- `AppServiceProvider::boot` calls `URL::forceScheme('https')` in the production environment so generated URLs (and any `url()`/`route()` callers) emit https.
- New global middleware `App\Http\Middleware\SecurityHeaders` sets `Strict-Transport-Security` (only on actual https), `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: same-origin`, and a permissive baseline CSP. CSP still allows `'unsafe-inline'` for scripts/styles because the legacy Blade views need it; tightening is tracked separately.
- `config/sanctum.php` `guard => []` (set in #4) means the legacy `stateful => [...]` list is no longer consulted. Left the stateful entry in place but commented for posterity; no live code reads it.

Tests: 2 in `tests/Feature/Http/SecurityHeadersTest.php` exercising both the static headers and the http vs https HSTS gate via `X-Forwarded-Proto`.

Full suite green: 116 tests / 279 assertions.

### #8 — Data retention / purge jobs (closed 2026-06-07)

Four new commands plus the previously-added `tokens:purge-expired`, all `--dry-run`-capable, all idempotent:

- `purge:webhook-payloads` — replaces `webhook_deliveries.payload` with a `{"_trimmed": true}` stub after 90 days (column is NOT NULL so we can't simply null it). Daily 03:15.
- `purge:failed-jobs` — deletes `failed_jobs` rows older than 30 days. Weekly Mon 03:30.
- `purge:read-notifications` — deletes notifications where `read_at` is older than 180 days. Unread notifications are never touched. Weekly Mon 03:45.
- `archive:audit-log` — exports `audit_log` rows older than 18 months to `storage/app/private/audit-archive/{YYYY-MM}.jsonl.gz` (multi-stream gzip append), then deletes them from the table. Monthly on the 1st at 04:00. Ops should sync the archive directory to cold storage as part of nightly backups.

All registered in `bootstrap/app.php` with `onOneServer()` + `withoutOverlapping()`. `php artisan schedule:list` now shows 10 jobs (2 from Purchases module + 8 from ShipFlow core).

Tests: 4 in `tests/Feature/Purge/PurgeCommandsTest.php`. Each exercises the dry-run + real path and asserts only the right rows are affected.

Full suite green: 114 tests / 267 assertions.
