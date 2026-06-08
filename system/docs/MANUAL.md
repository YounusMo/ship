# ShipFlow — System Manual

A complete reference for the ShipFlow / Mataz Trading platform.
Covers architecture, every module on the web admin, both mobile apps, the accounting model, the tracking model, APIs, deployment, and operations.

If you only need the day-to-day "how do I do X" guide for a specific role, read `USER_GUIDE_EN.md` (or `USER_GUIDE_AR.md`). This manual is the deeper "how does it all fit together" reference.

---

## Table of contents

1. [System overview](#1-system-overview)
2. [Architecture](#2-architecture)
3. [Technology stack](#3-technology-stack)
4. [Installation and deployment](#4-installation-and-deployment)
5. [Configuration and settings](#5-configuration-and-settings)
6. [Authentication and user types](#6-authentication-and-user-types)
7. [Web admin — module reference](#7-web-admin--module-reference)
8. [Client web portal](#8-client-web-portal)
9. [Employee mobile app](#9-employee-mobile-app)
10. [Client mobile app](#10-client-mobile-app)
11. [The accounting model](#11-the-accounting-model)
12. [The tracking model](#12-the-tracking-model)
13. [Sourcing and purchases](#13-sourcing-and-purchases)
14. [Notifications (FCM)](#14-notifications-fcm)
15. [Audit log](#15-audit-log)
16. [APIs](#16-apis)
17. [Operations playbook](#17-operations-playbook)
18. [Troubleshooting](#18-troubleshooting)
19. [Glossary](#19-glossary)

---

## 1) System overview

ShipFlow is the internal operating system for **Mataz Trading** — a multi-branch shipping and trading business with offices in Tripoli, Misrata, Benghazi, and Guangzhou. It does five things in one place:

1. **Client management** — clients, multi-currency balances, deposits, withdrawals, statements.
2. **Freight operations** — air (sky) and sea shipments, containers, packing lists, delivery notes.
3. **Treasury** — branch cash, FX, transfers, reconciliation, cash counts.
4. **Accounting** — double-entry journal, trial balance, P&L, balance sheet, cash flow, aging reports.
5. **Sourcing / Purchases** — proformas, quotes, change requests, payment plans, buyer custody, supplier reliability.

Plus end-to-end **shipment tracking** built on QR stickers scanned by staff in the field, fused with carrier events from **ShipsGo**, surfaced to clients in their mobile app and web portal.

The currencies supported throughout are **USD, EUR, LYD, CNY**. Every balance, transaction, and report is currency-aware.

---

## 2) Architecture

### High-level

```
┌──────────────────────┐   ┌──────────────────────┐   ┌──────────────────────┐
│  Web admin           │   │ Employee mobile app  │   │ Client mobile app    │
│  (Blade + jQuery)    │   │ (Flutter)            │   │ (Flutter)            │
│  Staff & client web  │   │ Sanctum tokens       │   │ Sanctum tokens       │
└─────────┬────────────┘   └──────────┬───────────┘   └──────────┬───────────┘
          │                           │                          │
          │  session cookies          │  /api/v1/employee/*      │  /api/*
          │                           │                          │
          └───────────────┬───────────┴──────────────────────────┘
                          │
                  ┌───────▼────────┐    ┌─────────────────┐
                  │ Laravel 12     │◀──▶│ MySQL           │
                  │ system/        │    │ database        │
                  └───────┬────────┘    └─────────────────┘
                          │
        ┌─────────────────┼─────────────────────────────┐
        │                 │                             │
   ┌────▼────┐      ┌─────▼─────┐               ┌───────▼────────┐
   │ Queue   │      │ ShipsGo   │               │ Firebase Cloud │
   │ worker  │      │ webhooks  │               │ Messaging      │
   └─────────┘      └───────────┘               └────────────────┘
```

### Three client surfaces, one backend

| Surface | Folder | Auth | Audience |
|---------|--------|------|----------|
| Web admin & client portal | `system/` (Laravel Blade) | Session cookies (`chkAuthAdmin`, `chkAuthClient`) | Staff and clients on a computer |
| Employee mobile app | `mobile_employee/` (Flutter) | Sanctum personal access tokens with `branch:N` abilities | Branch staff scanning QR stickers |
| Client mobile app | `mobile/` (Flutter) | Sanctum personal access tokens | Clients on their phone |

The Laravel backend is the single source of truth. Both Flutter apps talk only to `/api/*` and `/api/v1/employee/*`. Web admin/client portal pages render server-side Blade.

### Two domain modules

`app/Modules/` contains two namespaced modules that own their own routes, controllers, services, models, migrations:

- **`Tracking`** — shipment tracking, branch staff, stickers, scan events, ShipsGo webhook.
- **`Purchases`** — buyer accounts, purchase orders, accounting integration for the sourcing flow.

Everything else lives in classic Laravel locations under `app/Http/Controllers/`, `app/Models/`, etc.

---

## 3) Technology stack

| Layer | Tech |
|-------|------|
| Backend | PHP **8.2+**, Laravel **12**, Sanctum 4 |
| Database | MySQL 8 (`utf8mb4_unicode_ci`) |
| PDF generation | mpdf |
| QR codes | endroid/qr-code |
| Barcodes | picqer/php-barcode-generator |
| Images | Intervention Image 3 |
| Frontend (admin) | Blade + jQuery, no SPA framework |
| Mobile | Flutter (Dart 3+) — Riverpod 2 state, Dio HTTP, go_router, firebase_messaging |
| Push | Firebase Cloud Messaging |
| Carrier tracking | ShipsGo v2 (webhook + REST polling) |
| Testing | PHPUnit 11, Mockery 1.6, fakerphp |
| Tooling | Laravel Pail, Pint, Sail |

PHP 8.2+ is required by `composer.json`.

---

## 4) Installation and deployment

### Local development

```bash
# 1. clone the repo, cd system/
composer install
cp .env.example .env
php artisan key:generate

# 2. point .env at a MySQL database
#    DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306
#    DB_DATABASE=ship_system DB_USERNAME=ship_user DB_PASSWORD=...

# 3. migrate & seed
php artisan migrate
php artisan db:seed   # seeds Chart of Accounts + tracking branches

# 4. run the dev server
php artisan serve --host=127.0.0.1 --port=8002

# 5. (optional, required for sourcing/purchases jobs and FCM push)
php artisan queue:work --queue=default
```

Default web URL: `http://127.0.0.1:8002`. The mobile apps point at this with `--dart-define=API_BASE_URL=...`.

### Production

The web app is a standard Laravel deployment:

- Apache or nginx with `public/` as document root.
- PHP-FPM 8.2+.
- A persistent `php artisan queue:work` (systemd, supervisor, or similar) for the `default` queue — required for FCM push, sourcing reminders, ShipsGo webhook processing.
- Laravel scheduler (`* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`) for periodic reminders and tracking reconcile.
- HTTPS in front of everything. Sanctum tokens cannot safely cross the wire in clear.

Key environment variables (beyond Laravel defaults):

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Used in proforma emails (public portal links). Must match the public URL. |
| `FCM_PROJECT_ID` | Firebase project for push notifications. |
| `FCM_CREDENTIALS_PATH` | Path to the service-account JSON granted FCM send permission. |
| `SHIPSGO_API_KEY` | ShipsGo v2 user token. |
| `SHIPSGO_WEBHOOK_SECRET` | Shared secret for verifying inbound webhook payloads. |

### Mobile app builds

Both Flutter apps follow the same flow:

```bash
cd mobile          # or mobile_employee
flutter pub get
flutter run --dart-define=API_BASE_URL=https://api.example.com
```

For production builds: build a thin wrapper script that calls `flutter build apk --release --dart-define=API_BASE_URL=...` and `flutter build ios --release --dart-define=API_BASE_URL=...` per environment.

The client app additionally needs Firebase config files (`google-services.json`, `GoogleService-Info.plist`) dropped into the platform folders — both are `.gitignored`, never commit them.

---

## 5) Configuration and settings

### `.env` (server-side)

Standard Laravel `.env`. Key entries used by ShipFlow:

```ini
APP_NAME="Ship Flow"
APP_ENV=production
APP_URL=https://app.example.com

DB_CONNECTION=mysql
DB_HOST=...
DB_DATABASE=ship_system
DB_USERNAME=ship_user
DB_PASSWORD=...

QUEUE_CONNECTION=database

FCM_PROJECT_ID=...
FCM_CREDENTIALS_PATH=/etc/shipflow/fcm.json

SHIPSGO_API_KEY=...
SHIPSGO_WEBHOOK_SECRET=...
```

### `system/app/Http/Controllers/settings.json` (operator-facing settings)

A JSON file edited through the Settings UI. The fields and what they control:

| Field | Effect |
|-------|--------|
| `timezone` | Used by the journal and reports for "today" boundaries. |
| `logo`, `company_name`, `address`, `phone`, `email`, `commercial_registry`, `tax_id` | Header on PDFs (receipts, P&L, balance sheet, proformas). |
| `receipt_footer` | Footer on every printed receipt. |
| `tracking_prefix` | Prefix used when generating per-piece tracking codes. |
| `print_pin_hash` | Bcrypt of a PIN required to mass-print delivery notes and packing lists. |
| `client_transactions_default_pending` | If `true`, new client deposits/withdrawals start in **Pending** state and need approval. |
| `proforma_email_subject`, `proforma_email_body`, `proforma_reminder_subject`, `proforma_reminder_body` | Templates for proforma emails. Placeholders: `{number}`, `{client}`, `{company}`, `{link}`, `{total}`. |
| `currency_eur`, `currency_den`, `currency_cny` | Default FX rates relative to USD. Used as fallback if FX Rate History has nothing recent. |

The Settings UI also writes to other config files (`config/purchases.php` and DB tables); the JSON above is just the legacy core.

---

## 6) Authentication and user types

### Three middleware lanes

| Middleware | Where it lives | Who can pass |
|-----------|----------------|--------------|
| `chkAuthAdmin` | `app/Http/Middleware/` | Web admin routes — staff users (`users` table). |
| `chkAuthClient` | `app/Http/Middleware/` | Client portal routes — client logins. |
| `auth:sanctum` | Laravel core | All `/api/*` routes — token-based, for both mobile apps. |
| `auth:sanctum` + `EnforceBranchScope` | `app/Modules/Tracking/Http/Middleware/` | Employee scan endpoints — token must carry the `branch:N` ability for the branch being acted on. |

### Web admin users

- Stored in `users` table (vanilla Laravel `email`, `password`, `name`).
- Login via `/login` → `POST /auth/user/login`.
- There is no role column; every authenticated web admin user has full admin access. Role-based access control on the web is by convention, not enforced in code. Be deliberate about who you give a web admin account to.
- Create / edit / delete via `/users` (admin only).

### Branch staff roles (for the employee mobile app)

Stored in `branch_staff` (one row per `(branch, user)` pair). Roles:

| Role | Typical responsibilities |
|------|-------------------------|
| `MANAGER` | Branch oversight, can scan all event types, can manage staff in the branch UI. |
| `RECEIVER` | Frontline: scan incoming goods, register at-hub / at-branch events. |
| `COURIER` | Out-for-delivery: scan during hand-off to the customer. |
| `AUDITOR` | Read-only audit of stickers and queue; no financially sensitive scan events. |

A single user can have **multiple branch assignments with different roles**. The employee app shows a dropdown when they sign in; they pick which branch they're acting under for this session.

### Sanctum abilities — the `branch:N` mechanism

When an employee logs in, the `AuthController` (under `app/Modules/Tracking/Http/Controllers/Employee/`) issues a Sanctum token carrying an ability per active branch assignment, e.g. `branch:3 branch:7`. Every scan request runs through `EnforceBranchScope`, which checks the requested branch_id matches an ability on the token.

This is why staff can't accidentally register a "received at branch" event in a branch they don't belong to — the token literally lacks the ability.

### Clients

- Stored in `clients` table — code, name, branch_id, per-currency balances.
- Login uses either the **client code** (e.g. `101`) or **email**. Password is set by the branch manager (no self-serve reset).
- The same login works on the **web portal** and the **mobile client app**. The mobile app gets a Sanctum token; the web portal gets a session cookie.

### Admin password reset (for ops engineers)

```bash
php artisan tinker --execute="\$u = \App\Models\User::where('email','admin@example.com')->first(); \$u->password = bcrypt('NewPass!'); \$u->save();"
```

---

## 7) Web admin — module reference

The sidebar groups everything under five headings: **(implicit)**, **Shipping**, **Company**, **Data**, **Insights**, **Accounting**, **System**. Every page below is a server-rendered Blade view with jQuery for table interactions.

### 7.1 Dashboard (`/`)

The first screen after login. Shows:

- Total clients, plus the count of active branches.
- Per-currency totals across all clients (USD, EUR, LYD, CNY).
- Pending approvals counter — clicks through to client transactions waiting for review (only relevant if `client_transactions_default_pending` is true).
- Today's deposits and withdrawals (count of approved transactions).
- "Recent activity" — last N audit log rows.

Top-right has language toggle (EN / AR / ZH) and a logout icon.

### 7.2 Clients (`/clients/all`)

The master list of client accounts.

**Columns:** code, name, email, phone, type (Person / Company), branch, balance per currency (USD, LYD, EUR, RMB), created at, actions.

**Filters at top right:**
- **Positive** — clients with positive net balance (we owe them, they have deposits with us).
- **Negative** — clients with negative balance (they owe us).
- **Pending** — clients with pending transactions awaiting approval.

**Per-row actions:**
- **Edit** — opens the client profile modal.
  - General tab: code, name, phone, email, type, branch.
  - Transactions tab: full ledger. Buttons: **Deposit**, **Withdraw**, **Withdraw commission**, **Transfer** (between own currencies), **Transfer to client** (to another client).
- **Reports** — opens the reports dropdown:
  - **Statement** — full ledger PDF.
  - **Deposit / Withdraw / Transfer / Commission** receipts.
  - **Exp report** — exposure report.
  - **Pending** — show transactions awaiting approval.
  - **Approve / Reject** pending transactions.

**Routes that back this page:**
- `POST /clients/load` — paginated list (DataTables-style).
- `POST /clients/create`, `/edit`, `/save`.
- `POST /clients/deposit`, `/withdraw`, `/withdraw_commission`.
- `POST /clients/transfer`, `/transfer_client`.
- `POST /clients/del_transaction` — soft delete a transaction (reversible).
- `POST /clients/get_client_data`, `/get_code` — autocomplete helpers.

### 7.3 Air Freight (`/shipping/sky`)

Single screen with tab filters across the top:

| Tab | Meaning |
|-----|---------|
| **Received** | Shipments received at the hub / Guangzhou; not yet shipped out. |
| **Inside** | Shipments inside an outbound air container that has departed but not yet been opened. |
| **Outside** | Shipments delivered / at destination branch, ready for client. |
| **Trips** | Air trip-level view (container = trip). |
| **Canceled received** | Cancelled at the received stage. |
| **Canceled trips** | Cancelled trips. |

**Creating a received air shipment** (`POST /shipping/sky/new_received` → `save_received`):
- Client code (auto-completes the name).
- Origin country.
- Type (piece / box / parcel), category (A1, A2, B, …), weight (kg), CBM, number of pieces.
- Receipt mode: with receipt / without receipt.
- Brand, notes.

**Moving shipments through stages:**
- `add_link` / `insert_exist` — link a received shipment into an air container.
- `eject` / `get_eject_modal` — remove a shipment from a container before it ships.
- `change_status` — mark inside → outside, etc.
- `create_container`, `save_container`, `new_custom_container` — container CRUD.
- `cancel`, `cancel_container`, `cancel_in_container` — cancellation paths at every level.

**Printing:**
- `print_container` — container manifest.
- `print_packing_list` — packing list with per-piece detail.
- `print_delivery` — delivery note. Requires the PIN from `settings.json:print_pin_hash`.

**Per-shipment financial actions:**
- `withdraw_custom_broker` — bill the customs broker against this shipment.
- `company/sky_withdraw` — record an internal sky-related withdrawal.

### 7.4 Sea Freight (`/shipping/sea`)

Same shape as Air Freight, plus:

- Container number is mandatory and tracked.
- Linked to **Shipping Lines** (suppliers).
- Containers can carry tracking events from ShipsGo (see [12) The tracking model](#12-the-tracking-model)).

Route set is identical: `new_received`, `save_received`, `add_link`, `insert_exist`, `eject`, `change_status[_custom_container]`, `create_container`, `save_container`, `cancel*`, `print_*`, `withdraw_custom_broker`. Plus all the `load_*` paged loaders (`load_received`, `load_inside`, `load_outside`, `load_containers`, `load_canceled`, `load_canceled_containers`).

### 7.5 Treasury (`/treasury`)

The cash-on-hand view per branch and currency.

- `POST /treasury/load` — branch list with per-currency cash totals.
- `POST /treasury/load_balance` — per-branch detail with movement history.
- `POST /treasury/get_totals` — grand totals.

Branch-level operations are routed via `branchesController`:
- `POST /company/deposit_branch` — deposit cash into a branch's till.
- `POST /company/add_expenses` — record an operating expense at a branch.
- `POST /company/transfer_branch` — move cash between branches (with FX conversion when currencies differ).
- `POST /company/fix_branch` — adjust a branch's balance (with mandatory note, audit-logged).
- `POST /company/deposit_commission` — receive commission revenue at a branch.

### 7.6 Accounting

Eighteen pages, all live under `accountingController` and `journalController`. The list:

| Page | Route | What it does |
|------|-------|--------------|
| Trial Balance | `/accounting/journal-trial-balance` | DR vs CR per currency, per account. The truth check. |
| Journal Entries | `/accounting/journal-entries` | Browse posted entries, with detail and reverse. |
| Drift Detector | `/accounting/drift` | Catches divergences between stored balances and what the journal says. |
| Chart of Accounts | `/accounting/chart` | The CoA — accounts, codes, types, normals. |
| Profit & Loss (PDF) | `/accounting/profit-loss` | mpdf-rendered P&L statement. |
| Balance Sheet (PDF) | `/accounting/balance-sheet` | mpdf-rendered balance sheet. |
| Cash Flow (PDF) | `/accounting/cash-flow` | mpdf-rendered cash flow statement. |
| Daily Journal | `/accounting/journal` | Day-by-day journal browser (HTML). |
| Client Aging | `/accounting/ar-aging` | AR + client deposits aged into 0–30, 31–60, 61–90, 91–180, 180+. |
| Supplier Aging | `/accounting/supplier-aging` | Same buckets for shipping lines. |
| Broker Aging | `/accounting/broker-aging` | Same buckets for customs brokers. |
| Accounting Periods | `/accounting/periods` | List periods, close / reopen. |
| Cash Count | `/accounting/cash-counts` | Physical cash count entry. `POST .../adjust` writes a settling journal entry for any difference. |
| Treasury by branch | `/accounting/treasury-by-branch` | Current cash per branch and currency. |
| FX Rate History | `/accounting/fx-history` | All recorded FX rates, by date. |
| Prepayments | `/accounting/prepayments` | Advances paid to suppliers / brokers, with apply mechanism. |
| Owners | `/accounting/owners` | Owners' equity contributions and withdrawals. |
| Owners Ledger | `/accounting/owners-ledger` | Detail view of equity movements. |

**Critical actions:**
- `POST /accounting/journal-entries/{id}/reverse` — reverse a posted journal entry. Always preferred over deletion.
- `POST /accounting/periods/{id}/close` — close a period. After this, no entries with a posting date inside the period can be edited.
- `POST /accounting/periods/{id}/reopen` — reverse the close (requires admin discretion).
- `POST /accounting/cash-counts/{id}/adjust` — settle a counted-vs-recorded difference.
- `POST /accounting/prepayments/register` and `.../{id}/apply` — register a prepayment and later apply it against a real expense.

### 7.7 Revenue / Profits (`/profits`)

The profitability dashboard. Aggregates revenue from completed sea trips, completed air trips, and sourcing margin into a single view.

- `POST /profits/load` — paginated list.
- `GET /profits/container/{type}/{id}` — per-container profit breakdown.

### 7.8 Matching (`/matching`)

Reconciliation between client deposits and the cash actually held in branches. Catches "client claims they paid; we don't see it" or "cash arrived but no client tagged" mismatches.

- `POST /matching/load` — list of unmatched transactions.

### 7.9 Old Balance Archive (`/old_balance_archive`)

The frozen opening balances from the system's go-live. Useful for "what was X's balance on day zero?" questions.

- `POST /old_balance_archive/load` — paged list.

### 7.10 Branches (`/branches/all`)

Branch master data. Used by every other page that needs a `branch_id`.

- Fields: English name, Arabic name, code, address, timezone.
- The `tracking_branches` table (used by the employee app) joins to this via the same id.

### 7.11 Shipping Lines / Suppliers (`/suppliers`)

The master list of sea/air shipping lines you pay for transport.

- Per-supplier fields: name, default currency, opening balance, contact.
- Transactions: deposits (advance payment), `POST /suppliers/deposit`.
- Reports: per-supplier statement via `POST /suppliers/reports`.

**Important accounting note:** supplier deposits sit in account `1200 — Prepaid to suppliers` (asset). They are NOT expensed when paid. They convert to expense only when the supplier delivers (per-container settlement).

### 7.12 Customs clearance / Brokers (`/customs_brokers`)

Same shape as Suppliers but for customs brokers.

- Deposits go to `1300 — Prepaid to customs brokers`.
- Like supplier deposits, they remain assets until consumed by a specific clearance.

### 7.13 Sourcing requests (`/sourcing`)

The biggest module on the system — a complete proforma-to-delivery sourcing workflow with public client portal, change requests, payment plans, and document management.

Sub-pages:

| Path | Purpose |
|------|---------|
| `/sourcing` | List of all sourcing requests. |
| `/sourcing/board` | Kanban board across statuses. |
| `/sourcing/dashboard` | Operational KPIs. |
| `/sourcing/funnel` | Conversion funnel. |
| `/sourcing/catalog` | Reusable item catalog. |
| `/sourcing/catalog/manage` | CRUD on catalog items. |
| `/sourcing/commissions` | Commission report per period. |
| `/sourcing/payments` | Open balances (what clients owe across proformas). |
| `/sourcing/insights/suppliers` | Supplier reliability ranking from sourcing history. |
| `/sourcing/{id}` | Single request detail. |
| `/sourcing/{id}/pdf` | Render proforma PDF. |
| `/sourcing/{id}/profit` | Per-request profit dashboard. |
| `/sourcing/{id}/diff` | Version diff between proforma versions. |
| `/sourcing/{id}/handoff/{kind}` | Handoff form (to shipping, to fulfillment). |

**Public client portal** (no login required, token-based):
- `GET /proforma/{token}` — client views proforma.
- `GET /proforma/{token}/pdf` — download PDF.
- `POST /proforma/{token}/approve` — client clicks approve.
- `POST /proforma/{token}/request-changes` — client requests modifications.
- `GET /portal/{token}` — broader client portal (their requests).

Item-level operations (`/sourcing/items/*`): add, update, delete, update dates, update status, photos upload/delete/set-primary.

Payment plan operations (`/sourcing/proforma/payments/*`): generate plan, add/update/delete installment, mark installment paid.

Document operations: upload, delete, set visibility (private/client-visible).

Workflow operations: create, update, cancel, fulfill (mark fulfilled), apply markup, clone, send proforma, approve on behalf, mint portal token, rotate token, link to PO (`po-link`/`po-unlink`), sync from container, manual snapshot.

Bulk operations: `/sourcing/bulk-pdf`, `/sourcing/bulk/trash`, `/sourcing/bulk/restore`.

Change request flow: `POST /sourcing/change-requests/respond` — staff accepts/rejects a client-requested change.

Reminders: `POST /sourcing/reminders/run` — manually trigger reminder email batch (also runs on schedule).

### 7.14 Audit log (`/audit`)

Append-only record of every change made through the system.

Columns: id, timestamp, who, action, target table, context, IP, details.

Filterable by: date range, actor (user), target table.

Actions are language-keyed (e.g. `audit.action.deposit`, `audit.action.fee.change`). Translations live in `system/app/Http/Controllers/langs/{en,ar,zh}.json`.

The audit log is **append-only** — there is no UI to delete entries, and the table has no delete endpoint.

### 7.15 Reconciliation (`/reconciliation`)

Periodic close-out tooling: month-end matching of client-side activity against branch-side cash.

- `POST /reconciliation/branches` — branch reconciliation snapshot.
- `POST /reconciliation/clients` — client reconciliation snapshot.

### 7.16 Users (`/users`)

Manage web admin user accounts.

- `POST /users/create`, `/save`, `/get`, `/delete`.
- `POST /users/change_pass` — admin can reset any user's password.

### 7.17 Settings (`/settings`)

Edits the JSON described in [section 5](#5-configuration-and-settings) plus DB-backed config:

- `POST /settings/save_general` — company info, receipt footer, tracking prefix, print PIN.
- `POST /settings/update_exchange` — update default FX rates (also writes a record to `fx_rate_history`).
- `POST /settings/save`, `/settings/save2` — legacy alternate save endpoints retained for backwards compatibility.

---

## 8) Client web portal

When a client (not a staff user) logs in, the `chkAuthClient` middleware sends them to `/client`. The portal has three sections, mirroring the mobile app:

| Path | Page |
|------|------|
| `/client` | Overview / balances. |
| `/client/transactions` | Ledger of their movements. |
| `/client/transactions` → `print_reports` | Print a PDF statement. |
| `/client/shipping/sea` | Their sea shipments. |
| `/client/shipping/sea/container/{id}` | A specific container they have stake in. |
| `/client/shipping/sky` | Their air shipments. |
| `/client/shipping/sky/container/{id}` | A specific air container. |

The portal is read-only. Clients cannot edit anything except their notification preferences.

---

## 9) Employee mobile app

Flutter, located at `mobile_employee/`. Title: **موظف ShipFlow** / **ShipFlow Employee**.

### Screens

| Screen | File | Purpose |
|--------|------|---------|
| Login | `lib/src/screens/login_screen.dart` | Email + password against `POST /api/v1/employee/auth/login`. |
| Home | `lib/src/screens/home_screen.dart` | Active branch dropdown, pending outbox banner, three nav cards (Scan, Queue, Activity). |
| Scanner | `lib/src/screens/scanner_screen.dart` | Camera-based QR scanning, torch toggle, manual entry. |
| Scan Review | `lib/src/screens/scan_review_screen.dart` | After a scan: show current state, allowed actions, submit. |
| Branch Queue | `lib/src/screens/queue_screen.dart` | All shipments currently in the active branch. |
| Activity | `lib/src/screens/activity_screen.dart` | Audit of what this user has done. |
| Settings | `lib/src/screens/settings_screen.dart` | API base URL, language note. |

### The scan flow

1. User taps **Scan QR sticker**. Camera opens.
2. App reads `shipflow://qr/<sticker_ulid>` from the QR.
3. App calls `POST /api/v1/employee/scan/resolve` with the sticker id.
4. Server responds with one of four states:
   - **assigned** — sticker is on a specific piece; current event + allowed next events.
   - **unassigned** — sticker is brand new; only `RECEIVED_AT_HUB` is valid.
   - **revoked** — sticker is out of service; no actions.
   - **not_found** — not a ShipFlow sticker.
5. User picks an action, fills in piece_id (if first scan) and notes.
6. App calls `POST /api/v1/employee/scan/submit` with an `Idempotency-Key` header (random per scan).
7. If online: server validates and writes a `tracking_events` row. App invalidates queues.
8. If offline: scan is queued in the local Outbox.

### The Outbox (offline support)

The employee app must work in warehouses with poor connectivity. Every scan is written to a local Drift database first. The `OutboxDrainer` service flushes pending scans to the server in the background — automatically on connectivity changes, or manually via the **Sync now** button.

The orange "N scans pending sync" banner appears on the home screen whenever the queue is non-empty.

Idempotency keys ensure the same scan cannot create two events even if the drainer retries.

### Language

The app reads the device locale. Strings are in `lib/l10n/app_ar.arb` and `lib/l10n/app_en.arb`. Adding a new language means adding a new `.arb` file and regenerating `app_localizations*.dart`.

---

## 10) Client mobile app

Flutter, located at `mobile/`. Title: **شيب فلو / Ship Flow — بوابة العملاء**.

### Screens

| Screen | File | Purpose |
|--------|------|---------|
| Splash | `splash_screen.dart` | Boot screen while auth state resolves. |
| Login | `login_screen.dart` | Email-or-code + password. |
| Home shell | `home_shell.dart` | Bottom-nav with the four tabs. |
| Dashboard | `dashboard_screen.dart` | Balances per currency with You-owe / We-owe / Settled badges. |
| Transactions | `transactions_screen.dart` | Filterable list (by currency, by type). |
| Shipments | `shipments_screen.dart` | Air + sea shipments. |
| Shipment detail | `shipment_detail_screen.dart` | Single shipment with tracking timeline and per-piece QR codes. |
| Notifications | `notifications_screen.dart` | Push history + mark-all-read. |
| Settings | `settings_screen.dart` | Notification preferences, biometric unlock toggle, sign out. |

### APIs used

| API | Endpoint |
|-----|----------|
| Login | `POST /api/auth/login` |
| Logout | `POST /api/auth/logout` |
| Me | `GET /api/me` |
| Balances | `GET /api/balances` |
| Transactions | `GET /api/transactions` |
| Receipts | `GET /api/receipts` |
| Shipments | `GET /api/shipments`, `GET /api/shipments/{mode}/{id}` |
| Notifications | `GET /api/notifications`, `POST /api/notifications/{id}/read`, `POST /api/notifications/read-all`, `GET/PATCH /api/notifications/prefs` |
| Device registration | `POST /api/devices/register`, `POST /api/devices/{id}/revoke` |

### Push notifications

After login the app registers its FCM token. Backend dispatches push messages via queue jobs (`App\Modules\Tracking\Jobs\DispatchShipmentEventNotificationJob` for shipment events) and Laravel notification classes (`App\Modules\Tracking\Notifications\ShipmentTrackingEventReceived`, `App\Modules\Tracking\Notifications\StuckShipmentReminderNotification`) on each side-effect: deposit, withdrawal, transfer, commission, shipment received. Centralization into a single `NotificationService` is tracked as gap #5.

Clients can mute specific notification kinds from the in-app settings (e.g. mute commission alerts, keep shipment alerts).

### Biometric unlock

Optional. Once enabled in settings, the app requires Face ID / Touch ID / Android fingerprint on launch.

---

## 11) The accounting model

### Double-entry, multi-currency

Every cash-affecting mutation in the system writes a balanced pair of rows to `journal_lines`. Each row has:

- `account_code` (e.g. `1000`, `1100`).
- `currency` (`USD`, `EUR`, `LYD`, `CNY`).
- `dr` or `cr` amount (one is non-zero).
- `entry_id` linking it to a `journal_entries` row (the header).
- `posted_at` timestamp.
- `source_table`, `source_id` — polymorphic ref back to the originating record (a client transaction, a branch movement, a sourcing payment, etc.).
- `is_reversed` boolean — for reversal entries.

The **Trial Balance** is simply `SUM(dr) GROUP BY account_code, currency` vs `SUM(cr) GROUP BY account_code, currency`. The page (`/accounting/journal-trial-balance`) is exactly this query, formatted.

### Chart of Accounts

Seeded by `database/seeders/PurchasesChartOfAccountsSeeder.php`. Selected codes:

| Code | Name (EN) | Type | Normal | Purpose |
|------|-----------|------|--------|---------|
| 1000 | Cash on hand | Asset | DR | Branch tills. |
| 1100 | Accounts receivable — clients | Asset | DR | Client debit balances. |
| 1200 | Prepaid to suppliers | Asset | DR | Shipping line advances. |
| 1250 | Buyer custody (USD) | Asset | DR | Cash held by a buyer for purchases. |
| 1300 | Prepaid to customs brokers | Asset | DR | Broker advances. |
| 1320 | Purchases in transit | Asset | DR | Goods bought but not yet received. |
| 1400 | Warehouse inventory | Asset | DR | Goods received, not yet shipped to client. |
| 1500 | Goods in shipment | Asset | DR | Goods on the way to client. |
| 2000 | Client deposits (unearned) | Liability | CR | Client credit balances. |
| 3000 | Owner's equity | Equity | CR | Owner capital. |
| 4000 | Commission revenue | Revenue | CR | Air/sea commissions. |
| 4020 | Sourcing commission revenue | Revenue | CR | Sourcing markup. |
| 4200 | FX gain | Revenue | CR | Currency translation gains. |
| 5000 | Operating expenses | Expense | DR | Branch expenses. |
| 5400 | COGS — delivered | Expense | DR | Cost of goods sold when delivered. |

### Periods and close

The `accounting_periods` table holds month rows with a status (open / closed). When a period is **closed**, no journal lines can be inserted with a `posted_at` inside the closed window. Reopening is permitted but audit-logged.

### Drift detector

Some balances (client balances, supplier balances, broker balances) are denormalized for performance. The Drift Detector recomputes them from `journal_lines` and flags any divergence. If it ever shows drift, fix the root cause before posting any further entries — drift compounds.

### FX rates

`fx_rate_history` is the source of truth. Every recorded rate has a date stamp. When a transaction crosses currencies, it picks the rate from this table closest to (but not after) the transaction's posted_at. Defaults from `settings.json` are only the fallback if the table is empty.

### Cash counts

A cash count is a sworn statement: "I physically counted X amount of USD in this branch on this date." The system computes the recorded balance and the difference. If different, the operator clicks **Adjust**, which writes a settling journal entry (DR/CR depending on direction) against an "adjustments" account.

### Prepayments

Used when an advance payment doesn't yet have a specific destination. Register a prepayment (`/accounting/prepayments/register`) → later apply it (`/accounting/prepayments/{id}/apply`) against the specific shipment, container, or sourcing request. Applying writes the realizing journal entry.

---

## 12) The tracking model

### Three tables that matter

| Table | Role |
|-------|------|
| `tracking_branches` | Branch master used by the tracking module. **Independent from the legacy `branches` table** — no FK, no synchronized ids. Joining the two requires matching on `code` or a hand-maintained sync. The tracking module uses `tracking_branches` exclusively. |
| `branch_staff` | Which users work at which branches, with what role. |
| `sticker_batches` | A batch of N stickers printed at once. |
| `stickers` | One QR sticker. ULID PK. State: issued / assigned / revoked. |
| `tracking_events` | The append-only event spine. International (ShipsGo) + Internal (scan) events. |
| `custody_events` | Chain-of-custody for in-hand handoffs (1:1 with internal tracking_events). |
| `shipment_pieces` | The granular "piece" inside a shipment (multi-box shipments have multiple pieces). |

### Sticker lifecycle

```
   ┌───────┐ print  ┌──────────┐ first scan  ┌──────────┐
   │ batch │──────▶ │  issued  │────────────▶│ assigned │
   └───────┘        └──────────┘             └──────────┘
                          │                        │
                          │ revoke                 │ revoke
                          ▼                        ▼
                     ┌──────────┐             ┌──────────┐
                     │ revoked  │◀────────────│ revoked  │
                     └──────────┘             └──────────┘
```

- A **batch** is printed (often hundreds at a time).
- Each sticker is **issued** the moment the batch is created.
- When a receiver scans a brand-new sticker, the app prompts for the **piece_id** on the box. That call to `/api/v1/employee/scan/submit` assigns the sticker to the piece — the sticker is now **assigned**.
- A sticker can be **revoked** at any point (misprint, lost, damaged, voided). Revoked stickers cannot be scanned.

### Event kinds

`tracking_events.kind` is `INTERNATIONAL` or `INTERNAL`.

- **INTERNATIONAL** events come from ShipsGo via `/api/v1/webhooks/shipsgo`. They describe carrier-side state: vessel departure, arrival, gate-in, gate-out.
- **INTERNAL** events come from staff scans. The valid event types (from `app/Modules/Tracking/Enums/InternalEventType.php`):
  - `RECEIVED_AT_HUB`
  - `IN_TRANSIT_INTERNAL`
  - `RECEIVED_AT_BRANCH`
  - `READY_FOR_PICKUP`
  - `DELIVERED_TO_CUSTOMER`
  - `RETURNED_TO_HUB`
  - `LOST`
  - `DAMAGED`

The Unified Timeline service merges both kinds into a single ordered timeline shown to clients.

### Idempotency

The `tracking_events` table has a unique index on `(kind, client_event_id)`. Scan submissions always carry an `Idempotency-Key` header that becomes the `client_event_id`. The same scan replayed by the Outbox cannot create two events.

### Internal state transitions

The scan resolve endpoint computes the allowed next events from the current state. Some transitions are explicitly forbidden:

- From `DELIVERED_TO_CUSTOMER`: no further events (terminal).
- From `LOST` or `DAMAGED`: no further events (terminal).
- From `RECEIVED_AT_HUB`: can go to `IN_TRANSIT_INTERNAL` or `READY_FOR_PICKUP` (if hub IS the destination branch).
- Plus branch-scope enforcement: you cannot register `RECEIVED_AT_BRANCH` for a branch your token doesn't have ability for.

### ShipsGo integration

Sea containers are registered with ShipsGo (a carrier tracking aggregator). ShipsGo:

1. Posts webhook events to `/api/v1/webhooks/shipsgo` whenever the carrier publishes new milestones.
2. The webhook handler parses the v2 payload (`shipment.containers[].events[]`), maps each to a `tracking_events` row with `kind=INTERNATIONAL`, and inserts (idempotency via the carrier's event id as `client_event_id`).

Manual reconciliation: `php artisan tracking:reconcile-stuck` polls ShipsGo for any container that hasn't received events in N hours. Run it via the Laravel scheduler or manually.

---

## 13) Sourcing and purchases

The sourcing module is for "client asks us to find and buy product X from China." The flow:

```
Client request  →  Quote(s) from supplier(s)  →  Proforma  →  Client approves
                                                                    │
                                                                    ▼
                          ┌─────────── Optional change requests ────┤
                          ▼                                         │
                  Update proforma → New version ──────────────────► Client approves
                                                                    │
                                                                    ▼
                                                          Generate payment plan
                                                                    │
                                                                    ▼
                                                           Client pays installments
                                                                    │
                                                                    ▼
                                                              Buyer purchases
                                                                    │
                                                                    ▼
                                                                 Delivery
                                                                    │
                                                                    ▼
                                                                 Fulfilled
```

### Statuses

A sourcing request moves through statuses tracked on the request row. The Kanban (`/sourcing/board`) lays them out as columns. Per-request status is updated via `POST /sourcing/{id}/status`.

### Items, quotes, photos

Each request has line items. Items can be reused from the **catalog** (`/sourcing/catalog`). Each item supports multiple photos (`/sourcing/items/photos/upload`) with a primary photo selection.

### Quotes

Suppliers respond with quotes (`POST /sourcing/quotes/add`). The accept endpoint (`/sourcing/quotes/accept`) picks one quote as the basis for the proforma.

### Proforma & client portal

Once accepted, the proforma is sent to the client (`POST /sourcing/{id}/send`). The client receives an email with a tokenized link to the **public portal** — no login required, the token authenticates. The client can:

- View the proforma online.
- Download the PDF.
- Approve.
- Request changes.

Tokens can be rotated (`/sourcing/{id}/rotate-token`).

### Change requests

When a client requests changes, the request enters change-request state. Staff reviews and responds with accept / reject via `POST /sourcing/change-requests/respond`. Accepted changes generate a new proforma version.

### Versions and diffs

Every change to the proforma creates a new version. `/sourcing/{id}/versions/{n}` renders that version's PDF. `/sourcing/{id}/diff` shows the diff between versions.

### Payment plan

Once approved, a payment plan is generated (`POST /sourcing/proforma/payment-plan`). Each installment is an `installment` row with due date, amount, currency. Mark paid via `/sourcing/proforma/payments/mark-paid`. Each payment writes a journal entry against client deposits.

### Buyers and purchase orders

`app/Modules/Purchases` owns the buyer-side accounting. Buyers are Mataz staff in China who physically buy from suppliers. A buyer has a buyer account (`buyer_accounts`) which holds custody balances (account `1250 — Buyer custody`).

Each sourcing request that's been approved spawns one or more **purchase orders** (`purchase_orders`). The PO lifecycle:

- `created` → `confirmed` → `start-purchasing` → `mark-purchased` → `mark-delivered` → `mark-received`.

API endpoints: `/api/purchases`, `/api/purchases/{order}/...`. The accounting integration service writes the right journal entries at each step (cash → in-transit → warehouse → goods-in-shipment → COGS).

### Profit dashboard

`/sourcing/{id}/profit` shows per-request profit: client receipts vs supplier costs vs shipping costs vs FX gain, in one place.

### Supplier reliability

`/sourcing/insights/suppliers` ranks suppliers by historical on-time, quality, price variance. Used to inform quote selection.

### Commissions report

`/sourcing/commissions` shows commission revenue per period.

---

## 14) Notifications (FCM)

### Flow

```
event happens         queue job             FCM         mobile app
─────────────         ─────────             ───         ──────────
client.deposit ───▶ NotifyClient ───▶  send-multicast  ───▶ push received
                       │                                       │
                       └────────── DB record ─────────────▶ in-app feed
```

Every notification has both an in-app feed entry (`notifications` table) and a push event. The mobile app shows the feed in the Notifications tab; push wakes the device.

### Triggers

Notifications fire on:

- Client deposit.
- Client withdrawal.
- Commission charge.
- Transfer in/out.
- Shipment received at hub.
- Shipment ready for pickup at branch.
- Sourcing proforma sent / approved / change-request answered.
- Installment due reminder (scheduled).

### Per-client preferences

Stored as bitmask/flags on the `clients` table (`notify_*` columns from migration `2026_05_20_140000_add_notify_prefs_to_clients_table.php`). Clients edit them in the mobile app settings.

### FCM credentials

Set on the backend:
- `FCM_PROJECT_ID` — Firebase project id.
- `FCM_CREDENTIALS_PATH` — absolute path to the service-account JSON.

For mobile builds, the client app needs `google-services.json` (Android) and `GoogleService-Info.plist` (iOS) — never commit either.

Device registration: `POST /api/devices/register` with `{token, platform}`. Revocation: `POST /api/devices/{id}/revoke`. List your devices: `GET /api/devices`.

---

## 15) Audit log

`audit_log` is the append-only record. Every meaningful state change writes a row:

- Who did it (`user_id`).
- When (`created_at`).
- What action (`action_key`, a translation key like `audit.action.deposit`).
- Which record (`target_table`, `target_id`).
- Free-form context (`context_json` — payload-specific details).
- IP address.

The log is paged via `POST /audit/load` and filterable in the UI by date range, actor, and target table.

There is no delete endpoint. There is no edit endpoint. The append-only property is the audit log's whole point.

### Per-module audit logs

Two modules have their own dedicated logs:

- `app/Modules/Tracking/...` writes to the main `audit_log` via `AuditLogService` for sticker / scan events.
- `app/Modules/Purchases/` writes to `purchase_audit_logs` via its own service for PO lifecycle events.

Both flow into the same UI under `/audit`.

---

## 16) APIs

### Authentication

```http
POST /api/auth/login
Content-Type: application/json

{ "email_or_code": "101", "password": "...", "device_name": "iPhone Daisy" }

→ 200 OK
{ "token": "1|abcdef...", "user": { ... } }
```

The token must be sent on every subsequent call as `Authorization: Bearer <token>`.

### Client API (mobile)

| Verb | Path | Returns |
|------|------|---------|
| GET | `/api/me` | The client profile. |
| GET | `/api/balances` | Per-currency balances. |
| GET | `/api/transactions?page=N` | Paginated ledger. |
| GET | `/api/receipts` | Receipts list. |
| GET | `/api/shipments` | All shipments (air + sea). |
| GET | `/api/shipments/{mode}/{id}` | One shipment detail (mode = `sky` or `sea`). |
| GET | `/api/notifications` | Notification feed. |
| POST | `/api/notifications/{id}/read` | Mark one as read. |
| POST | `/api/notifications/read-all` | Mark all as read. |
| GET | `/api/notifications/prefs` | Current notification preferences. |
| PATCH | `/api/notifications/prefs` | Update preferences. |
| POST | `/api/devices/register` | Register an FCM token. |
| GET | `/api/devices` | List registered devices for this client. |
| POST | `/api/devices/{id}/revoke` | Revoke a device. |

### Employee API (scanning)

| Verb | Path | Purpose |
|------|------|---------|
| POST | `/api/v1/employee/auth/login` | Email + password → token with `branch:N` abilities. |
| POST | `/api/v1/employee/auth/logout` | Invalidate the token. |
| GET | `/api/v1/employee/me` | Profile + branch assignments. |
| GET | `/api/v1/employee/branches/{branch}/queue` | All shipments currently in this branch. |
| POST | `/api/v1/employee/scan/resolve` | Resolve a sticker — get current state + allowed actions. |
| POST | `/api/v1/employee/scan/submit` | Submit an event. Requires `Idempotency-Key` header. |
| GET | `/api/v1/employee/activity` | This user's recent activity. |

### Webhook

| Verb | Path | Purpose |
|------|------|---------|
| POST | `/api/v1/webhooks/shipsgo` | Inbound carrier events from ShipsGo. HMAC verification using `SHIPSGO_WEBHOOK_SECRET`. |

### Sourcing public portal

Tokenized URLs — no login required, the token is the credential.

| Verb | Path |
|------|------|
| GET | `/proforma/{token}` |
| GET | `/proforma/{token}/pdf` |
| POST | `/proforma/{token}/approve` |
| POST | `/proforma/{token}/request-changes` |
| GET | `/portal/{token}` |

### Public shipment tracking

| Verb | Path |
|------|------|
| GET | `/track/{code}` | Public per-piece tracking page. The code is a tracking code printed on the QR sticker. |

---

## 17) Operations playbook

### What needs to be running

1. **Web server** (Apache / nginx) serving `public/`. Health endpoint at `/up` — point the load balancer's health check there (10s interval, 2 consecutive failures = unhealthy).
2. **PHP-FPM** 8.2+.
3. **MySQL** with the configured database.
4. **Queue worker**: `php artisan queue:work --queue=default --sleep=3 --tries=3`. Required for FCM push, sourcing reminders, ShipsGo webhook processing.
5. **Scheduler**: a single cron entry runs `php artisan schedule:run` every minute. `bootstrap/app.php`'s `withSchedule()` registers every recurring task — see [§17.5 Console commands reference](#175-console-commands-reference). Production cron:
   ```
   * * * * * cd /var/www/system && php artisan schedule:run >> /dev/null 2>&1
   ```

### Common console commands

```bash
# Cache routes / config (after deploy)
php artisan route:cache
php artisan config:cache

# Clear caches (after settings change)
php artisan route:clear && php artisan config:clear

# Re-run migrations after a deploy
php artisan migrate

# Reconcile stuck ShipsGo trackings (also runs on schedule)
php artisan tracking:reconcile-stuck

# Drain failed queue jobs after fixing the root cause
php artisan queue:retry all

# List every scheduled task with next-run timestamp
php artisan schedule:list
```

### 17.5 Console commands reference

Every command in `app/Console/Commands/`. Anything marked **scheduled** runs automatically via the Laravel scheduler; everything else is run manually by an ops engineer.

| Command | Schedule | Purpose |
|---------|----------|---------|
| `sourcing:remind` | daily 09:00 | Send reminder emails for stale proformas. Defaults: 3-day age, 5-day cooldown. Options to override. |
| `sourcing:health-snapshot` | daily 02:00 | Snapshots deal health into `sourcing_deal_health_snapshots` for the dashboards. |
| `tracking:reconcile-stuck` | every 4 hours | Polls ShipsGo for containers whose webhook updates haven't been received. Backfills missing events. |
| `tokens:purge-expired` | daily 03:00 | Deletes expired Sanctum `personal_access_tokens` rows. `--dry-run` available. |
| `purge:webhook-payloads` | daily 03:15 | Replaces `webhook_deliveries.payload` with a stub after 90 days; keeps the metadata row. |
| `purge:failed-jobs` | weekly Mon 03:30 | Deletes `failed_jobs` rows older than 30 days. |
| `purge:read-notifications` | weekly Mon 03:45 | Deletes notifications where `read_at` is older than 180 days. Unread are kept forever. |
| `archive:audit-log` | monthly 1st 04:00 | Exports `audit_log` rows older than 18 months to gzipped JSONL under `storage/app/private/audit-archive/`, then deletes. |
| `stickers:generate` | manual | Generate a new batch of QR stickers and PDF for printing. Run before a print run. |
| `tracking:shipsgo-smoke` | manual | Diagnostic: fire a known sticker through ShipsGo and verify the round-trip. |
| `tracking:e2e-walk` | manual | Synthetic end-to-end walk through the tracking flow for testing. |
| `journal:backfill` | manual, one-shot | Migration helper: build `journal_lines` from legacy transaction tables. `--dry-run`. Only safe to run during initial upgrade. |
| `shipments:pieces-backfill` | manual, one-shot | Migration helper: populate `shipment_pieces` from legacy `store_sea`/`store_sky` rows. `--dry-run`. |
| `schema:reset` | dev only | Wipes and re-migrates the schema. Refuses to run unless `APP_ENV` is local/testing. |

Plus the Purchases module ships its own scheduled commands via `PurchasesServiceProvider`:

| Command | Schedule | Purpose |
|---------|----------|---------|
| `purchases:fetch-exchange-rates` | every 6 hours | Pulls FX rates from the configured external provider into `exchange_rates`. |
| `purchases:check-low-balances` | daily 09:00 | Flags buyer accounts below their threshold so a top-up can be initiated. |

### Backups

- **Database**: nightly `mysqldump` to off-site storage. The system has no built-in backup command.
- **Storage**: the `storage/app/public/` tree holds sourcing item photos, proforma PDFs, document uploads. Back this up too.
- **Settings JSON**: `system/app/Http/Controllers/settings.json` is small — include it in the daily backup.

### Monthly close checklist

1. Confirm Trial Balance shows DR = CR per currency. If not, open Drift Detector and fix before anything else.
2. Run AR Aging, Supplier Aging, Broker Aging. Flag anything 90+ days for follow-up.
3. Do a physical cash count at every branch; record via Cash Count and let the system post the adjusting entry.
4. Update FX rates if they've drifted from market.
5. Close the period from Accounting Periods.
6. Generate the three PDFs (P&L, Balance Sheet, Cash Flow) and archive offline.

### After-incident recovery

If a journal entry was posted wrong:
1. Find it in Journal Entries.
2. Click **Reverse**. This posts the inverse entry (DR↔CR) with `is_reversed=true` on both rows.
3. Post the correct entry as a new entry.

Never edit posted journal rows directly. Never delete from `journal_lines` outside of the model methods.

---

## 18) Troubleshooting

### "Trial Balance doesn't balance"

Most likely causes:

1. A transaction posted with a one-sided entry (DR without CR or vice versa). Check Drift Detector and the audit log for the most recent entries.
2. Two legs of a single transaction posted at different FX rates. Check FX Rate History to see what was active at that moment.
3. A cash count was adjusted but the adjusting entry hit the wrong account. Check the cash count's audit detail.

### "An employee scan never reached the server"

1. Open the employee app's home screen. If the orange "N scans pending sync" banner is visible, the outbox is non-empty.
2. Check internet connectivity.
3. Tap **Sync now**. If it fails, the error toast describes the cause.
4. If the token has been revoked (e.g. admin removed the user from the branch), the app will force a re-login.

### "ShipsGo events stopped arriving"

1. Check the webhook URL is reachable from the public internet.
2. Confirm `SHIPSGO_WEBHOOK_SECRET` matches what's configured in ShipsGo's portal.
3. Run `php artisan tracking:reconcile-stuck` manually to backfill events while you investigate.
4. Check the queue worker is running — webhook payloads are processed via `ProcessShipsGoWebhook` job.

### "A client says they don't see a recent deposit on their app"

1. Confirm the deposit was approved (not pending) — check pending filter on Clients.
2. Confirm the deposit is on the right client (right code, right name).
3. If `client_transactions_default_pending=true` is set, all new transactions need explicit approval before they're visible to the client.
4. Check the queue worker — push notifications are queued; the in-app feed updates immediately on next refresh.

### "Push notifications never arrive"

1. Confirm `FCM_PROJECT_ID` and `FCM_CREDENTIALS_PATH` are set in `.env`.
2. Confirm the service-account JSON has FCM send permission in the Firebase console.
3. Confirm the client mobile app actually called `POST /api/devices/register` after login — check `client_devices` table.
4. Confirm the queue worker is running. Push is dispatched via queue jobs.
5. On iOS specifically, confirm the app has APNs entitlements set up in Firebase.

### "Audit log shows raw translation keys"

E.g. `audit.action.sourcing_request` instead of "Sourcing request submitted".

Cause: the `action_key` exists in code but no matching entry in `system/app/Http/Controllers/langs/{en,ar,zh}.json`. Add the entry for each language file.

### "Period close button is disabled"

The period can't be closed while DR ≠ CR. Trial Balance has to be in balance first.

### "Login works but every page says I'm logged out"

Likely a session storage problem. Check `SESSION_DRIVER` in `.env` and confirm the configured store (file, database, or redis) is writable / reachable.

---

## 19) Glossary

| Term | Meaning |
|------|---------|
| **Branch** | A physical Mataz Trading location (Tripoli, Misrata, Benghazi, Guangzhou). |
| **Branch staff** | A user assigned to one or more branches with a role (MANAGER/RECEIVER/COURIER/AUDITOR). |
| **Buyer** | A staff member in China who physically buys product from suppliers. Has a buyer custody account. |
| **Chart of Accounts (CoA)** | The list of accounts (numbered, typed) the journal posts against. |
| **Container** | A shipping container (sea) or air trip (sky). Houses many shipments. |
| **Drift** | Divergence between a denormalized balance and the journal truth. Detected by Drift Detector. |
| **FCM** | Firebase Cloud Messaging — Google's push notification service. |
| **Idempotency-Key** | Header on scan submits, ensures the same scan can't create two events. |
| **Internal event** | A scan event written by a Mataz staff member (vs an international event from ShipsGo). |
| **Journal line** | One side of a journal entry (a DR or a CR for one account, one currency). |
| **Outbox** | The local queue of pending scans inside the employee app. |
| **Period** | An accounting period (typically a month). Can be open or closed. |
| **Piece** | One physical box within a multi-box shipment. Each piece has its own sticker and QR. |
| **Prepayment** | An advance payment recorded as an asset before being applied to a specific transaction. |
| **Proforma** | A pre-invoice document sent to the client for approval before purchasing. |
| **Sanctum** | Laravel's token authentication package, used for both mobile apps. |
| **ShipsGo** | Third-party carrier tracking aggregator. Source of `INTERNATIONAL` tracking events. |
| **Sourcing request** | A client request for Mataz to source product. Lives in the sourcing module. |
| **Sticker** | A physical QR sticker (ULID PK). Issued in batches, assigned to a piece, sometimes revoked. |
| **Trial Balance** | Per-currency DR vs CR aggregation across all accounts. Must balance every day. |
| **Unified timeline** | The merged view of internal scan events + ShipsGo events that the client sees on a shipment. |

---

---

## 20) Database schema

The database has ~47 tables across the domains described in the manual. Below is the ERD by domain, followed by the cross-domain relationships.

### 20.1 Identity & devices

```
┌──────────────────────┐         ┌─────────────────────────┐
│ users                │         │ personal_access_tokens  │
│──────────────────────│         │─────────────────────────│
│ id (PK)              │◀───────▶│ tokenable_id            │
│ name                 │ 1   *   │ tokenable_type          │
│ email (unique)       │         │ name                    │
│ password (bcrypt)    │         │ abilities (JSON)        │
│ email_verified_at    │         │ last_used_at            │
└──────────┬───────────┘         └─────────────────────────┘
           │
           │ 1
           │
           │ *
┌──────────▼───────────┐
│ branch_staff         │  one user can serve multiple branches with different roles
│──────────────────────│
│ id (PK)              │
│ branch_id (FK)       │───▶ tracking_branches.id
│ user_id (FK)         │───▶ users.id
│ role (enum)          │     MANAGER | RECEIVER | COURIER | AUDITOR
│ is_active            │
│ UNIQUE(branch, user) │
└──────────────────────┘

┌──────────────────────┐         ┌──────────────────────┐
│ clients              │         │ client_devices       │
│──────────────────────│         │──────────────────────│
│ id (PK)              │◀────────│ client_id (FK)       │
│ code (e.g. "101")    │ 1   *   │ fcm_token            │
│ name                 │         │ platform             │
│ email                │         │ last_seen_at         │
│ branch_id (FK)       │         └──────────────────────┘
│ balance_usd          │
│ balance_eur          │         ┌──────────────────────┐
│ balance_lyd          │         │ employee_devices     │
│ balance_cny          │         │──────────────────────│
│ notify_*  (bitmask)  │         │ user_id (FK)         │
└──────────────────────┘         │ fcm_token            │
                                 │ platform             │
                                 └──────────────────────┘
```

### 20.2 Branches & shipments

> **Note on the two branches tables.** `branches` (legacy) and `tracking_branches` (tracking module) are **independent**. No FK, no ID alignment. The tracking module exclusively uses `tracking_branches`. The legacy `branches` table is referenced by the older shipping (`store_sea`, `store_sky`) and treasury code. Joining the two requires matching `code` or a hand-maintained sync. Resolving this is tracked separately.

```
┌──────────────────────┐         ┌──────────────────────┐
│ branches             │         │ tracking_branches    │
│ (legacy)             │         │ (tracking module)    │
│──────────────────────│         │──────────────────────│
│ id (PK)              │         │ id (PK)              │
│ name_en, name_ar     │         │ code, name           │
│ code                 │         │ role (HUB|SPOKE)     │
│ address              │         │ country, city        │
└──────────┬───────────┘         └──────────┬───────────┘
           │                                │ *
           │ referenced by                  │
           │ store_sea, store_sky,          │
           │ treasury, etc.                 │
           │                                ▼
           │                       (branch_staff,
           │                        tracking_events,
           │                        custody_events,
           │                        employee_action_logs)
           ▼
   ┌──────────────┐   ┌──────────────┐
   │ store_sea    │   │ store_sky    │   (legacy table names; "store_out_sea/sky" in code)
   │──────────────│   │──────────────│
   │ id (PK)      │   │ id (PK)      │
   │ client_id    │   │ client_id    │
   │ branch_id    │   │ branch_id    │   (refs branches, NOT tracking_branches)
   │ container_id │   │ container_id │
   │ weight, cbm  │   │ weight, cbm  │
   │ category     │   │ category     │
   │ status       │   │ status       │
   │ created_at   │   │ created_at   │
   └──────┬───────┘   └──────┬───────┘
          │                  │
          │  polymorphic     │
          │  source_table=   │
          │  "store_out_sea" │
          │  or "..._sky"    │
          ▼                  ▼
   ┌─────────────────────────────────┐
   │ shipment_pieces                 │
   │─────────────────────────────────│
   │ id (PK)                         │
   │ tracking_code (UNIQUE, 12 char) │  MTZ-XXXX-XXXX-XXXX
   │ source_table   (varchar 32)     │  polymorphic ptr
   │ source_id      (bigint)         │
   │ client_id (nullable, no FK)     │
   │ piece_index, piece_total        │
   │ status                          │
   └────────────────┬────────────────┘
                    │
                    │ 1
                    │
                    │ 1 (when assigned)
   ┌────────────────▼────────────────┐
   │ stickers                        │
   │─────────────────────────────────│
   │ id (PK, ULID)                   │  shipflow://qr/<ulid>
   │ batch_id (FK)                   │
   │ shipment_piece_id (FK, nullable)│
   │ printed_at                      │
   │ assigned_at                     │
   │ revoked_at, revoke_reason       │
   └─────────────────────────────────┘
                    ▲
                    │ *
                    │ 1
   ┌────────────────┴────────────────┐
   │ sticker_batches                 │
   │─────────────────────────────────│
   │ id (PK)                         │
   │ printed_at                      │
   │ count                           │
   └─────────────────────────────────┘
```

### 20.3 Tracking events

```
┌──────────────────────────────────────────────────────────┐
│ tracking_events  (append-only event spine)               │
│──────────────────────────────────────────────────────────│
│ id (PK)                                                  │
│ shipment_source_table (varchar 32)  ─┐  polymorphic ref  │
│ shipment_source_id    (bigint)       │  to store_sea     │
│                                      ┘  or store_sky     │
│ shipment_piece_id (FK, nullable)                         │
│ kind (enum: INTERNATIONAL | INTERNAL)                    │
│ event_type (varchar 64)              RECEIVED_AT_HUB,    │
│                                      DELIVERED, ...      │
│ occurred_at (timestamp)                                  │
│ city, country                                            │
│ branch_id (FK, nullable)                                 │
│ raw_payload (JSON)                                       │
│ translation_key, translation_params                      │
│ recorded_by_user_id (FK, nullable)                       │
│ client_event_id (varchar 191)        idempotency key     │
│ is_customer_visible                                      │
│ UNIQUE(kind, client_event_id)                            │
└─────────────────────┬────────────────────────────────────┘
                      │
                      │ 1
                      │
                      │ 0..1     for INTERNAL events that
                      ▼          involve a physical handoff
        ┌────────────────────────┐
        │ custody_events         │
        │────────────────────────│
        │ tracking_event_id (FK) │
        │ from_user_id, to_user_id (or to_client_id) │
        │ signature_path         │
        │ photo_path             │
        └────────────────────────┘

┌────────────────────────────────────────┐
│ webhook_deliveries  (audit + dedup)    │
│────────────────────────────────────────│
│ id (PK)                                │
│ provider (varchar 32)  e.g. "shipsgo"  │
│ external_event_id (varchar 191)        │
│ event_type                             │
│ payload (JSON)                         │
│ signature                              │
│ received_at, processed_at              │
│ UNIQUE(provider, external_event_id)    │
└────────────────────────────────────────┘
```

### 20.4 Accounting

```
┌─────────────────────────┐         ┌─────────────────────────┐
│ chart_of_accounts       │         │ accounting_periods      │
│─────────────────────────│         │─────────────────────────│
│ code (PK, varchar 16)   │         │ id (PK)                 │
│ name_en, name_ar, name_zh│        │ start_date, end_date    │
│ type (asset|liability|  │         │ status (open|closed)    │
│       equity|revenue|   │         │ closed_at, closed_by    │
│       expense)          │         └─────────────────────────┘
│ normal (debit|credit)   │
│ semantic_key            │
└──────────┬──────────────┘
           │
           │ referenced by
           │
┌──────────▼──────────────┐         ┌─────────────────────────┐
│ journal_entries         │ 1     * │ journal_lines           │
│─────────────────────────│◀────────│─────────────────────────│
│ id (PK)                 │         │ id (PK)                 │
│ posted_at (timestamp)   │         │ entry_id (FK)           │
│ memo                    │         │ account_code (FK)       │
│ source_table            │         │ account_name (snapshot) │
│ source_id               │         │ currency (USD|EUR|...)  │
│ created_by_user_id (FK) │         │ dr (decimal 18,4)       │
│ is_reversed (bool)      │         │ cr (decimal 18,4)       │
│ reverses_entry_id (FK)  │         │ is_reversed (bool)      │
└─────────────────────────┘         └─────────────────────────┘

┌─────────────────────────┐  ┌─────────────────────────┐  ┌──────────────────┐
│ fx_rate_history         │  │ exchange_rate_configs   │  │ exchange_rates   │
│─────────────────────────│  │─────────────────────────│  │──────────────────│
│ id (PK)                 │  │ id (PK)                 │  │ id (PK)          │
│ from_currency           │  │ ccy_from, ccy_to        │  │ config_id (FK)   │
│ to_currency             │  │ source ("manual"|"api") │  │ rate (decimal)   │
│ rate                    │  │ default_markup_bps      │  │ effective_at     │
│ effective_at            │  └─────────────────────────┘  └──────────────────┘
│ recorded_by_user_id     │
└─────────────────────────┘

┌─────────────────────────┐  ┌─────────────────────────┐  ┌──────────────────┐
│ cash_counts             │  │ prepayments             │  │ owners           │
│─────────────────────────│  │─────────────────────────│  │──────────────────│
│ id (PK)                 │  │ id (PK)                 │  │ id (PK)          │
│ branch_id (FK)          │  │ party_type (sup|brk)    │  │ name             │
│ currency                │  │ party_id                │  │ equity_currency  │
│ counted_amount          │  │ amount, currency        │  └──────────────────┘
│ recorded_amount         │  │ journal_entry_id (FK)   │
│ adjust_journal_entry_id │  │ applied_at              │
│ counted_at, counted_by  │  │ applied_to_*            │
└─────────────────────────┘  └─────────────────────────┘

┌─────────────────────────┐
│ receipts                │
│─────────────────────────│
│ id (PK)                 │
│ source_table            │   polymorphic ptr to the
│ source_id               │   transaction it documents
│ receipt_no              │
│ amount, currency        │
│ printed_at              │
│ void_journal_entry_id   │
└─────────────────────────┘
```

### 20.5 Sourcing

```
┌─────────────────────────┐
│ sourcing_requests       │
│─────────────────────────│
│ id (PK)                 │
│ client_id (FK)          │
│ branch_id (FK)          │
│ status (enum)           │  draft|sent|approved|in_progress|fulfilled|cancelled
│ portal_token (UNIQUE)   │
│ commission_amount       │
│ commission_journal_entry│
│ purchase_order_id (FK)  │
└──────────┬──────────────┘
           │
           ├──────────────────────────────────────┬───────────────────┐
           │ 1:*                                  │ 1:*               │ 1:*
           ▼                                      ▼                   ▼
┌─────────────────────────┐         ┌──────────────────────┐  ┌────────────────────────┐
│ sourcing_request_items  │         │ sourcing_request_    │  │ sourcing_request_      │
│─────────────────────────│         │ quotes               │  │ documents              │
│ id (PK)                 │         │──────────────────────│  │────────────────────────│
│ sourcing_request_id (FK)│         │ id (PK)              │  │ id (PK)                │
│ catalog_item_id (FK?)   │         │ sourcing_request_id  │  │ sourcing_request_id    │
│ name, qty, unit_price   │         │ supplier_id          │  │ path, mime, size       │
│ photos (JSON array)     │         │ supplier_name_free   │  │ visibility (priv|client│
│ status                  │         │ status (proposed|    │  │   _visible)            │
└─────────────────────────┘         │   accepted|rejected) │  └────────────────────────┘
                                    └──────────────────────┘

┌──────────────────────────────────┐  ┌──────────────────────────────────┐
│ sourcing_request_change_requests │  │ sourcing_request_versions        │
│──────────────────────────────────│  │──────────────────────────────────│
│ id (PK)                          │  │ id (PK)                          │
│ sourcing_request_id (FK)         │  │ sourcing_request_id (FK)         │
│ requested_changes (JSON)         │  │ version_no (int)                 │
│ status (pending|accepted|rejected│  │ snapshot (JSON)                  │
│ responded_by, responded_at       │  │ created_at                       │
└──────────────────────────────────┘  └──────────────────────────────────┘

┌──────────────────────────────────┐  ┌──────────────────────────────────┐
│ sourcing_request_purchase_orders │  │ sourcing_deal_health_snapshots   │
│──────────────────────────────────│  │──────────────────────────────────│
│ id (PK)                          │  │ id (PK)                          │
│ sourcing_request_id (FK)         │  │ sourcing_request_id (FK)         │
│ purchase_order_id (FK)           │  │ taken_at                         │
│ allocation_pct                   │  │ health_score                     │
└──────────────────────────────────┘  │ risk_flags (JSON)                │
                                      └──────────────────────────────────┘

┌─────────────────────────┐
│ product_catalog         │
│─────────────────────────│
│ id (PK)                 │
│ sku, name, photos       │
│ default_price, currency │
│ last_used_at            │
└─────────────────────────┘
```

### 20.6 Purchases (buyer-side)

```
┌─────────────────────────┐         ┌─────────────────────────┐
│ buyers                  │         │ warehouses              │
│─────────────────────────│         │─────────────────────────│
│ id (PK)                 │         │ id (PK)                 │
│ name, contact           │         │ name, address           │
│ user_id (FK, nullable)  │         │ city, country           │
└──────────┬──────────────┘         └─────────────────────────┘
           │
           │ 1:*
           ▼
┌─────────────────────────┐         ┌─────────────────────────┐
│ buyer_accounts          │   *:1   │ buyer_transactions      │
│─────────────────────────│◀────────│─────────────────────────│
│ id (PK)                 │         │ id (PK)                 │
│ buyer_id (FK)           │         │ buyer_account_id (FK)   │
│ currency                │         │ kind (deposit|spend|    │
│ balance                 │         │   transfer|adjust)      │
└──────────┬──────────────┘         │ amount, currency        │
           │                        │ journal_entry_id (FK)   │
           │                        └─────────────────────────┘
           │
           │ 1:*
           ▼
┌─────────────────────────┐
│ buyer_reconciliations   │
│─────────────────────────│
│ id (PK)                 │
│ buyer_account_id (FK)   │
│ period_start, period_end│
│ reconciled_balance      │
└─────────────────────────┘

┌─────────────────────────┐         ┌─────────────────────────────┐
│ purchase_orders         │ 1     * │ purchase_order_items        │
│─────────────────────────│◀────────│─────────────────────────────│
│ id (PK)                 │         │ id (PK)                     │
│ po_number (UNIQUE)      │         │ purchase_order_id (FK)      │
│ buyer_id (FK)           │         │ sourcing_request_item_id    │
│ warehouse_id (FK)       │         │ description                 │
│ supplier_id             │         │ qty, unit_price, currency   │
│ status (created|        │         └─────────────────────────────┘
│   confirmed|purchasing| │
│   purchased|delivered|  │         ┌─────────────────────────────┐
│   received|cancelled)   │ 1     * │ purchase_order_status_      │
│ total_amount, currency  │◀────────│ history                     │
│ confirmed_at            │         │─────────────────────────────│
│ delivered_at            │         │ id (PK), po_id (FK)         │
└──────────┬──────────────┘         │ from_status, to_status      │
           │                        │ actor_user_id, at, note     │
           │ 1:*                    └─────────────────────────────┘
           ▼
┌─────────────────────────────┐
│ purchase_order_attachments  │
│─────────────────────────────│
│ id (PK), po_id (FK)         │
│ path, mime, size            │
└─────────────────────────────┘
```

### 20.7 Audit & ops plumbing

```
┌─────────────────────────┐  ┌─────────────────────────┐  ┌─────────────────────────┐
│ audit_log               │  │ purchase_audit_logs     │  │ employee_action_logs    │
│─────────────────────────│  │─────────────────────────│  │─────────────────────────│
│ id (PK)                 │  │ id (PK)                 │  │ id (PK)                 │
│ user_id (FK, nullable)  │  │ purchase_order_id (FK)  │  │ user_id (FK)            │
│ action_key (varchar 64) │  │ actor_user_id           │  │ branch_id (FK)          │
│ target_table            │  │ action_key              │  │ action                  │
│ target_id               │  │ before, after (JSON)    │  │ payload (JSON)          │
│ context (JSON)          │  │ created_at              │  │ ip, user_agent          │
│ ip                      │  └─────────────────────────┘  └─────────────────────────┘
│ created_at              │
└─────────────────────────┘

┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐
│ jobs             │  │ failed_jobs      │  │ sessions         │  │ cache            │
│  (queue backlog) │  │  (queue failures)│  │  (web sessions)  │  │  (app cache)     │
└──────────────────┘  └──────────────────┘  └──────────────────┘  └──────────────────┘
```

### 20.8 Cross-domain relationships at a glance

```
clients
  ├──▶ clients_transactions  ──┐
  ├──▶ store_sea / store_sky ──┼──▶ shipment_pieces ──▶ stickers
  ├──▶ sourcing_requests ──────┘                ▲
  └──▶ client_devices                           │
                                                │
sourcing_requests                               │
  ├──▶ sourcing_request_items                   │
  ├──▶ sourcing_request_quotes                  │
  ├──▶ sourcing_request_purchase_orders ───▶ purchase_orders
  │                                          ├──▶ purchase_order_items
  │                                          ├──▶ purchase_order_status_history
  │                                          └──▶ purchase_order_attachments
  └──▶ commission_journal_entry_id ──▶ journal_entries

store_sea / store_sky ───▶ tracking_events ◀─── webhook_deliveries (ShipsGo)
                                ▲
                                └─── scan events (employee app)

every cash-affecting mutation ──▶ journal_entries ──▶ journal_lines ──▶ chart_of_accounts
                                                                              ▲
fx_rate_history ──── consulted by journal-writing services ───────────────────┘
```

**Notes on conventions:**
- Several tables use polymorphic `(source_table, source_id)` pointers instead of FK constraints. This is deliberate where the parent can be in either `store_sea` or `store_sky` (shipments) or one of many transaction tables (receipts, journal lines). The trade-off is no DB-level RI on those pointers — the model layer enforces them.
- `clients_transactions` and `customs_brokers`, `suppliers` predate the migration timeline shown here and live in older migrations not in the current repo. Their structure is best read off the controllers (`clientsController.php`, `customsBrokersController.php`, `suppliersController.php`).
- Money columns are `decimal(18,4)` everywhere except legacy client balance columns which are `decimal(18,2)`. Be careful when joining the two.

---

## 21) Permission matrix

ShipFlow's permission model has three independent layers. Each layer answers a different question.

| Layer | Question it answers | Where enforced |
|-------|---------------------|----------------|
| **Web admin gate** | Can this person reach the admin web at all? | `chkAuthAdmin` middleware |
| **Client gate** | Can this person reach the client portal at all? | `chkAuthClient` middleware |
| **Branch ability gate** | Can this employee scan in this branch with this event? | Sanctum `branch:N` abilities + `EnforceBranchScope` |

There is **no formal RBAC on the web admin**. Every user in the `users` table who can log in has full access to every web admin page. Role-based gating on the web is by convention (who you give an account to), not enforced in code.

### 21.1 Web admin — module access

| Module / page group | Anyone with admin login |
|---------------------|:-----------------------:|
| Dashboard | ✅ |
| Clients (CRUD + transactions) | ✅ |
| Air Freight / Sea Freight | ✅ |
| Treasury | ✅ |
| All Accounting pages (incl. period close, drift, owners, prepayments) | ✅ |
| Sourcing (all 50+ endpoints) | ✅ |
| Audit log (read) | ✅ |
| Reconciliation | ✅ |
| Branches, Shipping Lines, Customs Brokers | ✅ |
| Users (create / edit / delete) | ✅ |
| Settings | ✅ |

> ⚠️ **Operational implication:** because everyone with a web login is a de-facto admin, the admin user list MUST be tightly controlled. Anyone who can reach `/users` can create new admin accounts. If you need real RBAC (e.g. "the branch accountant can edit clients but not change settings"), it has to be added — `app/Http/Middleware/chkAuthAdmin.php` is the place to inject a role check.

### 21.2 Mobile employee app — by role

The four `branch_staff.role` values gate **which scan events** an employee can submit. The matrix below reflects what the scan resolve / submit endpoints permit per role.

| Event type | MANAGER | RECEIVER | COURIER | AUDITOR |
|------------|:-:|:-:|:-:|:-:|
| `RECEIVED_AT_HUB` (first scan of a new sticker) | ✅ | ✅ | ❌ | ❌ |
| `RECEIVED_AT_HUB` (re-receive) | ✅ | ✅ | ❌ | ❌ |
| `IN_TRANSIT_INTERNAL` (dispatch to another branch) | ✅ | ✅ | ❌ | ❌ |
| `RECEIVED_AT_BRANCH` | ✅ | ✅ | ❌ | ❌ |
| `READY_FOR_PICKUP` | ✅ | ✅ | ❌ | ❌ |
| `DELIVERED_TO_CUSTOMER` | ✅ | ❌ | ✅ | ❌ |
| `RETURNED_TO_HUB` | ✅ | ✅ | ✅ | ❌ |
| `LOST` (mark lost) | ✅ | ❌ | ❌ | ❌ |
| `DAMAGED` (mark damaged) | ✅ | ✅ | ❌ | ❌ |
| Branch Queue read | ✅ | ✅ | ✅ | ✅ |
| Activity (own) | ✅ | ✅ | ✅ | ✅ |

Plus, regardless of role, **every** scan must target a branch the token has the `branch:N` ability for. A MANAGER for Branch 3 cannot register events in Branch 7 unless they also have an active assignment there.

### 21.3 Mobile client app — by client

Clients can only see their own data. Every `/api/*` client endpoint resolves the requesting client from the Sanctum token's `tokenable_id` and filters every query by that id at the controller level. Cross-client access is prevented by the query, not by middleware.

### 21.4 Public token endpoints

| Endpoint | Authorization |
|----------|--------------|
| `GET /proforma/{token}` | The token itself; rotation invalidates older tokens. |
| `GET /portal/{token}` | Same, scoped to a client portal session. |
| `GET /track/{code}` | Public; only the tracking code is needed. The tracking code is high-entropy (12 char Crockford base-32). Treat the code as semi-secret. |

---

## 22) Deployment architecture

### 22.1 Production topology

```
                  ┌──────────────────────────────────┐
                  │ Cloudflare (DNS + WAF + SSL)     │
                  │   - DNS records                  │
                  │   - DDoS protection              │
                  │   - WAF rules (block SQLi/XSS)   │
                  │   - TLS termination at edge      │
                  └────────────────┬─────────────────┘
                                   │  HTTPS
                                   ▼
                  ┌──────────────────────────────────┐
                  │ Nginx reverse proxy (TLS to LB)  │
                  │   - HTTP/2                       │
                  │   - Rate limit (low tier)        │
                  │   - Static asset cache           │
                  └────────────────┬─────────────────┘
                                   │  fastcgi
                                   ▼
        ┌──────────────────────────────────────────────────────┐
        │ Laravel app servers (1..N, stateless)                │
        │ ┌──────────────────────────────────────────────────┐ │
        │ │ PHP-FPM 8.2 — public/index.php                   │ │
        │ │ system/ codebase                                 │ │
        │ │ session driver: database (or redis)              │ │
        │ │ cache driver: redis                              │ │
        │ └──────────────────────────────────────────────────┘ │
        └──────┬───────────────────┬───────────────────┬───────┘
               │                   │                   │
               ▼                   ▼                   ▼
       ┌──────────────┐   ┌──────────────────┐   ┌──────────────┐
       │ MySQL primary│   │ Redis            │   │ Object store │
       │ (writes)     │   │  - cache         │   │  - storage/  │
       │ ┌──────────┐ │   │  - sessions      │   │     app/     │
       │ │ MySQL    │ │   │  - queue (opt.)  │   │     public/  │
       │ │ replica  │ │   └──────────────────┘   │ (S3 / B2)    │
       │ │ (reads,  │ │                          └──────────────┘
       │ │ failover)│ │
       │ └──────────┘ │
       └──────────────┘

        ┌──────────────────────────────────────────────────────┐
        │ Background workers (separate hosts, same codebase)   │
        │ ┌──────────────────────────────────────────────────┐ │
        │ │ queue worker(s)                                   │ │
        │ │   php artisan queue:work --queue=default \        │ │
        │ │     --sleep=3 --tries=3 --max-time=3600           │ │
        │ │ supervisord / systemd, restart on exit            │ │
        │ └──────────────────────────────────────────────────┘ │
        │ ┌──────────────────────────────────────────────────┐ │
        │ │ scheduler                                         │ │
        │ │   cron: * * * * * php artisan schedule:run        │ │
        │ │   runs: reminders, tracking reconcile, cleanup   │ │
        │ └──────────────────────────────────────────────────┘ │
        └──────────────────────────────────────────────────────┘

        ┌──────────────────────────────────────────────────────┐
        │ External integrations                                 │
        │   ShipsGo  ──webhook──▶ /api/v1/webhooks/shipsgo     │
        │   FCM      ◀──push──── queue worker                  │
        │   SMTP     ◀──email─── queue worker                  │
        └──────────────────────────────────────────────────────┘
```

### 22.2 Server roles

| Role | Hosts | Notes |
|------|-------|-------|
| Web (PHP-FPM + Nginx) | 1..N, stateless | Horizontally scale on CPU. Behind a single LB. |
| Queue worker | 1..N | Independent of web; restart-safe. Add hosts as job throughput grows. |
| Scheduler | 1 (singleton) | If you run multiple, use `withoutOverlapping()` on scheduled tasks or pin to one host. |
| MySQL primary | 1 | All writes. |
| MySQL replica | 1+ | Async replication. Optional read offload + failover target. |
| Redis | 1 (HA cluster optional) | Cache, sessions, optionally the queue. |
| Object store | S3 / B2 / similar | `storage/app/public/` — uploaded photos, PDFs. |

### 22.3 TLS / SSL

- Cloudflare provides TLS at the edge.
- Origin → Cloudflare also uses TLS (Cloudflare "Full (strict)" mode). Use a Let's Encrypt cert pinned to your origin hostname.
- Never run the origin over plain HTTP behind Cloudflare. Sanctum tokens flow on every request — clear-text would be a credential leak.

### 22.4 Firewall posture

Inbound from the internet:
- 443/tcp → Cloudflare IPs only (other source IPs blocked at the host firewall).
- 22/tcp → admin VPN / bastion only.

Inbound to MySQL / Redis:
- Only from the web and worker security groups. Never publicly exposed.

Outbound from app servers:
- 443/tcp to: Firebase (`fcm.googleapis.com`), ShipsGo API, SMTP relay, object store.

### 22.5 Backups

| What | Method | Frequency | Retention |
|------|--------|-----------|-----------|
| MySQL | `mysqldump --single-transaction` → encrypted upload to off-site bucket | Nightly | 30 days rolling + monthly archive 12 months |
| `storage/app/public/` | rsync / S3 sync to off-site bucket | Nightly | Same |
| `system/app/Http/Controllers/settings.json` | Included in the nightly database backup (one job tars settings + DB) | Nightly | Same |
| FCM service-account JSON, ShipsGo secret | Out of band (vault, password manager) | Manual on rotation | N/A |

Backups must be encrypted at rest. Test restoration **at least quarterly** — a backup you have never restored is not actually a backup.

### 22.6 Failover & disaster recovery

| Failure | Recovery |
|---------|----------|
| Single web host down | LB removes from rotation. No customer impact. |
| All web hosts down | Re-provision from image, deploy code, point LB. Time: 30–60 min if image is ready. |
| MySQL primary fails | Promote replica. Update app `.env` `DB_HOST`. Time: 10–30 min depending on replication lag. |
| Region-wide outage | Restore from off-site backup into a new region. Time: hours — measure during your DR drill. |
| Lost queue worker | Jobs accumulate in `jobs` table. Bring a worker back, it drains. No data loss for pending push notifications and webhooks. |
| Lost data via bad migration | Restore last nightly backup; re-apply diffs only if reproducible. **There is no rollback magic; backups are your only safety net.** |

---

## 23) Sequence diagrams — key flows

### 23.1 Client app login + authenticated request

```
Client App                        Laravel                          MySQL
    │                                │                                │
    │ POST /api/auth/login           │                                │
    │   { email_or_code, password,   │                                │
    │     device_name }              │                                │
    ├───────────────────────────────▶│                                │
    │                                │ throttle:login                 │
    │                                │  (5/min per identity,          │
    │                                │   20/min per IP)               │
    │                                │                                │
    │                                │ resolve client by code|email   │
    │                                │  + Hash::check(password)       │
    │                                ├───────────────────────────────▶│
    │                                │◀───────────────────────────────│
    │                                │                                │
    │                                │ createToken(device_name,       │
    │                                │   ['client'])                  │
    │                                ├───────────────────────────────▶│
    │                                │   INSERT personal_access_tokens│
    │                                │◀───────────────────────────────│
    │                                │                                │
    │ 200 { token, user }            │                                │
    │◀───────────────────────────────│                                │
    │                                │                                │
    │ store token in secure storage  │                                │
    │                                │                                │
    │ GET /api/balances              │                                │
    │ Authorization: Bearer <token>  │                                │
    ├───────────────────────────────▶│                                │
    │                                │ Sanctum middleware:            │
    │                                │   look up token, hydrate user  │
    │                                ├───────────────────────────────▶│
    │                                │◀───────────────────────────────│
    │                                │                                │
    │                                │ BalanceController              │
    │                                │   $client = Auth::user();      │
    │                                │   query filtered by $client->id│
    │                                ├───────────────────────────────▶│
    │                                │◀───────────────────────────────│
    │                                │                                │
    │ 200 { balances: { ... } }      │                                │
    │◀───────────────────────────────│                                │
```

> Note: `config/sanctum.php` has `'expiration' => null` — tokens do not auto-expire. Revoke via `POST /api/devices/{id}/revoke` or `POST /api/auth/logout`.

### 23.2 Employee scan flow (online + offline)

```
Employee App                    Laravel                    MySQL          Notification
    │                              │                          │              service
    │ user taps "Scan QR sticker"  │                          │                │
    │                              │                          │                │
    │ camera reads                 │                          │                │
    │   shipflow://qr/01HXY...     │                          │                │
    │                              │                          │                │
    │ POST /api/v1/employee/scan/  │                          │                │
    │      resolve                 │                          │                │
    │   { sticker_id }             │                          │                │
    ├─────────────────────────────▶│                          │                │
    │                              │ Sanctum auth +           │                │
    │                              │ EnforceBranchScope       │                │
    │                              │ ScanController::resolve  │                │
    │                              │   look up sticker,       │                │
    │                              │   last event, allowed    │                │
    │                              │   transitions per role   │                │
    │                              ├─────────────────────────▶│                │
    │                              │◀─────────────────────────│                │
    │                              │                          │                │
    │ 200 { state, current_event,  │                          │                │
    │       allowed_event_types }  │                          │                │
    │◀─────────────────────────────│                          │                │
    │                              │                          │                │
    │ user picks action, notes,    │                          │                │
    │ piece_id (if first scan)     │                          │                │
    │                              │                          │                │
    │ POST /api/v1/employee/scan/  │                          │                │
    │      submit                  │                          │                │
    │   Idempotency-Key: <UUID>    │                          │                │
    │   { sticker_id, event_type,  │                          │                │
    │     branch_id, notes, ... }  │                          │                │
    ├─────────────────────────────▶│                          │                │
    │                              │                          │                │
    │            ┌─── ONLINE PATH ─┴──┐                       │                │
    │            ▼                    │                       │                │
    │   validate transition,          │                       │                │
    │   check (kind, client_event_id) │                       │                │
    │   unique-index, INSERT          │                       │                │
    │   tracking_events,              │                       │                │
    │   if first scan: UPDATE         │                       │                │
    │   stickers.shipment_piece_id    │                       │                │
    │                                 │──▶                    │                │
    │                                 │◀──                    │                │
    │                                 │                       │                │
    │   dispatch NotifyClient job     │──────────────────────────────────────▶│
    │                                 │                       │                │
    │   200 { event_id, occurred_at } │                       │     (worker    │
    │◀────────────────────────────────│                       │     picks job  │
    │   invalidate Branch Queue       │                       │     and pushes │
    │   + Activity cache              │                       │     to client) │
    │                                 │                       │                │
    │            ┌─── OFFLINE PATH ───┴──┐                    │                │
    │            ▼                       │                    │                │
    │   POST fails (no connection)       │                    │                │
    │   Drift DB: enqueue Outbox row     │                    │                │
    │   show "queued" snackbar           │                    │                │
    │                                    │                    │                │
    │   ...later, connectivity returns   │                    │                │
    │   OutboxDrainer wakes              │                    │                │
    │   re-POSTs with same               │                    │                │
    │   Idempotency-Key                  │                    │                │
    │   → server INSERTs once            │                    │                │
    │     (or 200s silently the          │                    │                │
    │     duplicate via unique index)    │                    │                │
```

### 23.3 ShipsGo webhook

```
ShipsGo                  Nginx + Laravel               Queue worker          MySQL
   │                          │                              │                 │
   │ POST /api/v1/webhooks/   │                              │                 │
   │   shipsgo                │                              │                 │
   │ X-Signature: <HMAC>      │                              │                 │
   │ body: { shipment: ... }  │                              │                 │
   ├─────────────────────────▶│                              │                 │
   │                          │ throttle:60,1                │                 │
   │                          │                              │                 │
   │                          │ ShipsGoWebhookVerifier::     │                 │
   │                          │   verify():                  │                 │
   │                          │   hash_hmac('sha256',        │                 │
   │                          │     rawBody, SECRET)         │                 │
   │                          │   timingSafeEqual()          │                 │
   │                          │                              │                 │
   │                          │ INSERT webhook_deliveries    │                 │
   │                          │   (provider, external_event_ │                 │
   │                          │    id, payload, signature,   │                 │
   │                          │    received_at)              │                 │
   │                          │   UNIQUE(provider,           │                 │
   │                          │     external_event_id)       │                 │
   │                          ├─────────────────────────────▶│  (write only)   │
   │                          │                              ├────────────────▶│
   │                          │◀─────────────────────────────│                 │
   │                          │                              │                 │
   │                          │ dispatch(ProcessShipsGo      │                 │
   │                          │   WebhookJob(delivery_id))   │                 │
   │                          ├─────────────────────────────▶│                 │
   │ 202 Accepted             │                              │                 │
   │◀─────────────────────────│                              │                 │
   │                          │                              │ worker picks    │
   │                          │                              │ ProcessShipsGo  │
   │                          │                              │ WebhookJob:     │
   │                          │                              │ parse v2 payload│
   │                          │                              │ for each event: │
   │                          │                              │   INSERT into   │
   │                          │                              │   tracking_     │
   │                          │                              │   events with   │
   │                          │                              │   kind=INTL,    │
   │                          │                              │   client_event_ │
   │                          │                              │   id = ShipsGo  │
   │                          │                              │   event id      │
   │                          │                              │   (unique idx   │
   │                          │                              │   dedupes)      │
   │                          │                              ├────────────────▶│
   │                          │                              │◀────────────────│
   │                          │                              │                 │
   │                          │                              │ UPDATE webhook_ │
   │                          │                              │ deliveries SET  │
   │                          │                              │ processed_at    │
   │                          │                              ├────────────────▶│
```

### 23.4 Period close

```
Admin (web)                  Laravel                              MySQL
   │                            │                                   │
   │ POST /accounting/          │                                   │
   │      periods/{id}/close    │                                   │
   ├──────────────────────────▶ │                                   │
   │                            │ chkAuthAdmin                      │
   │                            │                                   │
   │                            │ accountingController::            │
   │                            │   periodClose()                   │
   │                            │                                   │
   │                            │ TrialBalanceService::             │
   │                            │   forPeriod(period)               │
   │                            ├──────────────────────────────────▶│
   │                            │   SUM(dr), SUM(cr) GROUP BY       │
   │                            │   account_code, currency          │
   │                            │   WHERE posted_at BETWEEN ...     │
   │                            │◀──────────────────────────────────│
   │                            │                                   │
   │                            │ if any currency unbalanced:       │
   │                            │   abort(422, "trial balance       │
   │                            │   does not balance: <details>")   │
   │                            │                                   │
   │                            │ DB::transaction:                  │
   │                            │   UPDATE accounting_periods       │
   │                            │     SET status='closed',          │
   │                            │     closed_at=now(),              │
   │                            │     closed_by=user_id             │
   │                            │   WHERE id=? AND status='open'    │
   │                            ├──────────────────────────────────▶│
   │                            │                                   │
   │                            │ AuditLogService::log(             │
   │                            │   'audit.action.period_close',    │
   │                            │   ...)                            │
   │                            ├──────────────────────────────────▶│
   │ 200 { status: 'closed' }   │                                   │
   │◀───────────────────────────│                                   │
```

---

## 24) Monitoring & alerting

ShipFlow ships without an opinionated monitoring stack. This section describes what you should put in front of it and what to watch.

### 24.1 What to monitor

| Signal | Source | Why it matters | Threshold |
|--------|--------|----------------|-----------|
| HTTP 5xx rate | Nginx access log or APM | Catches uncaught exceptions, DB outages | > 1% over 5 min |
| HTTP p95 latency | Nginx access log or APM | Catches slow DB queries, blocking calls | > 500 ms over 5 min |
| Queue depth | `SELECT COUNT(*) FROM jobs` | Worker stuck → push notifications + webhook processing fall behind | > 500 jobs / > 10 min old |
| Failed job count | `SELECT COUNT(*) FROM failed_jobs` | Reveals bugs in async paths | Any new failed job |
| Queue worker uptime | systemd / supervisor | If the worker dies silently, queue grows quietly | Not running for > 1 min |
| MySQL replication lag | `SHOW SLAVE STATUS` (or replica equivalent) | Big lag = stale reads on replica | > 60 s |
| Disk usage on web/db hosts | node-exporter / cloud monitoring | Disk full → DB stops accepting writes | > 80% |
| Webhook delivery success | `webhook_deliveries.processed_at IS NULL` count | Carrier integration broken | > 50 unprocessed / > 1 h old |
| Trial Balance drift | `/accounting/drift` (write a small CLI to scrape) | The whole accounting model is broken | Any non-zero drift |
| Failed login spike | `audit_log` action=login_failed | Credential stuffing attempt in progress | > 100/min globally |
| Push delivery success | FCM response codes (logged by NotifyService) | Mass FCM failures = silent client app | > 5% failure rate |

### 24.2 Recommended tools

| Layer | Suggested tools |
|-------|-----------------|
| Errors | **Sentry** (`sentry/sentry-laravel`) — install, set `SENTRY_LARAVEL_DSN`, you get unhandled exceptions + slow query alerts. |
| APM | Sentry Performance or New Relic — adds request traces, breaks down DB time vs CPU time per route. |
| Logs | Laravel default → stack channel writes to `storage/logs/laravel.log`. In prod, ship to **Loki**, **CloudWatch Logs**, or **Datadog** with daily rotation (`LOG_CHANNEL=daily`, retain 14 days). |
| Live tail | `php artisan pail` — built-in tail of the log file for development and incident triage. |
| Uptime | **UptimeRobot** / **Pingdom** / **Better Uptime** — check `https://your-host/up` every minute. (Laravel ships `/up` as a health endpoint.) |
| Queue | **Horizon** if you switch to Redis-backed queue. With the default DB driver, write a 1-minute cron that pages on `COUNT(jobs)` thresholds. |
| Metrics | **Prometheus + Grafana** — node-exporter on every host, mysqld-exporter on DB, custom exporter for app-specific gauges (open trial-balance drift, failed jobs). |
| MySQL slow log | `slow_query_log=ON, long_query_time=1` — review weekly. |

### 24.3 Alerting policy

Tier alerts by impact:

| Tier | Examples | Who pages | Response time |
|------|----------|-----------|---------------|
| P1 — outage | Site 5xx > 50%, MySQL down, queue worker dead > 5 min, drift detected | On-call ops engineer | 15 min |
| P2 — degraded | p95 latency > 1 s, queue depth growing, replication lag > 5 min | On-call ops engineer (during business hours) | 1 hour |
| P3 — informational | Single failed job, single webhook unprocessed, settings.json changed | Slack channel, no page | next business day |

### 24.4 Built-in observability we already have

You don't need to install anything to use these:

- **`/audit`** — every meaningful state change. The first place to look when "who did X?"
- **`webhook_deliveries`** table — every ShipsGo webhook with payload + signature + processed timestamp. Replay queries are trivial.
- **`employee_action_logs`** — every scan attempt with IP, user agent. Catches anomalous device activity.
- **`failed_jobs`** table — the queue's last-resort dump. Inspect with `php artisan queue:failed`.
- **`/accounting/drift`** — programmatic drift check.

---

## 25) Security model

### 25.1 Authentication

| Surface | Mechanism | Notes |
|---------|-----------|-------|
| Web admin | Session cookie (Laravel session driver), HttpOnly, SameSite=Lax | Behind HTTPS only. `chkAuthAdmin` middleware. |
| Web client portal | Same session, separate guard | `chkAuthClient` middleware. |
| Mobile (both apps) | Sanctum personal access tokens, bearer header | `Authorization: Bearer <token>`. |
| Employee scan API | Sanctum tokens **with `branch:N` abilities** | Token cannot scan in a branch unless the ability is on the token. |
| Public proforma portal | Per-request rotating token in URL | Token can be rotated by staff to invalidate older shares. |

### 25.2 Password handling

- Stored as **bcrypt** (`Hash::make($pw)`), cost factor 12 (Laravel default).
- Never logged, never sent in webhook payloads, never written to receipts.
- A self-serve reset flow exists at `/password/request` → `/password/reset/{token}` (`App\Http\Controllers\Auth\PasswordResetController`, gap #7 in `docs/GAPS.md`). **Email delivery depends on `MAIL_MAILER`:**
  - If `MAIL_MAILER=smtp` (or any real driver), reset link is emailed to the user. Self-serve works end-to-end.
  - If `MAIL_MAILER=log` (the default in the local dev `.env` as of 2026-06-08), the email goes to `storage/logs/laravel.log` instead. From the user's point of view, reset is **admin-mediated**: they request a reset, an admin greps the log for the link and forwards it. Set a real `MAIL_MAILER` to remove this manual step.
- The legacy `pass_txt` column was dropped in `2026_05_13_120000_drop_pass_txt_columns.php`.
- The legacy `pass_txt` column was dropped in `2026_05_13_120000_drop_pass_txt_columns.php`.

### 25.3 Token lifecycle

- Sanctum tokens are issued by `POST /api/auth/login` (clients) and `POST /api/v1/employee/auth/login` (employees).
- `config/sanctum.php` sets `'expiration' => null` — **tokens do not expire automatically.**
- Revocation paths:
  - User clicks logout in-app → `POST /api/auth/logout` deletes the row.
  - Admin removes a user from `branch_staff` → existing tokens still authenticate but lose their `branch:N` ability and are blocked at scan time.
  - Admin can directly `DELETE FROM personal_access_tokens WHERE tokenable_id = ?` to revoke all of a user's tokens at once.
- Operational implication: **a leaked token is valid until explicitly revoked.** Treat the bearer token like a password. If a phone is lost, revoke via the admin tinker:
  ```bash
  php artisan tinker --execute="\App\Models\Client::find(101)->tokens->each->delete();"
  ```

### 25.4 Rate limiting

Defined in `app/Providers/AppServiceProvider.php` and the route files:

| Limiter | Limit | Scope | Defends against |
|---------|-------|-------|-----------------|
| `throttle:login` | 5/min per email + 20/min per IP | Web admin login (`POST /auth/user/login`) | Credential stuffing — per-IP-only limits are easily evaded by distributed attacks; per-identity catches the "one account, many IPs" case |
| `throttle:30,1` | 30/min | Misc web endpoints | General abuse |
| `throttle:60,1` | 60/min | `/api/*` + `/api/v1/employee/*` | Normal mobile app traffic ceiling |

If you sit ShipFlow behind Cloudflare, add platform-level WAF rules on top: rate-limit `/auth/user/login` and `/api/auth/login` aggressively (e.g. 10/min/IP) so the attack never reaches the origin.

### 25.5 Webhook verification

`ShipsGoWebhookVerifier::verify()` computes `hash_hmac('sha256', rawBody, SECRET)` and compares with the signature header using a timing-safe comparison. Unverified payloads are rejected with HTTP 401.

The shared secret lives in `config('tracking.shipsgo.webhook_secret')`, read from `SHIPSGO_WEBHOOK_SECRET` in `.env`. Rotate it:

1. Generate a new secret. Set it as a second value in the ShipsGo portal (most providers support overlap during rotation).
2. Deploy `.env` with the new value to all app hosts.
3. Wait until you see no traffic on the old secret (check `webhook_deliveries.signature` traces).
4. Remove the old secret from ShipsGo.

### 25.6 Input validation

- Every controller action takes a `Request`; financial actions use Form Requests or inline `validate()` calls. **No raw `$_POST` reads.**
- Eloquent + query builder everywhere — no string-concatenated SQL. Raw expressions (`DB::raw`) are confined to reporting aggregations.
- All Blade output is HTML-escaped by default (`{{ $var }}`). Raw output (`{!! ... !!}`) is only used for trusted PDF templates.

### 25.7 CSRF

- Web admin and client portal routes are CSRF-protected by Laravel's default `VerifyCsrfToken` middleware. Every POST form includes `@csrf`.
- `/api/*` routes are stateless (Sanctum bearer tokens) and excluded from CSRF, as expected.
- Webhook endpoints (`/api/v1/webhooks/shipsgo`) are stateless and CSRF-excluded; HMAC verification is the substitute.

### 25.8 SQL injection / XSS / file upload

- Eloquent guards SQLi everywhere. The few places using `DB::raw()` accept only bound parameters.
- Blade auto-escapes; XSS surface is minimal.
- File uploads (sourcing item photos, proforma attachments):
  - Stored under `storage/app/public/` with a hashed filename — original filenames are never used as paths.
  - MIME type is validated server-side.
  - Files are served via Laravel's `storage/{path}` route or signed S3 URLs — never directly executed.
  - No file is loaded into PHP via `include` / `require`.

### 25.9 Audit log immutability

The `audit_log` table has no UPDATE or DELETE endpoint in the application code. To truly enforce immutability at the DB level:

- Create a dedicated MySQL user `shipflow_audit` with INSERT-only on `audit_log`.
- Revoke DELETE/UPDATE on `audit_log` from the application's main DB user.
- Trade-off: a compromised DB-admin credential can still drop the table. Mitigate with off-site backup retention and binlog shipping.

### 25.10 Secrets management

| Secret | Where it lives | How to rotate |
|--------|----------------|---------------|
| `APP_KEY` | `.env` | Rotating requires re-encrypting all encrypted column values. Avoid unless compromised. |
| `DB_PASSWORD` | `.env` | Rotate in MySQL, deploy new `.env`, restart PHP-FPM, restart workers. |
| `SHIPSGO_API_KEY` | `.env` | Rotate in ShipsGo portal, deploy new `.env`. |
| `SHIPSGO_WEBHOOK_SECRET` | `.env` | See 25.5. |
| FCM service account JSON | filesystem path from `FCM_CREDENTIALS_PATH` | Generate new in Firebase Console, drop in place, restart workers, revoke old. |
| `print_pin_hash` | `settings.json` | Re-hash via Settings page → Print PIN field. |

`.env` is never committed. `system/app/Http/Controllers/settings.json` is committed but contains only bcrypt hashes for the print PIN, no plaintext secrets.

### 25.11 Data retention

ShipFlow has no automatic data retention / purge policy out of the box. By default everything is retained forever. For GDPR / similar regimes, you'll need to add:

- A "delete account" path for clients (Laravel cascade deletes will cascade to `clients_transactions`, `client_devices`, `notifications` — but **not** to `audit_log` or `tracking_events`, which carry references rather than FKs).
- A retention policy for `webhook_deliveries.payload` — payloads can be large; consider purging payloads > 90 days, keeping only the metadata row.
- A retention policy for `failed_jobs` — purge entries > 30 days after triage.

### 25.12 Backup encryption

- Backups must be encrypted at rest. Use `gpg --symmetric --cipher-algo AES256` or your storage provider's server-side encryption (S3 SSE-KMS, B2 native).
- Symmetric key for backup encryption is **not** in `.env` — store in your secrets vault. Loss of the key = loss of recovery.
- Test decryption + restoration on every quarterly DR drill.

### 25.13 Mobile app security

| Concern | Mitigation |
|---------|------------|
| Token stored on phone | Both apps use `flutter_secure_storage` (Keychain / Keystore). Not stored in shared preferences. |
| Token reuse after device loss | Revocation via `DELETE personal_access_tokens` (see 25.3). Client app also offers biometric unlock to add a local barrier. |
| MITM on hostile WiFi | TLS-only API base URL. App will fail to connect on `http://` outside dev mode. |
| Reverse engineering | The mobile app is a Sanctum client — there is no signing secret embedded. The worst-case from reversing is learning the endpoint shape. |
| FCM token exposure | FCM tokens are device identifiers, not credentials — exposure does not allow impersonation. |

### 25.14 Security checklist for ops engineers

Run through this list quarterly:

- [ ] All `.env` secrets rotated within the last 12 months.
- [ ] No web admin accounts belong to departed staff.
- [ ] `branch_staff.is_active=false` for all departed employees.
- [ ] `failed_jobs` table reviewed weekly; chronic failures fixed at the root.
- [ ] Rate limits in `AppServiceProvider` reviewed for whether traffic patterns have changed.
- [ ] Cloudflare WAF rule for login throttling tested (purposefully trigger and confirm 429 returns).
- [ ] Backup restoration tested on a staging host this quarter.
- [ ] TLS certificates have > 30 days before expiry.
- [ ] OS packages on web / db / worker hosts patched within the last 30 days.

---

**End of manual.**

If you find a section that's wrong or outdated, fix the code, then fix the manual. If you find a section that's missing, add it — the manual is meant to be the canonical reference, not a one-time write-up.
