# Smoke test — full report, 2026-05-15

Driven via Playwright against `http://127.0.0.1:8002` as the seeded admin user. Every major workflow was exercised; numeric balances were checked against `branches.balance_*`, `clients.balance_*`, `treasury_transactions`, and the derived trial balance.

Severity tags:
- **P0** = data integrity, security, or "money is wrong"
- **P1** = workflow blocked, visibly broken, or surprising
- **P2** = cosmetic / inconvenient
- **🔮** = likely future bug — fragile pattern in code, no live reproduction

## Coverage matrix

| Area | Touched | Notes |
|---|---|---|
| Clients: create | yes | Password hashing via dropped `pass_txt` field still works |
| Clients: deposit | yes | Approved + pending paths, override path |
| Clients: withdraw | not in this run | Already verified earlier |
| Clients: withdraw_commission | not in this run | |
| Clients: currency transfer | yes | Found multiple issues |
| Clients: c2c transfer | yes | Worked once route name corrected |
| Clients: edit/save | yes | OK |
| Treasury: deposit_branch | yes | OK |
| Treasury: add_expenses | yes | OK (purpose now persisted thanks to earlier fix) |
| Treasury: transfer_branch | **fails 500** | Silent error, no log entry |
| Treasury: fix_branch | not in this run | |
| Suppliers: create + deposit | yes | OK; existence check added during this run |
| Brokers: create + deposit | yes | OK; existence check added during this run |
| Settings + FX rate change | yes | Snapshots to `fx_rate_history` ✓ |
| Approve/Reject pending | yes | Found multiple issues |
| Receipts: PDF + void | yes | OK |
| Accounting: trial balance | yes | Found + fixed plus_minus bug |
| Accounting: P&L + balance sheet + cash flow PDFs | yes | OK after plus_minus fix |
| Accounting: daily journal | yes | OK |
| Accounting: client/supplier/broker aging | yes | OK |
| Accounting: period close + override | yes | Found bypass on approveReject (now fixed) |
| Accounting: cash count + adjust | yes | Found + fixed dual-ledger gap |
| Accounting: prepayment auto-register + apply | yes | OK |
| Accounting: owners CRUD + ledger | yes | OK |
| Reconciliation | yes | Endpoint returns 200 with rows |
| Audit log | yes | All smoke events captured (29 rows) |

## Bugs found

### Fixed in this session

| # | Sev | Title | Fix |
|---|---|---|---|
| 1 | P0 | `assertCanAccessClient` short-circuited for admins without verifying the client exists. Posting to `id=99999` silently created orphan rows. Same hole on supplier_id and broker_id. | Existence check now runs first for every caller. Added `assertSupplierExists` + `assertBrokerExists` helpers and wired them into supplier/broker deposit + withdraw. |
| 2 | P0 | Trial balance derivation filtered `where('plus_minus','-')` and `'+'`, but the data stores the words `'plus'` / `'minus'`. Operating expenses, owner drawings, and owner salary were silently 0 — the owner's-equity plug absorbed everything. | All three derivations now use `'minus'`. Prepayment dangling detection now uses `'plus'`. |
| 3 | P0 | `approveReject` had no period-close guard. An admin could let a transaction sit pending and approve it after month-end to bypass the lock. | Added `$this->assertPeriodOpen($get->created_date)` immediately after the row lookup. |
| 4 | P0 | Cash count adjustment posted to `branches_transactions` only, never to `treasury_transactions`. It also used `'+'` / `'-'` for `plus_minus` instead of the system's `'plus'` / `'minus'`. So cash flow PDF and daily journal net-change cards were blind to cash-count corrections, and any future SQL filter on `plus_minus` would miss the row. | Adjustment now writes to both ledgers inside a single `DB::transaction`. Plus_minus uses the word form. Old row backfilled in this test DB. |
| 5 | P1 | `approveReject` returned HTTP 200 with empty body when the target row didn't exist. Caller couldn't tell success from silent no-op. | Returns `{type:'not_found'}` 404 on miss, `{type:'success'}` 200 on success. |

### Found but **not** fixed in this session (next-up backlog)

