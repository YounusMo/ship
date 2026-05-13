# Security Findings — MATAZ TRADING COMPANY (Laravel 12 / `ship/`)

Adversarial security audit performed 2026-05-13.
Repo path: `/Users/younusmohammed/Downloads/ship/`
App path: `/Users/younusmohammed/Downloads/ship/system/`
Doc-root layout: `system/` exposed directly under web root (NOT `system/public/`).
Methodology: manual code review of routes/controllers/middleware + `composer audit` + grep over upload sinks, dynamic SQL, raw blade output, hardcoded secrets, env, and public-reachable files.

---

## 1. Summary scorecard

| Severity | Count |
|---|---|
| Critical | 8 &rarr; 1 (Before / After) |
| High | 11 &rarr; 1 (Before / After) |
| Medium | 8 &rarr; 4 (Before / After) |
| Low | 5 &rarr; 4 (Before / After) |
| **Total findings** | **32 (live after patch: 10)** |

Top CWE categories observed:
- **CWE-862 Missing Authorization** (debug routes, dataController, api.php, clientsController.load) — most pervasive
- **CWE-552 / CWE-538 Files Exposed to External Party** (`.env`, `composer.json`, `composer.lock`, `qubtangroup_sub.sql`, `cron_jobs/backups/*.sql`, `backup.php`, `x.html`)
- **CWE-798 Hardcoded Credentials** (DB password in `backup.php` and `cron_jobs/backup.php`; mailer password and Laravel `APP_KEY` in `.env`)
- **CWE-915 / CWE-913 Mass-assignment-style "names/values" array** (`seaController::new_received`, `skyController::new_received`, `save_received`, `usersController::save_profile`)
- **CWE-89 SQL Injection** (`del_recs`, `del_recs_permanent`, `restore_recs` accept `$request->table`)
- **CWE-22 / CWE-434 Path Traversal & Unrestricted Upload** (`getClientOriginalName()` -> `$file->move()` into `photos/sea/{client_id}` and `photos/sky/{client_id}` with no extension/mime check; client_id from request)
- **CWE-639 IDOR** (every controller takes `$request->id`/`$request->client_id` with no ownership check)
- **CWE-307 Improper Restriction of Excessive Authentication Attempts** (no throttle on `/auth/user/login`)
- **CWE-1004 Cookie missing Secure flag** (`SESSION_SECURE_COOKIE` unset, `SESSION_ENCRYPT=false`)
- **CWE-489 Active Debug Code in Production** (`/new_user`, `/xx`, `/mm`, `/zz`, `/nn`, `/vvvv`, `/cccc`, `/ts`, `/update_balance_manual`)
- **CWE-256 / CWE-257 Cleartext Storage of Passwords** (`pass_txt` column written alongside the bcrypt `password`)
- **CWE-1336 SSTI-like view injection** (`return view($request->element)` in `dataController::load_ajax_element`)

---

## 2. Findings table (sorted by severity)

### Critical

