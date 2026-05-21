# ALIGNMENT PATCH — New Features → Existing system/

> **Status:** authoritative. Supersedes anything in the two source spec folders
> (`New Features/tracking new/` and `New Features/purchaces/`) where they
> conflict with what's actually in `system/`.
>
> **Audience:** anyone (human or Claude) implementing the tracking and purchases
> modules. Read this **before** the original spec docs.
>
> **Last updated:** 2026-05-21.

---

## 1. Why this doc exists

The original specs (~7,500 lines across 9 markdown files plus a 95-file Laravel
bootstrap zip) were written assuming a clean Laravel app. The actual `system/`
app is Laravel 12 **with a real, already-shipped surface**:

- Customer mobile API live at `/api/*` (no version prefix), Sanctum tokens.
- Real double-entry accounting (`chart_of_accounts`, `journal_entries`,
  `journal_lines`) with established conventions and seeded account codes.
- Existing wallet ledger (`clients_transactions`).
- Existing devices (`client_devices`) + in-app notifications (`notifications`).
- Existing shipment surface (`/api/shipments/{mode}/{id}` with `mode=sea|sky`).
- A legacy CodeIgniter-style controller layer (`accountingController`,
  `journalController`, `skyController`, etc.) that the new code must coexist
  with — not replace.

Three categories of conflict between the specs and reality:

1. **Structural** — the two new spec folders disagree with each other
   (modular monolith for purchases, flat `app/Http/Controllers/Api/V1/...` for
   tracking).
2. **Schema collisions** — bootstrap zip ships migrations for tables that
   already exist (`journal_entries`, `wallet_transactions`).
3. **Account code collisions** — purchases spec's account codes (1100, 1200,
   1300, 2100, 4100, 5100) name **different things** than the existing
   `chart_of_accounts` rows with those same codes. Building on the spec
   verbatim would silently misroute money.

This doc locks in the resolution. Decisions below are binding.

---

## 2. Decisions

### 2.1 Module layout

| Module    | Path                          |
|-----------|-------------------------------|
| Purchases | `app/Modules/Purchases/`      |
| Tracking  | `app/Modules/Tracking/`       |

Tracking follows the same modular layout as purchases. The tracking spec's
`app/Http/Controllers/Api/V1/Mobile/...` paths are **wrong** — those become
`app/Modules/Tracking/Http/Controllers/...` with routes loaded from
`routes/tracking.php`.