| # | Sev | Title | Repro / Notes |
|---|---|---|---|
| 6 | P0 | Approving a currency transfer never touches `treasury_transactions` and never updates the branch balance. Per-currency trial balance drifts (e.g. LYD: 405 liability with 0 LYD asset), owner's-equity plug absorbs it. | Reproduced by approving transfer id=989 in the test DB. The original `approveReject` already had this commented-out behavior, so it's a long-standing design gap, not a regression. Proper fix needs a journal entry for the swap (debit one currency cash, credit the other, plus FX gain/loss). |
| 7 | P1 | `transfer_branch` returns HTTP 500 with `{"type":"error"}` and no fresh entry in `laravel.log`. The controller's `try/catch` is swallowing it without flushing the log. | Reproduced by POSTing `/company/transfer_branch from=1 to=16 currency=usd value=50`. Need to find the actual exception — likely a missing field or a `purpose` column that the insert path doesn't set. |
| 8 | P1 | Currency transfer defaults to `status='pending'`. After we moved client deposit/withdraw to immediate approval, transfer is the odd one out — admins still have to chase the approval queue. | Either default to `approved` for admin role like the others, or surface a clearer "needs approval" badge in the UI. |
| 9 | P1 | `pass_txt` field still appears on the New Client form even though the `pass_txt` column was dropped. Works today because the controller hashes it into `password`, but the UI label and the front-end variable name are misleading. | `resources/views/pages/clients/new.blade.php` + `public/js/clients/clients.js:1021` still ship the legacy name. |
| 10 | P1 | Route naming inconsistency: route `/clients/transfer_client` (singular) → method `transfer_clients` (plural). Easy to get wrong when extending the JS. | `routes/web.php`. Renaming the route is a breaking change; renaming the method is internal. |
| 11 | P2 | Receipts can become orphans pointing at deleted `clients_transactions` rows when a pending transfer is created, a receipt is issued, then the transaction is deleted before approval. | r#3 and r#4 in the test DB point at deleted rows 988/989. Append-only receipts is the right call, but we should probably skip issuing a receipt until the transaction reaches `approved` status. |
| 12 | P2 | The cash injection (purpose=`cash_injection`) and cash-count over/short are classified as `inflow_client` / `other_inflow` in cash flow because the classifier only recognizes `owner_capital_in` / `cash_count_over`/`cash_count_short` keywords. | `accountingController::classifyCashMovement`. Add `cash_injection` → owner capital and `cash_count_over` / `cash_count_short` → their own bucket so the cash flow shows them on the right lines. |

## 🔮 Likely future bugs / fragile patterns

| # | Title | Why it'll bite |
|---|---|---|
| F1 | Two parallel cash ledgers (`branches_transactions` vs `treasury_transactions`) with no enforced invariant that every cash event writes to both. Every existing mutation today does the right thing, but the cash count bug (now fixed) showed how easy it is to write to only one. | Add a `treasuryController::recordCashMovement($...)` that writes to both inside a single transaction, and route every mutation through it. Or model it as one ledger and derive the per-branch breakdown. |
| F2 | Per-currency trial balance does not balance after currency transfers. Cross-currency it still does (at the current FX rate), so the owner's-equity plug absorbs the difference. An FX gain/loss account exists in the chart of accounts but nothing posts to it. | Currency transfer should generate explicit journal entries: `Dr Cash (to-ccy)`, `Cr Cash (from-ccy)`, plus FX gain/loss for any rate spread. Otherwise every conversion silently shifts the plug. |
| F3 | `pass_txt` form field still passed across the wire on update too — likely the legacy edit form does the same. Sending an empty `pass_txt` may overwrite the hashed `password` to an empty hash. | Verified once on /clients/save with `pass_txt` omitted — backend behavior on update is not yet checked. Worth a unit test. |
| F4 | The `id` parameter convention is dangerous. Most modules accept `id` meaning primary key, but the user-facing column is `code`. A typo in a client-side JS (or an operator typing the "code" in a URL) creates orphan transactions until the existence check now catches it. | The existence check (added today) is the first line of defense; the longer fix is to namespace the request param (`client_id` everywhere) and never accept `id` as ambiguous. |
| F5 | PHP 8.x deprecation: `number_format(null, ...)` is hit somewhere in `clientsController.php:538`. Will become a fatal error on PHP 9. | Coerce with `floatval()` or `?? 0` at the call site. |
| F6 | The controller's `try/catch` blocks swallow exceptions and return `{type:'error'}` 500. When `Log::error` is suppressed (custom log channel, file rotation, etc.) the user gets a useless error and the dev has no stack trace. | Either return the exception message (in dev only) or surface a request-id that ties the user's 500 back to a log line. |
| F7 | `assertPeriodOpen` enforces only on the **create** date. Back-dating a transaction by setting a `created_date` field doesn't exist today, but if a future feature adds one, the lock will trivially be bypassable. | When/if back-dating gets added, the period check needs to also fire on the requested date, not just today. |
| F8 | Plus_minus stored as the string `'plus'`/`'minus'` rather than `+1`/`-1` makes every SUM-with-sign query require either a CASE expression or two separate queries. It's the kind of schema choice that quietly bloats query code and makes off-by-one mistakes very likely (see #2 above). | Migrate to a signed integer (or store an explicit signed `delta` column on insert). High-cost refactor — not urgent. |
| F9 | Receipts are issued at the moment of insert, including for transfers with `status='pending'`. If the operator deletes the row before approval, the receipt becomes an orphan with a frozen counterparty label. | Move `issueReceipt` to fire on first approval, or mark the receipt as `provisional` until the row is approved and only assign the final `series_number` then. |

## What you can trust now vs what to watch

- **Trust**: trial balance is balanced and correctly categorizes expenses after today's plus_minus fix. AR/AP/cash all derive from real rows. Receipts are sequential and audit-logged. Period close enforcement now covers approval too.
- **Watch**: anything that touches multi-currency transfer math (item #6 above). The per-currency trial balance presentation will look wrong any time a transfer hasn't been settled by a matching physical-cash swap.