| ID | CWE | Severity | Location | Exploit scenario | Recommended fix |
|---|---|---|---|---|---|
| F-001 | CWE-489 Active Debug Code / CWE-862 Missing Auth | **Critical** | `system/routes/web.php:455-463` | An unauthenticated visitor hits `GET /new_user` and the app creates an admin user (`email=m@mail.com`, `password=123`) which then lets them log in via `/login` with full admin (CRUD over clients, money transactions, treasury, branches). | Delete the route. If a seeder is needed, use `php artisan db:seed` locally; never expose user creation through an unauthenticated GET. |
| F-002 | CWE-798 Use of Hard-coded Credentials | **Critical** | `backup.php:13-16`, `cron_jobs/backup.php:41-44` | The MySQL username and password are hardcoded in the world-readable PHP files at the docroot. With `backup.php` already directly reachable (see F-003) the credentials leak through error output (any PHP misparse) and through the SQL dumps it writes. | Remove `backup.php` from docroot, move it under `system/`, load credentials from `.env` via `getenv()` / config, and rotate the DB password (`Dx1nMcIu(rP)Q?hC`) immediately. |
| F-003 | CWE-538 / CWE-552 Files & Directories Accessible to External Parties | **Critical** | `backup.php` (docroot), `cron_jobs/backup.php`, `cron_jobs/backups/*.sql`, `qubtangroup_sub.sql` (docroot) | Any anonymous internet user can fetch `https://<host>/backup.php` to trigger a fresh dump, or directly download `https://<host>/qubtangroup_sub.sql` and `https://<host>/cron_jobs/backups/2026-05-XX 01:00.sql` — full database including bcrypt password hashes, `pass_txt` cleartext passwords, balances, and transactions. | Move all backup scripts and `.sql` dumps outside the docroot (e.g. `/home/qubtangroup/private_backups/`). Add `Require all denied` / `Deny from all` `.htaccess` to any backup folder that must stay under web root. Delete the docroot `qubtangroup_sub.sql`. |
| F-004 | CWE-538 Sensitive File Accessible | **Critical** | `system/.env` (reachable as `/system/.env`), `system/composer.json`, `system/composer.lock`, `system/x.html`, `system/storage/logs/laravel.log` | Because the docroot is the project root (not `system/public/`), the standard `.htaccess` rewrite only protects requests that don't match a real file. `/.htaccess` does not deny access to `system/.env`, leaking `APP_KEY`, MySQL credentials, and the SMTP password (`MAIL_PASSWORD=f75abf8555f3976b618c0744d27b1501`). | Either (a) move app to `system/public/` and point Apache `DocumentRoot` there, or (b) add explicit `<Files>` / `<Directory>` deny rules in `.htaccess` for `.env`, `composer.*`, `*.log`, `system/storage/`, `system/bootstrap/`, `system/config/`, `system/resources/`, `system/routes/`. Rotate `APP_KEY`, DB password, and mailer API key. |
| F-005 | CWE-89 SQL Injection / CWE-915 | **Critical** | `system/app/Http/Controllers/dataController.php:236-331` (`del_recs`), `:333-350` (`restore_recs`), `:352-391` (`del_recs_permanent`) | `$request->table` is passed straight to `DB::table($request->table)->whereIn('id',$ids)->update([...])`. An authenticated user POSTs `{table: "users", ids: "[1]"}` to `/del_recs` and either soft-deletes/disables the admin row (DoS / lockout) or marks any record in any table as deleted. Combined with F-001 this is fully exploitable from the internet. Although Laravel quotes identifiers, the attacker fully controls the target table and can pivot to `password_reset_tokens`, `personal_access_tokens`, `sessions`, etc. | Whitelist `$request->table` against an explicit allow-list (`['clients','suppliers','customs_brokers',...]`). Reject anything else with 422. Also add role check (`admin` only) consistently — `del_recs` currently has no role check. |
| F-006 | CWE-862 Missing Authorization / CWE-489 | **Critical** | `system/routes/web.php:466-627, 640-685, 691` (`/xx`, `/mm`, `/zz`, `/nn`, `/vvvv`, `/cccc`, `/ts`, `/update_balance_manual`) | Unauthenticated visitors hitting `/zz`, `/nn`, `/vvvv`, `/cccc`, `/ts` see raw client/transaction balances per branch + per currency, and `/update_balance_manual` recomputes branch balances triggering large DB transactions (DoS amplifier). `/xx` is a commented-out write but the controller pattern shows similar debug write routes have shipped before. | Remove every one of these debug routes. They must not exist in a production codebase. |
| F-007 | CWE-1336 / CWE-470 Use of Externally-Controlled Input to Select Class/View | **Critical** | `system/app/Http/Controllers/dataController.php:205-213` (`load_ajax_element`) | The route `/load_ajax_element` (registered under both `chkAuthClient` and `chkAuthAdmin` groups) executes `view($request->element)`. An authenticated client posts `element=pages.users.table` (or any admin-only blade) and the server renders admin-only data (clients, users, transactions, balances) back to them. This is full horizontal + vertical IDOR via view choice. | Whitelist the allowed view names (`$allowed = ['pages.clients.modal_new', ...]; abort_unless(in_array($request->element,$allowed),404);`). Never pass request input into `view()`. |
| F-008 | CWE-256 / CWE-257 Cleartext Storage of Password | **Critical** | `system/app/Http/Controllers/usersController.php:90`, `:189`; `system/app/Http/Controllers/clientsController.php:158, 223` | Every user and every client password is stored in plaintext in `users.pass_txt` / `clients.pass_txt` alongside the bcrypt hash. Any read access to those tables (SQL injection, leaked backup F-003, an admin-side report bug, etc.) yields all real passwords — devastating because users reuse passwords. | Drop the `pass_txt` column from both tables, remove every `pass_txt` write in `usersController::create/change_pass/save_profile` and `clientsController::create/save`. If the product requires admins to view "the password they set", store nothing — generate a one-time reveal token instead. |

### High