PSR-4: `App\Modules\` autoload entry added to `composer.json`. Each module
ships a `Providers/{Module}ServiceProvider.php` that registers its routes,
migrations (none — kept under `database/migrations/` for unified ordering),
config, lang files, and event listeners.

### 2.2 API versioning

| Surface                                    | Path prefix                |
|--------------------------------------------|----------------------------|
| **Existing customer API** (shipped)         | `/api/*` (unchanged)       |
| **Customer tracking** (extends existing)    | `/api/shipments/...` (unchanged) |
| **Employee app API** (new)                  | `/api/v1/employee/*`       |
| **ShipsGo webhooks** (new)                  | `/api/v1/webhooks/shipsgo` |
| **Internal admin** (if any)                 | `/api/v1/admin/*`          |

Rationale: the existing `/api/*` routes are already on customer phones.
Re-prefixing them to `/api/v1/*` breaks every install. The unified timeline
gets bolted onto `/api/shipments/{mode}/{id}` so the customer app gets it
"for free" via an app update, not a routing migration.

New surfaces (employee, webhooks) start at `/v1` so they can evolve
independently.

### 2.3 Accounting — reuse, do not re-create

Bootstrap zip ships `2026_05_21_000013_create_journal_entries_table.php`.
**Drop this migration on merge.** The existing `journal_entries` +
`journal_lines` (from `2026_05_15_200000_create_journal_entries_table.php`)
are the canonical tables.

The existing convention (see `app/Http/Controllers/journalController.php`):

```php
// One entry, N lines, per-currency balanced.
journal_entries:  entry_date, posted_at, posted_by_user_id, kind, description,
                  source_table, source_id, transaction_number, branch_id,
                  reverses_entry_id, status
journal_lines:    entry_id, line_no, account_id, account_code, account_name,
                  dr, cr, currency, description, counterparty_type,
                  counterparty_id, branch_id
```

`AccountingIntegrationService` in the purchases module is rewritten to:

1. Look up `chart_of_accounts` by code (raises if missing).
2. Build `journal_entries` row with `kind = 'purchase.*'`, `source_table = 'purchase_orders'`, `source_id = $order->id`.
3. Insert balanced `journal_lines` per currency.
4. Use existing reversal helper for cancellations.

No new `journal_entries` table. No parallel ledger.

### 2.4 Account code mapping — the critical correction

The purchases spec's account code list **collides semantically** with the
existing `chart_of_accounts`. Side-by-side:

| Code | Existing meaning                | Spec claimed meaning      | Resolution |
|------|---------------------------------|---------------------------|------------|
| 1000 | Cash on hand                    | Cash / bank               | Reuse existing |
| 1100 | AR — clients                    | Customer wallets          | Use **2000** (existing client deposits, liability) for wallet, **not** 1100 |
| 1200 | Prepaid to suppliers            | Buyer custody             | Use new **1250** for buyer custody (USD) |
| 1300 | Prepaid to customs brokers      | Purchases in transit      | Use new **1320** for purchases in transit |
| 1400 | (unused)                        | Warehouse inventory       | Use **1400** for warehouse inventory ✅ |
| 1500 | (unused)                        | Goods in shipment         | Use **1500** for goods in shipment ✅ |
| 2100 | AP — suppliers                  | Wallet liabilities        | **Do not use 2100**. Wallet liability is 2000 (existing) |
| 4000 | Commission revenue              | —                         | Reuse for purchases commission |
| 4100 | Shipping revenue                | Commission revenue        | **Do not collide**. Use existing 4000 for commission |
| 4200 | (unused)                        | FX gain                   | Use **4200** for FX gain ✅ |
| 5100 | Owner's salary                  | COGS delivered            | Use new **5400** for COGS delivered |
| 5200 | FX gain/loss                    | FX loss                   | Reuse existing 5200 for FX loss (gain goes to 4200) |

**Net new account codes to seed for purchases** (in a new seeder
`PurchasesChartOfAccountsSeeder.php`):

| Code | Name (en)            | Type      | Normal | derivation_key      |
|------|----------------------|-----------|--------|---------------------|
| 1250 | Buyer custody (USD)  | asset     | debit  | buyer_custody       |
| 1320 | Purchases in transit | asset     | debit  | purchases_in_transit|
| 1400 | Warehouse inventory  | asset     | debit  | warehouse_inventory |
| 1500 | Goods in shipment    | asset     | debit  | goods_in_shipment   |
| 4200 | FX gain              | revenue   | credit | fx_gain             |
| 5400 | COGS — delivered     | expense   | debit  | cogs_delivered      |

`config/purchases.php` exposes these as constants — services reference
`config('purchases.accounts.buyer_custody')`, never a hardcoded `'1250'`.

### 2.5 Wallet — reuse existing `clients_transactions`

Bootstrap zip ships `2026_05_21_000012_create_wallet_transactions_table.php`.
**Drop this migration on merge.** The customer wallet lives in
`clients_transactions` (used by `skyController`, `dataController`, etc.).

`WalletIntegrationService` in the purchases module:

- Reads available balance via the existing `ClientBalanceService`.
- Writes debit/credit via inserts into `clients_transactions` with a new
  `type` value (e.g. `purchase_debit`, `purchase_refund`).
- Uses `lockForUpdate()` on the client row before computing balance.

The `BuyerAccount` / `BuyerTransaction` ledger from the bootstrap stays as-is
— that's the **buyer custody** ledger (purchasing agent's USD float), which
is genuinely new. Don't confuse it with the customer wallet.

### 2.6 Devices + notifications — reuse

Existing tables `client_devices` and `notifications` cover customer push.
Tracking notifications to customers fan out through the existing
`NotificationController` patterns.

For **employee** push (new), add `employee_devices` and `employee_notifications`
tables in Phase 2 (the schema is identical — same FCM/APNs token model — just
keyed to `users` instead of `clients`).

### 2.7 Mobile project structure

Keep existing `mobile/` as the customer app. **Do not restructure** to
`apps/customer/` + `apps/employee/`. Add a new sibling `mobile_employee/`
Flutter project in Phase 6. The two apps share nothing in code — different
auth, different distribution. A shared `shipflow_core` Dart package can be
extracted later if friction shows up.

### 2.8 White-label sanitization — softer than the spec

Original spec (SYSTEM_OVERVIEW §5.8): middleware throws HTTP 500 if forbidden
patterns (`/shipsgo/i`) appear in any mobile JSON response.

**Change:** behavior is environment-dependent.

| Env           | Behavior on match           |
|---------------|-----------------------------|
| `local`, `testing` | **Throw** — catches leaks in dev/CI |
| `production`  | **Log + alert + strip + serve** — never break a customer read because of a string leak |

Implementation in `EnforceMobileSanitization::handle()`. The "strip" step
runs a regex replace on the serialized JSON before returning the response.

### 2.9 Webhook dedup — at insert, not just in the job

Original spec relies on the queued job being idempotent. **Additionally**,
`webhook_deliveries` gets a `UNIQUE(provider, external_event_id)` constraint
so ShipsGo retries fail at insert time and we return 200 immediately without
dispatching another job. The job stays idempotent as defense in depth.

### 2.10 Queue driver

Both modules target `QUEUE_CONNECTION=database` for dev parity with `system/`
today. Prod target is **Redis**. When Redis lands, no code changes — just
`.env` flip. No code should call queue-specific APIs.

### 2.11 Idempotency on writes

All mobile write endpoints (employee scans, purchase creation) require an
`Idempotency-Key` header. Server stores `(client_id, key) -> response_hash`
in a small `idempotency_keys` table (TTL 24h). Re-sending the same key
returns the cached response, never re-executes the side effect.

This is **new** (not in either spec verbatim). Added in Phase 2 alongside
the tracking scaffold.

### 2.12 Branch scope

Employee tokens carry a `branch_id` claim (stored in the Sanctum token's
`abilities` array as `branch:N`). Middleware `EnforceBranchScope` rejects
scan attempts on shipments not currently routed to that branch (per the
custody chain). This protects against employee A in Tripoli marking a
shipment received in Misrata.

---

## 3. What stays from the original specs (unchanged)

- Event-sourced `tracking_events` with a `kind` discriminator
  (`INTERNATIONAL` / `INTERNAL`).
- Unified timeline computed at the backend, not the client.
- Sticker is dumb (encodes ULID only), backend resolves allowed actions on scan.
- Sanctum + device-bound tokens (token `name` holds device id).
- BCMath everywhere money is touched; append-only `BuyerTransaction`.
- Frozen `exchange_rate_id` + `frozen_exchange_rate` on purchase orders at
  CONFIRM time.
- Spike protection with `PENDING_APPROVAL` rate status.
- Webhook → fast 200 → queued worker pattern.
- State machine with Guards + Effects for both purchases and internal tracking.
- Per-currency journal balance assertion (already how `journalController`
  works — we just match it).

---

## 4. Deferred decisions (resolve when their phase needs them)

| Question | Resolve in | Default if no answer when needed |
|----------|------------|----------------------------------|
| QR per piece vs per shipment | Phase 4 (QR stickers) | Per piece — uses existing `shipment_pieces` |
| Customer pickup verification (ID vs in-app code) | Phase 5 (Employee API) | In-app 4-digit code + photo of customer |
| SMS provider for employee OTP | Phase 5 | Twilio (cheapest path to working) |
| Sticker PDF storage driver | Phase 4 | Local for dev, S3-compatible for prod (via `Storage::disk('private')`) |
| Photo retention period | Phase 7 | 180 days, then move to cold storage |

---

## 5. Phase order (binding)

| # | Phase                              | Done when |
|---|------------------------------------|-----------|
| 0 | Alignment + foundation              | This doc + composer autoload merged |
| 1 | Purchases module                    | Bootstrap merged, accounting adapted, `php artisan test` passes |
| 2 | Tracking foundation                 | Schema migrated, models load, service skeletons compile |
| 3 | ShipsGo integration                 | Webhook → DB → job pipeline + idempotency tests |
| 4 | QR stickers                         | `php artisan stickers:generate` produces a printable PDF |
| 5 | Mobile API surface                  | Customer `/api/shipments/{mode}/{id}` returns unified timeline; employee scan flow round-trips |
| 6 | Flutter (customer + employee)       | Both apps build, scan-to-notify e2e works on real device |
| 7 | E2E test + ops commands             | Seed shipment walks Yiwu → Tripoli through 11 events; stuck-shipment job alerts |

Each phase is a separate set of commits with its own PR.

---

## 6. Files this patch will create or modify (Phase 0–1 only)

**Created:**
- `docs/ALIGNMENT_PATCH.md` (this file).
- `app/Modules/Purchases/...` (all bootstrap files except journal_entries / wallet_transactions migrations).
- `database/seeders/PurchasesChartOfAccountsSeeder.php`.
- `routes/purchases.php`.

**Modified:**
- `composer.json` — add `"App\\Modules\\": "app/Modules/"` PSR-4 entry.
- `bootstrap/providers.php` — register `PurchasesServiceProvider`.
- `routes/api.php` — `require __DIR__.'/purchases.php';` inside the
  `auth:sanctum + client.sanctum` group.

**Adapted from bootstrap (not used verbatim):**
- `app/Modules/Purchases/Services/AccountingIntegrationService.php` — rewritten to use existing `journal_entries` + `journal_lines`.
- `app/Modules/Purchases/Services/WalletIntegrationService.php` — rewritten to use existing `clients_transactions`.
- `config/purchases.php` — account codes use the corrected mapping from §2.4.

**Skipped from bootstrap:**
- `database/migrations/2026_05_21_000012_create_wallet_transactions_table.php` (conflicts with `clients_transactions`).
- `database/migrations/2026_05_21_000013_create_journal_entries_table.php` (conflicts with existing).

---

End of patch.