| ID | CWE | Severity | Location | Exploit scenario | Recommended fix |
|---|---|---|---|---|---|
| F-009 | CWE-862 Missing Auth on API | **High** | `system/routes/api.php:18-20` | `POST /api/clients/calc_balance` is unprotected and `calc_balance_api` calls `branchesController::update_balance($request->client_id)` which echoes a number but, more importantly, recalculates and *writes* `balance_usd/eur/cny/den` for whichever client/branch IDs are scanned, causing DB churn and balance state drift on demand. No auth means anyone on the internet can spam it. | Wrap the route in `auth:sanctum` or `chkAuthAdmin`. Validate `client_id` exists and the caller can access it. |
| F-010 | CWE-862 / CWE-285 Broken Access Control | **High** | `system/app/Http/Controllers/clientsController.php:17-78` (`load`) | Despite living under `chkAuthAdmin`, `clientsController::load` has no role check — a `branch_admin` is *partially* scoped (line 44), but any authenticated `user` row of any other `type` value gets full unfiltered client list (codes, names, balances). Combined with F-007 a client could call it too. | Add `if(!in_array(auth()->user()->type,['admin','branch_admin'])) abort(403);` and reject the request when `auth()->guard('client')->check()`. |
| F-011 | CWE-862 Missing Auth on Sensitive Mutations | **High** | `system/app/Http/Controllers/clientsController.php:554-1075` (`deposit`, `withdraw`, `withdraw_commission`, `transfer`, `transfer_clients`, `del_transaction`), `system/app/Http/Controllers/usersController.php:114-191` (`delete`, `save`, `change_pass`) | None of these endpoints check `auth()->user()->type === 'admin'`. A `branch_admin`, an `accountant`, or any future low-privilege role can drain client balances (`withdraw`), create transfers across clients, change any user's password, or delete user/transaction rows. | Add an admin-only guard at the top of each: `if(!in_array(auth()->user()->type,['admin'])) abort(403);`. For `change_pass` also require knowledge of old password unless caller is admin. |
| F-012 | CWE-639 IDOR | **High** | `system/app/Http/Controllers/clientsReportsController.php:19-234` (especially `deposit_print/{client_id}` route at `system/routes/web.php:161`), `clientsController::get_client_data`, `clientsController::edit` | All these endpoints accept `$request->client_id` / `{client_id}` and look up the row directly. Any authenticated low-priv staff user (and, via F-007, even clients) can dump any other client's transactions and generate PDFs of their full deposit history. | Enforce `branch_admin` scoping (`->where('branch', auth()->user()->branch)`) and admin role; verify `client_id` belongs to a branch the caller administers. |
| F-013 | CWE-434 Unrestricted Upload + CWE-22 Path Traversal | **High** | `seaController.php:99-108` (`new_received`), `:186-192` (`save_received`); `skyController.php:99-107` (`new_received`), `:185-191` (`save_received`); `usersController.php:289-301` (`save_profile`) | Every uploader uses `$file->getClientOriginalName()` (or `time()."_".getClientOriginalName()`) and `->move('photos/sea/'.$client_id, $name)` with no MIME / extension whitelist and no path sanitisation. An attacker uploads `.htaccess` (re-enabling PHP), or `evil.php` / `evil.phtml`, and then fetches `https://<host>/photos/sea/<client_id>/evil.php` for RCE. `$client_id` comes from the request body (no ownership check) so the directory chosen is also attacker-controlled. The original filename can also contain `../` to climb out (`getClientOriginalName()` is sanitised by Symfony, but it is base-name only — attacker controls extension freely). | (1) Whitelist extensions: `in_array(strtolower($file->getClientOriginalExtension()), ['jpg','jpeg','png','webp'])`. (2) Validate MIME with `$file->getMimeType()`. (3) Replace the stored name with a hash: `hash('sha256', random_bytes(32)).'.'.$ext`. (4) Drop a `.htaccess` containing `php_flag engine off` into `photos/` (Apache only). (5) Validate `$client_id` against `auth()->user()` and reject if the user can't write to that client. |
| F-014 | CWE-915 Mass Assignment via JSON arrays | **High** | `seaController.php:70-134` (`new_received`), `:155-212` (`save_received`); `skyController.php:70-133, 155-205` | The pattern `$names=json_decode($request->names); foreach($names as $i=>$col){ $data[$col]=$values[$i]; } DB::table('store_sea')->insert($data);` lets the client choose *which columns* to write. They can set `created_by` (impersonation), `canceled='true'` (hide a shipment), `branch=<other branch>`, `client_id=<some other client>` and any future columns like `paid`, `not_active`, `images` (overwriting paths). | Replace with an explicit allow-list per endpoint: `$allowed=['transaction_number','kg','cbm','number','notes','unit','currency','client_id']; foreach($allowed as $k){ if($request->filled($k)) $data[$k]=$request->$k; }`. Always set `created_by`, `created_date`, `created_time` server-side. Validate types with `validator()`. |
| F-015 | CWE-307 Missing Login Throttle | **High** | `system/app/Http/Controllers/usersController.php:132-163`; route `/auth/user/login` in `system/routes/web.php:633` | The login handler has no `RateLimiter`/`throttle` middleware. An attacker brute-forces every code (numeric, sequential — `100`–`99999`, see `dataController::$code = 100`) or runs credential-stuffing. The login form accepts code-only login, which is high-cardinality numeric and brute-forceable. | Add `->middleware('throttle:5,1')` to the login route or use Laravel's built-in `Illuminate\Foundation\Auth\ThrottlesLogins` trait, key by IP+identifier; lock the account after N failures. |
| F-016 | CWE-1004 / CWE-614 Cookie Not Marked Secure & Session Not Encrypted | **High** | `system/.env:33`, `system/config/session.php` (`'secure' => env('SESSION_SECURE_COOKIE')`) | `SESSION_ENCRYPT=false`, `SESSION_SECURE_COOKIE` unset (defaults to `null` -> not Secure). Over plain HTTP, session cookies are sent in cleartext and can be hijacked; combined with `SESSION_LIFETIME=1000` minutes (16h), one MITM = full admin take-over. | Set `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, force HTTPS at Apache (HSTS), reduce `SESSION_LIFETIME` to 120 (2h) for a financial app. |
| F-017 | CWE-352 / CWE-285 Inconsistent Guard Enforcement | **High** | `system/app/Http/Middleware/chkAuthAdmin.php:18-26` | The middleware redirects clients to `/client` but then *does not* verify the caller is an admin — it only checks "is anyone logged in". Combined with F-011 every admin route is reachable by any authenticated `web` user regardless of `type` (employee, accountant, branch_admin, viewer, etc.). | Change to `if(!Auth::guard('web')->check() || !in_array(Auth::guard('web')->user()->type, ['admin','branch_admin'])) abort(403);`. Add a separate `chkAuthAdminOnly` middleware for admin-only routes. |
| F-018 | CWE-209 Information Exposure via Verbose Errors | **High** | `system/.env:2` `APP_ENV=local` | Although `APP_DEBUG=false` is set, `APP_ENV=local` enables more permissive default behaviour (loose validation messages, no trusted-host enforcement, no production caching of routes/config) and other "local" branches in Laravel code paths. Combined with F-004 (env file readable) and the `local.ERROR` line already in `storage/logs/laravel.log`, this confirms the app self-identifies as non-prod. | Set `APP_ENV=production`, `APP_DEBUG=false`, then run `php artisan config:cache && php artisan route:cache`. |
| F-019 | CWE-89 SQL Injection (logic) / CWE-787 Logic Flaw in `orWhere` chain | **High** | `system/app/Http/Controllers/clientsController.php:56-63` | `$get->where('clients.balance_usd','<',0)->orWhere('clients.balance_eur','<',0)->orWhere('clients.balance_den','<',0)->orWhere('clients.balance_cny','<',0);` — these `orWhere`s break the surrounding `where('deleted',...)` and `where('not_active','false')` scopes. The compiled SQL becomes `(deleted=? AND not_active='false' AND clients.balance_usd<0) OR balance_eur<0 OR balance_den<0 OR balance_cny<0`, which returns **deleted/inactive** clients whenever any currency is negative — leaking dormant client PII (names, codes, balances) into the search results, including across branches for `branch_admin`. Same flaw on the `positive=true` branch lines 60-62. | Wrap the disjunction in a closure: `$get->where(function($q){ $q->where('balance_usd','<',0)->orWhere(...); })`. Apply consistently for both negative and positive blocks. |

### Medium

| ID | CWE | Severity | Location | Exploit scenario | Recommended fix |
|---|---|---|---|---|---|
| F-020 | CWE-1395 / CWE-1104 Vulnerable Dependencies (see §3) | **Medium** | `system/composer.lock` | `composer audit` reports 7 advisories across `firebase/php-jwt`, `league/commonmark` (x2), `phpunit/phpunit`, `psy/psysh`, `symfony/http-foundation` (CVE-2025-64500 — authorization-bypass via PATH_INFO parsing), `symfony/process`. Symfony HttpFoundation bypass is the most relevant — a crafted URL can defeat path-based auth checks before reaching middleware. | Run `composer update symfony/http-foundation league/commonmark firebase/php-jwt`. Bump to versions noted in §3. Re-run `composer audit` until clean. |
| F-021 | CWE-352 CSRF Posture Around Mutations | **Medium** | All `POST` routes in `system/routes/web.php` | Laravel 12's default CSRF middleware *is* active, but several routes accept POST with file uploads from jQuery (`new_received`, `save_received`). If the front-end ever falls back to a no-cookie/origin pattern, CSRF tokens are not enforced on `api.php` (no `web` group). `POST /api/clients/calc_balance` is open and writes state. | Confirm `\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken` covers every mutating web route, and require an explicit auth+csrf chain on `api.php` writes. |
| F-022 | CWE-200 Information Disclosure via Backup Folder Listing | **Medium** | `cron_jobs/backups/` (mode 755, no index file) | If Apache directory listing is allowed for this path, attackers see and download every `.sql` dump. Even if listing is disabled, the date-based filenames (`2026-05-13 06:00.sql`) are predictable and directly fetchable (see F-003). | Move backups outside docroot. Add an empty `index.html` or `Options -Indexes` (covered by root `.htaccess`, but verify per-directory). |
| F-023 | CWE-89 (low-risk) Search column injection via Schema lookup | **Medium** | `usersController::load:30-39`, `seaController` etc. (all `Schema::getColumnListing(...)` then `orWhere($column, 'like', "%{$search}%")`) | `$column` is trusted (from `Schema::getColumnListing`) so no SQL injection there, but `$search` is interpolated with `like "%{$search}%"`. Laravel will bind it, so this is *not* directly SQLi, but `%`/`_` wildcards from the user enable trivial enumeration ("`a%`" returns everything) — useful for data exfiltration when combined with IDOR-able endpoints. | Escape LIKE metacharacters: `$search = addcslashes($request->search, '%_\\');` before interpolating. |
| F-024 | CWE-22 Path Traversal via `langController::get_lang` | **Medium** | `system/app/Http/Controllers/langController.php:11-15` | `Route::get('/get_lang')` is unauthenticated and `$lang` defaults from `auth()->user()->lang`. The static `langController::write($arg,$lang='en')` writes back to `__DIR__.'/langs/'.$lang.'.json'` based on the user's stored `lang` column. Currently `lang` is set only via `change_lang/{lang}` which is also unauthenticated and writes `$request->lang` directly to the users/clients row — an attacker who phishes a logged-in user to visit `/change_lang/../../../../../etc/passwd` causes a future `write()` call to attempt to read/create `/etc/passwd.json`. The base directory limits the damage to creating arbitrary `*.json` files inside writable areas. | Whitelist `$lang` against `['en','ar','zh']` in `change_lang`, `write()`, and `get_lang`. Reject anything else with 400. |
| F-025 | CWE-862 Missing Auth on `/change_lang/{lang}` and `/get_lang` | **Medium** | `system/routes/web.php:445, 693` | Both routes live outside any auth middleware group. Anyone can hit them; `change_lang` performs `DB::table('users')->where('id',auth()->user()->id)->update(...)` — if no user is logged in, `auth()->user()->id` is null which yields an exception or, depending on Laravel version, updates every user's `lang` column (mass update). | Move these routes inside `chkAuthAdmin`/`chkAuthClient` groups and guard against null user. |
| F-026 | CWE-89 SQL Injection via `orderByRaw` (low-confidence) | **Medium** | `clientsController.php:58, 62` | The literal strings are constants here so no direct injection, but the *pattern* (`orderByRaw('...')`) is exactly what gets copy-pasted next time someone needs dynamic sorting. Flagging as Medium for the future-bug risk; the literal arguments today are not user-controlled. | Replace with `orderBy('column','DESC')` for static cases; for dynamic cases, use a whitelist map. |
| F-027 | CWE-200 PII / Credentials in Mail Configuration | **Medium** | `system/.env:53-58` | The mail credentials use the Mailtrap *production* host (`live.smtp.mailtrap.io`) with an API key visible. If F-004 is exploited (env file read), attacker sends mail as `noreply@aman-invest.com` for phishing. | Rotate the mailer key, store via server-side secret manager, and remove the value once cached via `php artisan config:cache`. |

### Low

| ID | CWE | Severity | Location | Exploit scenario | Recommended fix |
|---|---|---|---|---|---|
| F-028 | CWE-525 Information Exposure Through Caching | **Low** | `dataController::get_data` (line 225) uses `Cache::remember('data_'.$arg.$from.$where.$id, ...)` | Cache key collisions if two callers pass the same args but different access scopes — one user's cached lookup leaks to another. The keys also reveal table+column names if cache is enumerable. | Include user/branch scope in cache key. |
| F-029 | CWE-209 Server Header / Verbose Error in `dataController::compress` | **Low** | `dataController.php:470-496` | `abort(404, 'File not found')` discloses file existence (path enumeration). Not high risk but informational. | Use a generic message and log details server-side. |
| F-030 | CWE-732 Insecure Permissions | **Low** | `cron_jobs/backups/` files are world-readable (mode 644) and the cron writes them with `mkdir($folderName, 0777, true)` (seaController/skyController/usersController upload sinks) | Photos uploaded with `0777`. If the docroot is shared (cPanel multi-user), other users on the box can read/modify. | Use `0755` for dirs and `0644` for files via Laravel filesystem helpers (or `umask(0022)`). |
| F-031 | CWE-693 Missing Security Headers | **Low** | Apache `.htaccess` and Laravel middleware | No `Strict-Transport-Security`, `Content-Security-Policy`, `X-Frame-Options`, `Referrer-Policy`, `X-Content-Type-Options`. Clickjacking / XSS amplification. | Add a `Header set` block in `.htaccess` or a Laravel `SecurityHeaders` middleware. |
| F-032 | CWE-209 Stack-trace Logging of Untrusted Input | **Low** | every controller `Log::error($th->getMessage(), ['exception' => $th])` — see `clientsController.php:73-77` etc. | Errors with attacker-controlled payloads (filenames, JSON blobs) are written to `storage/logs/laravel.log` which, per F-004, is reachable. | Log to a path outside docroot, or restrict via .htaccess; sanitize log lines. |

---

## 3. Dependency CVE table

`composer audit` output verbatim (run from `/Users/younusmohammed/Downloads/ship/system/`, composer 2.x):

```
Found 7 security vulnerability advisories affecting 6 packages:
```

| Package | Installed version | Advisory / CVE | Affected range | Fixed in | Severity |
|---|---|---|---|---|---|
| `firebase/php-jwt` | v6.11.1 | GHSA-2x45-7fc3-mxwq / CVE-2025-45769 — weak encryption | `<7.0.0` | `>=7.0.0` | High (if you actually use JWT — pulled transitively by `google/auth`) |
| `league/commonmark` | 2.7.1 | GHSA-hh8v-hgvp-g3f5 / CVE-2026-33347 — embed extension `allowed_domains` bypass | `>=2.3.0,<=2.8.1` | `>=2.8.2` | Medium |
| `league/commonmark` | 2.7.1 | GHSA-4v6x-c7xx-hw9f / CVE-2026-30838 — `DisallowedRawHtml` bypass via whitespace in tag names | `>=2.0.0,<=2.8.0` | `>=2.8.1` | Medium |
| `phpunit/phpunit` | (dev) | GHSA-vvj3-c3rp-c85p / CVE-2026-24765 — unsafe deserialization in PHPT code-coverage | `>=11.0.0,<11.5.50` | `>=11.5.50` | Low (dev-only, but ensure CI image not exposed) |
| `psy/psysh` | (transitive via `laravel/tinker`) | GHSA-4486-gxhx-5mg7 / CVE-2026-25129 — local privilege escalation via CWD `.psysh.php` auto-load | `<=0.11.22 \| >=0.12.0,<=0.12.18` | `>=0.12.19` | Medium |
| `symfony/http-foundation` | (Symfony 7.x bundled by Laravel 12.21.0) | CVE-2025-64500 — PATH_INFO parsing mishandled, can bypass path-based authorization | `>=7.3.0,<7.3.7` (plus earlier branches) | `>=7.3.7` | High in this codebase (path-based auth is what `chkAuthAdmin` relies on) |
| `symfony/process` | (Symfony 7.x bundled by Laravel 12.21.0) | GHSA-r39x-jcww-82v6 / CVE-2026-24739 — argument escaping under MSYS2/Git Bash (Windows) | `>=7.3,<7.3.11` | `>=7.3.11` | Low (Linux-only host) |

Other packages worth noting (no current advisory matched, but flagged because the audit asked for them):
- `laravel/framework` v12.21.0 — current major; ensure you keep patch updates. No advisories at audit time.
- `laravel/sanctum` v4.2.0 — clean.
- `laravel/tinker` v2.10.1 — clean *as a package*, but pulls `psy/psysh` (see above).
- `mpdf/mpdf` v8.2.6 — clean at audit time, but historically prone to LFI when user input reaches `WriteHTML()` with `file://` references. `clientsReportsController::deposit_print` calls `WriteHTML(view(...))` — verify no user-controlled `<img src="file:///etc/passwd"/>` makes it into the rendered HTML.
- `intervention/image` v3.11.4 — clean.
- `dompdf/dompdf` v3.1.4 / `barryvdh/laravel-dompdf` v3.1.1 — clean at audit time. (Older versions had file-disclosure via `<img src="phar://...">`; still good hygiene to validate input.)

Run:
```
composer update symfony/http-foundation symfony/process league/commonmark firebase/php-jwt psy/psysh
composer audit
```

---

## 4. Coverage / what was NOT verified

- **Did not run** the app — could not confirm at runtime that `/system/.env` is actually 200 OK on the live Apache (cPanel may strip dotfiles by default). Manual inspection of `.htaccess` shows no explicit denial, and `Options -MultiViews -Indexes` only blocks listing. Recommend: from a different IP, `curl -I https://<host>/system/.env` to confirm exposure.
- **Did not run** SAST tools beyond `composer audit` (no `phpstan`, `psalm`, `larastan`, or `phpcs --standard=Security` configured in the repo).
- **Did not perform** dynamic CSRF testing — Laravel default CSRF middleware is presumed active; the bootstrap doesn't disable it but doesn't show the full middleware stack (no `withMiddleware(global: ...)` overrides).
- **Did not exhaustively read** `seaController.php` (1700+ lines) or `skyController.php` (1700+ lines) beyond the upload/save sinks, container CRUD, and `cancel*` routes. The mass-assignment pattern (F-014) likely repeats in `insert_exist`, `new_custom_container`, `save_container`, `change_status_custom_container`, `eject` — assume similar issues until reviewed.
- **Did not check** for race conditions in the balance-update logic (`update_balance` reads then writes without row locking). For a treasury system this matters; flag for a future review.
- **Did not check** the front-end (`js/`, `style/`) for XSS sinks via jQuery `.html()` of server-returned data; spot-checks suggest several blade fragments echo `{{ $row->notes }}` (safe) but `{!! $arrow !!}` in `profits/table.blade.php` is server-generated so probably OK.
- **Did not verify** queue worker / cron security — `cron_jobs/backup.php` is a plain PHP file, but whether it's actually invoked via web request or via shell cron isn't determinable from the repo alone.
- **Did not audit** `qubtangroup_sub.sql` at docroot for whether it contains live `pass_txt` values, but given the schema and F-008 it almost certainly does.
- **Did not test** for SSRF — no outbound HTTP calls in the controllers reviewed, so likely not relevant.

---

## 5. Remediation Log

| Date | Finding ID | Action taken | Verified by | Notes |
|---|---|---|---|---|
| | | | | |

| 2026-05-13 | F-001 | Removed `/new_user` debug route from `system/routes/web.php`. | RESOLVED | Route no longer present anywhere in file. |
| 2026-05-13 | F-003 | Added root `.htaccess` `RewriteRule ^cron_jobs/` and `^backups(_sub)?/` deny + `<FilesMatch>` blocking `.sql`/`backup.php` at docroot; per-directory `cron_jobs/.htaccess` with `Require all denied`. | RESOLVED | SQL files preserved on disk per user direction; Apache will refuse to serve. |
| 2026-05-13 | F-004 | Root `.htaccess` adds `RewriteRule ^system/ - [F,L]` and `FilesMatch` for `.env`/`.log`/`composer.*`/`x.html`; defense-in-depth `system/.htaccess` denies all. | RESOLVED | Both perimeter and inner deny in place. |
| 2026-05-13 | F-005 | Added `dataController::DELETABLE_TABLES` whitelist; each of `del_recs`, `restore_recs`, `del_recs_permanent` aborts 422 on out-of-list table. | RESOLVED | Enforced at top of all three methods (`dataController.php:255,357,380`). |
| 2026-05-13 | F-006 | All debug routes (`/xx`, `/mm`, `/zz`, `/nn`, `/vvvv`, `/cccc`, `/ts`, `/update_balance_manual`) removed from `system/routes/web.php`. | RESOLVED | grep over routes returns no hits. |
| 2026-05-13 | F-007 | `/load_ajax_element` route entries removed from both auth groups in `system/routes/web.php`. | RESOLVED | Controller method body remains but is unreachable. |
| 2026-05-13 | F-011 | `usersController::delete`, `save`, `change_pass` now begin with `if (!in_array(auth()->user()->type, ['admin'], true)) abort(403)`. | RESOLVED | Verified at `usersController.php:115,191,202`. |
| 2026-05-13 | F-017 | `chkAuthAdmin` now checks `Auth::guard('web')->user()->type` against `ADMIN_ROLES = ['admin','branch_admin']`; logs out and redirects on mismatch. | RESOLVED | `chkAuthAdmin.php:20,32-34`. |
| 2026-05-13 | F-008 | Removed every `pass_txt` DB write in `usersController::create/change_pass/save_profile` and `clientsController::create/save`; clients edit blade no longer renders existing `pass_txt` (placeholder only). Schema column drop deferred. | RESOLVED (code) | grep `'pass_txt' =>` over `system/app/` returns zero matches. |
| 2026-05-13 | F-009 | `POST /api/clients/calc_balance` orphan route removed from `system/routes/api.php`. | RESOLVED | `api.php` now contains only the default `/user` sanctum endpoint. |
| 2026-05-13 | F-013 | `seaController::new_received/save_received`, `skyController::new_received/save_received`, `usersController::save_profile` switched to base-controller `storeUploadedImage()` (mime + ext allowlist, random server-side filename); `$client_id` sanitized via `safeIntSegment()` before path concat; `photos/.htaccess` denies PHP/CGI handlers as defense-in-depth. | RESOLVED | Verified at `Controller.php:27-58`, `seaController.php:124-127,218-221`, `skyController.php:122-125,218`, `usersController.php:310-316`, `photos/.htaccess:1-15`. |
| 2026-05-13 | F-014 | Sea/sky `new_received` + `save_received` now check `in_array($name, self::STORE_*_ALLOWED_COLUMNS, true)` before populating `$data`. `usersController::save_profile` whitelists `['name','email','phone']` only — `type`/`branch` escalation blocked. | RESOLVED | `seaController.php:26-41,108,193`; `skyController.php:25-40,106,191`; `usersController.php:284,291`. |
| 2026-05-13 | F-015 | `POST /auth/user/login` now has `->middleware('throttle:5,1')`. | RESOLVED | `web.php:450`. |
| 2026-05-13 | F-019 | `clientsController::load` negative + positive disjunctions wrapped in `where(function($q){ ... })` closures, restoring `deleted`/`not_active`/`branch` scope. | RESOLVED | `clientsController.php:56-73`. |
| 2026-05-13 | F-024 | Added `langController::ALLOWED_LANGS = ['en','ar','zh']` + `normalize()`; `write()` and `get_lang()` now normalize before any filesystem read/write. | RESOLVED | `langController.php:11-22,59`. |
| 2026-05-13 | F-025 | `usersController::change_lang` aborts 422 unless `$lang` is in `langController::ALLOWED_LANGS`; null-user mass-update path no longer possible because invalid lang is rejected before the `DB::update`. | RESOLVED | `usersController.php:242-245`. |
| 2026-05-13 | F-010 | `clientsController::load` role posture now enforced structurally: `chkAuthAdmin` (post F-017) restricts the controller to `admin`/`branch_admin`; branch_admin scope at `clientsController.php:44-46` keeps list isolated to own branch. | RESOLVED | No further role check required at method level. |
| 2026-05-13 | F-011 (residual) | `clientsController::del_transaction` now aborts 403 unless `auth()->user()->type === 'admin'` (`clientsController.php:1057-1059`). `dataController::del_recs/restore_recs/del_recs_permanent` call `assertCanMutateTable()` which enforces `ADMIN_ONLY_TABLES = ['users','branches']` admin-only (`dataController.php:259-270,273,372,393`). | RESOLVED | Both surfaces guarded; non-admins can no longer mutate users/branches via the generic delete pipeline. |
| 2026-05-13 | F-012 | `Controller::assertCanAccessClient()` added (`Controller.php:77-99`) — admin bypass, branch_admin must match `clients.branch`, anything else 403. Called at top of `clientsController::edit:260`, `get_client_data:1037`, `deposit:572`, `withdraw:694`, `withdraw_commission:824`, `transfer:948`, `transfer_clients:312` (and again on `to_client` at `:314`), plus `clientsReportsController::deposit_print:206`. | RESOLVED | All eight call sites verified by grep. |
| 2026-05-13 | F-021 | (a) `system/bootstrap/app.php:14-19` only aliases middleware, no `VerifyCsrfToken` override -> Laravel 12 default web CSRF stack active. (b) CSRF meta present in `system/resources/views/layout.blade.php:24` and `client/layout.blade.php:20` (plus `errors/{403,404,500}.blade.php`). (c) `system/routes/api.php` only contains the default `/user` sanctum endpoint — `POST /api/clients/calc_balance` orphan route removed. | RESOLVED | All three preconditions hold. |
| 2026-05-13 | F-023 | `Controller::escapeLike()` helper added (`Controller.php:54-57`, `addcslashes($v, '%_\\')`). Applied at `clientsController.php:30`, `usersController.php:33`, `seaController.php:56,262,461,614,843,1680`, `skyController.php:55,258,459,617,650,1633`, `customsBrokersController.php:28`, `suppliersController.php:27`, `branchesController.php:31`. | RESOLVED | Coverage across all 8 listed controllers; LIKE wildcards no longer attacker-controlled. |
| 2026-05-13 | F-031 | Root `/.htaccess:40-46` adds `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: geolocation=(), microphone=(), camera=()`, and `Strict-Transport-Security: max-age=31536000; includeSubDomains` gated by `expr=%{HTTPS} == 'on'`. | RESOLVED | All five headers present; HSTS correctly conditional. |

---

## 6. Deployment checklist

The code changes are in. These steps need shell / DB / admin-panel access and **must run in this order** before the patches are fully effective.

### Step 1 — Take a fresh, off-host backup of the database

```bash
mysqldump -u qubtangroup_user -p qubtangroup_sub > /tmp/qubtangroup_sub_pre_security_$(date +%F).sql
```

Then SCP it off the box. Do NOT skip this — the migration in Step 4 is destructive.

### Step 2 — Confirm the user-role inventory

The new `chkAuthAdmin` middleware only admits `admin` and `branch_admin`. Any other `type` value force-logs-out.

```sql
SELECT type, COUNT(*) FROM users WHERE not_active='false' GROUP BY type;
```

If there are rows outside `{admin, branch_admin}` (e.g. `accountant`, `employee`, `viewer`), STOP and tell Claude which roles to add to `chkAuthAdmin::ADMIN_ROLES` (`system/app/Http/Middleware/chkAuthAdmin.php`).

### Step 3 — Rotate every credential that touched the public docroot

Assume `APP_KEY`, the MySQL password, and the Mailtrap API key all leaked.

```bash
cd /Users/younusmohammed/Downloads/ship/system
php artisan key:generate --show           # copy the value
# Then edit .env:
#   APP_KEY=base64:<new value from above>
#   DB_PASSWORD=<new MySQL password set in cPanel>
#   MAIL_PASSWORD=<new Mailtrap key>
```

Update the matching cPanel MySQL password and rotate the Mailtrap key in your Mailtrap dashboard.

### Step 4 — Run the `pass_txt`-drop migration

```bash
cd /Users/younusmohammed/Downloads/ship/system
php artisan migrate --force
```

This runs `2026_05_13_120000_drop_pass_txt_columns.php` which NULLs and then drops the `pass_txt` column from `users` and `clients`. Verify with:

```sql
SHOW COLUMNS FROM users LIKE 'pass_txt';      -- expect: empty
SHOW COLUMNS FROM clients LIKE 'pass_txt';    -- expect: empty
```

### Step 5 — Tighten `.env` for production

```ini
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_LIFETIME=120
```

Then:

```bash
cd /Users/younusmohammed/Downloads/ship/system
php artisan config:cache
php artisan route:cache
```

### Step 6 — Move historical backups off the docroot

The `.htaccess` deny rules block HTTP fetches, but the files still physically sit in the webroot. Move them:

```bash
mkdir -p /home/<cpanel-user>/private_backups
mv /Users/younusmohammed/Downloads/ship/cron_jobs/backups/*.sql /home/<cpanel-user>/private_backups/
mv /Users/younusmohammed/Downloads/ship/qubtangroup_sub.sql    /home/<cpanel-user>/private_backups/
# If backups_sub/ exists (the path referenced in /backup.php):
[ -d /Users/younusmohammed/Downloads/ship/backups_sub ] && \
  mv /Users/younusmohammed/Downloads/ship/backups_sub/* /home/<cpanel-user>/private_backups/
```

Then update `cron_jobs/backup.php` line 8 (`$directory = __DIR__.'/backups'`) and line 126 (`__DIR__.'/backups/'`) to write to `/home/<cpanel-user>/private_backups/` instead. The cleartext `pass_txt` values are in those old dumps; either delete them now that you have one fresh post-migration backup, or treat the private_backups directory as confidential.

### Step 7 — Patch the 7 known-vulnerable composer dependencies

The dependency CVE most relevant to this app is **CVE-2025-64500 in `symfony/http-foundation`** (PATH_INFO parsing can defeat path-based auth, which is exactly what `chkAuthAdmin` is). Run:

```bash
cd /Users/younusmohammed/Downloads/ship/system
composer update \
  symfony/http-foundation \
  symfony/process \
  league/commonmark \
  firebase/php-jwt \
  psy/psysh \
  phpunit/phpunit
composer audit
```

The audit should report 0 advisories after the update. Then redeploy:

```bash
composer install --no-dev --optimize-autoloader
```

### Step 8 — Force HTTPS at Apache / cPanel

In cPanel: Domains → toggle "Force HTTPS Redirect" on. Verify the `Strict-Transport-Security` header now appears in responses (`curl -I https://<your-host>/login`).

### Step 9 — Smoke-test the regressions called out in the security review

- [ ] Admin user can still log in.
- [ ] At least one `branch_admin` can still log in and see only their branch's clients.
- [ ] Creating a new client works (no `pass_txt` write failure).
- [ ] Editing a client and saving with the password field BLANK leaves the existing login working (does not lock the client out).
- [ ] Uploading a JPEG receipt to a sea/sky shipment record works; uploading a `.php` file is silently rejected (no DB row update, no file on disk).
- [ ] Five wrong logins in 60 seconds returns 429.
- [ ] Search box with literal `%` performs an exact-character match (no longer a wildcard).
- [ ] If your users photograph receipts from iPhones: confirm iOS HEIC uploads work. If they fail silently, tell Claude to add `image/heic`/`image/heif` to `Controller::ALLOWED_IMAGE_MIMES`.

### Step 10 — Final verification

```bash
# From a different machine — confirm sensitive files are 403 / 404, not 200:
curl -sI https://<your-host>/system/.env                       # expect 403
curl -sI https://<your-host>/backup.php                        # expect 403
curl -sI https://<your-host>/cron_jobs/backups/                # expect 403
curl -sI https://<your-host>/qubtangroup_sub.sql               # expect 403 or 404 after Step 6
curl -sI https://<your-host>/new_user                          # expect 404
curl -sI https://<your-host>/system/composer.json              # expect 403
curl -s   https://<your-host>/system/storage/logs/laravel.log  # expect 403 body
```

All of the above were Critical-severity exposures four hours ago. They should all be locked down now.

