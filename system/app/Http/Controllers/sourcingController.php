<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mpdf\Mpdf;

/**
 * Sourcing requests — "عمولة البحث عن البضاعة".
 *
 * Lifecycle: open → searching → quoted → accepted → fulfilled.
 * Any state can transition to canceled.
 *
 * Commission revenue is recognised on acceptQuote(), posted to CoA 4020
 * (Sourcing commission revenue) against 1100 (AR clients). The cash side
 * is left to the standard client deposit/withdraw flow — that's how the
 * existing commission accounting works elsewhere in the system.
 */
class sourcingController extends Controller
{
    private const STATUSES = ['open', 'searching', 'quoted', 'accepted', 'fulfilled', 'canceled'];

    /* ------------------------------------------------------------
     *  Index — shell + status filter (admin only).
     * ------------------------------------------------------------ */
    public function index()
    {
        $this->requireAdminOrBranchAdmin();
        $lang = new langController();
        return view('pages.sourcing.index', [
            'lang'    => $lang,
            'section' => 'sourcing',
            'page'    => 'sourcing',
        ]);
    }

    /* ------------------------------------------------------------
     *  AJAX table load (POST /sourcing/load).
     *  Query params: status, search, from, to.
     * ------------------------------------------------------------ */
    public function load(Request $request)
    {
        $this->requireAdminOrBranchAdmin();

        $status = $request->get('status');
        $search = trim((string) $request->get('search', ''));

        $q = DB::table('sourcing_requests as sr')
            ->leftJoin('clients as c',  'c.id',  '=', 'sr.client_id')
            ->leftJoin('branches as b', 'b.id',  '=', 'sr.branch_id')
            ->select(
                'sr.*',
                'c.name as client_name',
                'c.code as client_code',
                'b.name as branch_name'
            )
            ->orderBy('sr.id', 'desc');

        // Trash filter: by default hide trashed rows. ?trash=1 flips the
        // view to show only trashed rows (the explicit "show trash" mode).
        $showTrash = $request->boolean('trash');
        if ($showTrash) {
            $q->whereNotNull('sr.deleted_at');
        } else {
            $q->whereNull('sr.deleted_at');
        }

        if ($status && in_array($status, self::STATUSES, true)) {
            $q->where('sr.status', $status);
        }
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('sr.request_number', 'like', "%$search%")
                  ->orWhere('sr.title', 'like', "%$search%")
                  ->orWhere('c.name', 'like', "%$search%")
                  ->orWhere('c.code', 'like', "%$search%");
            });
        }
        if ($from = $request->get('from')) $q->whereDate('sr.created_at', '>=', $from);
        if ($to   = $request->get('to'))   $q->whereDate('sr.created_at', '<=', $to);

        // Branch scoping for non-admin: a branch admin only sees their branch.
        $user = auth()->user();
        if ($user->type !== 'admin') {
            $q->where('sr.branch_id', $user->branch);
        }

        $rows  = $q->limit(500)->get();
        $count = $q->count();

        $lang = new langController();
        $html = view('pages.sourcing.table', [
            'rows'  => $rows,
            'count' => $count,
            'lang'  => $lang,
        ])->render();

        return response()->json(['type' => 'success', 'html' => $html, 'count' => $count]);
    }

    /* ------------------------------------------------------------
     *  Show — detail page for one request, with quotes.
     * ------------------------------------------------------------ */
    public function show($id)
    {
        $this->requireAdminOrBranchAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);

        $this->assertCanAccessClient($req->client_id);

        $client = DB::table('clients')->where('id', $req->client_id)->first();
        $branch = $req->branch_id
            ? DB::table('branches')->where('id', $req->branch_id)->first()
            : null;

        $quotes = DB::table('sourcing_request_quotes as q')
            ->leftJoin('suppliers as s', 's.id', '=', 'q.supplier_id')
            ->select('q.*', 's.name as supplier_name')
            ->where('q.sourcing_request_id', $id)
            ->orderBy('q.id', 'desc')
            ->get();

        $journalEntry = null;
        if ($req->commission_journal_entry_id) {
            $journalEntry = DB::table('journal_entries')
                ->where('id', $req->commission_journal_entry_id)
                ->first();
        }

        // Proforma data: line items + photos + payment schedule. All three
        // join eagerly so the show page is one round-trip per section.
        $items = DB::table('sourcing_request_items')
            ->where('sourcing_request_id', $id)
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $photos = [];
        if (count($items) > 0) {
            $itemIds = $items->pluck('id')->all();
            $photoRows = DB::table('sourcing_request_item_photos')
                ->whereIn('item_id', $itemIds)
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
            foreach ($photoRows as $p) {
                $photos[$p->item_id] = $photos[$p->item_id] ?? [];
                $photos[$p->item_id][] = $p;
            }
        }

        $payments = DB::table('sourcing_request_payments')
            ->where('sourcing_request_id', $id)
            ->orderBy('sequence')->orderBy('id')
            ->get();

        $documents = DB::table('sourcing_request_documents')
            ->where('sourcing_request_id', $id)
            ->orderByDesc('id')
            ->get();

        $changeRequests = DB::table('sourcing_request_change_requests')
            ->leftJoin('users', 'users.id', '=', 'sourcing_request_change_requests.responded_by_user_id')
            ->where('sourcing_request_id', $id)
            ->orderByDesc('sourcing_request_change_requests.id')
            ->select('sourcing_request_change_requests.*', 'users.name as responded_by_name')
            ->get();

        $linkedPOs = $this->loadLinkedPOs((int) $id);

        // Phase 14: client negotiation patterns + deal health.
        $clientPatterns = $this->computeClientPatterns((int) $req->client_id);
        $dealHealth     = $this->computeDealHealth($req, $payments, $items, $changeRequests);

        // Phase 13: version history (newest first). Don't pull
        // snapshot_json for the list view — too heavy.
        $versions = DB::table('sourcing_request_versions as v')
            ->leftJoin('users as u', 'u.id', '=', 'v.created_by_user_id')
            ->where('v.sourcing_request_id', $id)
            ->orderByDesc('v.version_no')
            ->select(
                'v.id', 'v.version_no', 'v.trigger', 'v.label',
                'v.status_at_snapshot', 'v.total_at_snapshot',
                'v.currency_at_snapshot', 'v.item_count_at_snapshot',
                'v.created_at',
                'u.name as created_by_name'
            )
            ->get();

        // Activity timeline — every audit_log row tagged to this proforma
        // gives the operator a single chronological view of every action
        // (settings change, item add, send, approve, payment, fulfill).
        $timeline = DB::table('audit_log')
            ->leftJoin('users', 'users.id', '=', 'audit_log.user_id')
            ->where('audit_log.target_table', 'sourcing_requests')
            ->where('audit_log.target_id', $id)
            ->orWhere(function ($q) use ($id) {
                // Item / photo / payment audits target child tables — pull
                // them in by walking through the payloads.
                $q->whereIn('audit_log.target_table', [
                    'sourcing_request_items',
                    'sourcing_request_item_photos',
                    'sourcing_request_payments',
                  ])
                  ->where(function ($qq) use ($id) {
                      $qq->where('audit_log.payload', 'like', '%"sourcing_request_id":' . $id . '%')
                         ->orWhere('audit_log.payload', 'like', '%"sourcing_request_id": ' . $id . '%');
                  });
            })
            ->select('audit_log.*', 'users.name as user_name')
            ->orderByDesc('audit_log.created_at')
            ->limit(200)
            ->get();

        $lang = new langController();
        $dataController = new dataController();
        return view('pages.sourcing.show', [
            'req'             => $req,
            'client'          => $client,
            'branch'          => $branch,
            'quotes'          => $quotes,
            'journalEntry'    => $journalEntry,
            'items'           => $items,
            'photos'          => $photos,
            'payments'        => $payments,
            'documents'       => $documents,
            'changeRequests'  => $changeRequests,
            'linkedPOs'       => $linkedPOs,
            'versions'        => $versions,
            'clientPatterns'  => $clientPatterns,
            'dealHealth'      => $dealHealth,
            'timeline'        => $timeline,
            'data'            => $dataController,
            'lang'            => $lang,
            'section'         => 'sourcing',
            'page'            => 'sourcing',
        ]);
    }

    /* ------------------------------------------------------------
     *  Create a new sourcing request (POST /sourcing/create).
     * ------------------------------------------------------------ */
    public function create(Request $request)
    {
        $this->assertCanAccessClient($request->client_id);
        $this->assertPeriodOpen(date('Y-m-d'));

        $validated = $request->validate([
            'client_id'         => 'required|integer|exists:clients,id',
            'branch_id'         => 'nullable|integer|exists:branches,id',
            'title'             => 'required|string|max:255',
            'description'       => 'nullable|string',
            'currency'          => 'required|string|max:8',
            'target_quantity'   => 'nullable|numeric',
            'target_unit'       => 'nullable|string|max:32',
            'target_unit_price' => 'nullable|numeric',
        ]);

        try {
            $id = null;
            DB::transaction(function () use ($validated, &$id) {
                $nextNum = DB::table('sourcing_requests')->max('id') + 1;
                $requestNumber = 'SRC-' . date('Ymd') . '-' . str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);

                $id = DB::table('sourcing_requests')->insertGetId([
                    'request_number'    => $requestNumber,
                    'client_id'         => $validated['client_id'],
                    'branch_id'         => $validated['branch_id'] ?? null,
                    'title'             => $validated['title'],
                    'description'       => $validated['description'] ?? null,
                    'currency'          => $validated['currency'],
                    'target_quantity'   => $validated['target_quantity'] ?? null,
                    'target_unit'       => $validated['target_unit'] ?? null,
                    'target_unit_price' => $validated['target_unit_price'] ?? null,
                    'status'            => 'open',
                    'created_by'        => auth()->user()->id,
                    'created_at'        => date('Y-m-d H:i:s'),
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);

                $this->logAudit('sourcing_create', 'sourcing_requests', $id, [
                    'client_id' => $validated['client_id'],
                    'title'     => $validated['title'],
                ], 'Sourcing request created');
            });

            return response()->json(['type' => 'success', 'id' => $id]);
        } catch (\Throwable $e) {
            Log::error('sourcing create failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['type' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /* ------------------------------------------------------------
     *  Update editable fields (POST /sourcing/update).
     * ------------------------------------------------------------ */
    public function update(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $this->assertPeriodOpen(date('Y-m-d'));

        $id  = (int) $request->id;
        $row = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$row) abort(404);
        $this->assertCanAccessClient($row->client_id);

        // Once commission has posted, the only safe edits are description
        // and status (to fulfilled/canceled). Locking the financial fields
        // here keeps the journal posting consistent with what the user
        // sees on the request.
        $payload = [
            'description' => $request->get('description', $row->description),
            'title'       => $request->get('title', $row->title),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];
        if (!$row->commission_journal_entry_id) {
            $payload['currency']          = $request->get('currency', $row->currency);
            $payload['target_quantity']   = $request->get('target_quantity', $row->target_quantity);
            $payload['target_unit']       = $request->get('target_unit', $row->target_unit);
            $payload['target_unit_price'] = $request->get('target_unit_price', $row->target_unit_price);
        }

        $newStatus = $request->get('status');
        if ($newStatus && in_array($newStatus, self::STATUSES, true)) {
            $payload['status'] = $newStatus;
        }

        DB::table('sourcing_requests')->where('id', $id)->update($payload);

        $this->logAudit('sourcing_update', 'sourcing_requests', $id, $payload, 'Sourcing request updated');

        return response()->json(['type' => 'success']);
    }

    /* ------------------------------------------------------------
     *  Cancel a request (POST /sourcing/cancel).
     *  Reverses the commission journal entry if one exists.
     * ------------------------------------------------------------ */
    public function cancel(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $this->assertPeriodOpen(date('Y-m-d'));

        $id  = (int) $request->id;
        $row = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$row) abort(404);
        $this->assertCanAccessClient($row->client_id);

        if ($row->status === 'canceled') {
            return response()->json(['type' => 'noop']);
        }

        try {
            DB::transaction(function () use ($row, $id) {
                if ($row->commission_journal_entry_id) {
                    (new journalController())->reverse(
                        (int) $row->commission_journal_entry_id,
                        'Sourcing request canceled — commission reversed'
                    );
                }

                DB::table('sourcing_requests')->where('id', $id)->update([
                    'status'     => 'canceled',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $this->logAudit('sourcing_cancel', 'sourcing_requests', $id,
                    ['previous_status' => $row->status],
                    'Sourcing request canceled'
                );
            });

            return response()->json(['type' => 'success']);
        } catch (\Throwable $e) {
            Log::error('sourcing cancel failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['type' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /* ------------------------------------------------------------
     *  Add a quote to a sourcing request (POST /sourcing/quotes/add).
     * ------------------------------------------------------------ */
    public function addQuote(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $this->assertPeriodOpen(date('Y-m-d'));

        $validated = $request->validate([
            'sourcing_request_id'    => 'required|integer|exists:sourcing_requests,id',
            'supplier_id'            => 'nullable|integer|exists:suppliers,id',
            'supplier_name_freeform' => 'nullable|string|max:191',
            'unit_price'             => 'required|numeric',
            'quantity'               => 'nullable|numeric',
            'currency'               => 'required|string|max:8',
            'lead_time_days'         => 'nullable|integer',
            'notes'                  => 'nullable|string',
        ]);

        $row = DB::table('sourcing_requests')->where('id', $validated['sourcing_request_id'])->first();
        if (!$row) abort(404);
        $this->assertCanAccessClient($row->client_id);

        if (in_array($row->status, ['accepted', 'fulfilled', 'canceled'], true)) {
            return response()->json([
                'type'    => 'error',
                'message' => 'Cannot add quotes to a ' . $row->status . ' request',
            ], 422);
        }

        $qty   = $validated['quantity'] ?? null;
        $total = $qty !== null ? ($qty * $validated['unit_price']) : $validated['unit_price'];

        try {
            $quoteId = null;
            DB::transaction(function () use ($validated, $total, $row, &$quoteId) {
                $quoteId = DB::table('sourcing_request_quotes')->insertGetId([
                    'sourcing_request_id'    => $validated['sourcing_request_id'],
                    'supplier_id'            => $validated['supplier_id'] ?? null,
                    'supplier_name_freeform' => $validated['supplier_name_freeform'] ?? null,
                    'unit_price'             => $validated['unit_price'],
                    'quantity'               => $validated['quantity'] ?? null,
                    'total_price'            => $total,
                    'currency'               => $validated['currency'],
                    'lead_time_days'         => $validated['lead_time_days'] ?? null,
                    'notes'                  => $validated['notes'] ?? null,
                    'status'                 => 'proposed',
                    'created_by'             => auth()->user()->id,
                    'created_at'             => date('Y-m-d H:i:s'),
                    'updated_at'             => date('Y-m-d H:i:s'),
                ]);

                // Advance the request to "quoted" the first time we add a
                // quote — keeps the dashboard honest without forcing the
                // user to remember an extra status click.
                if (in_array($row->status, ['open', 'searching'], true)) {
                    DB::table('sourcing_requests')->where('id', $row->id)->update([
                        'status'     => 'quoted',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                $this->logAudit('sourcing_quote_add', 'sourcing_request_quotes', $quoteId, [
                    'sourcing_request_id' => $validated['sourcing_request_id'],
                    'unit_price'          => $validated['unit_price'],
                    'currency'            => $validated['currency'],
                ], 'Quote added');
            });

            return response()->json(['type' => 'success', 'id' => $quoteId]);
        } catch (\Throwable $e) {
            Log::error('sourcing add quote failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['type' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /* ------------------------------------------------------------
     *  Accept a quote and post commission (POST /sourcing/quotes/accept).
     *
     *  Idempotency: if commission_journal_entry_id is already set, refuse.
     *  Double-entry:
     *    Dr 1100 AR clients               (commission_amount)
     *    Cr 4020 Sourcing commission rev  (commission_amount)
     *  Cost-object tagging:
     *    source_table = sourcing_requests, source_id = $id
     *    counterparty = client
     * ------------------------------------------------------------ */
    public function acceptQuote(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $this->assertPeriodOpen(date('Y-m-d'));

        $validated = $request->validate([
            'quote_id'            => 'required|integer|exists:sourcing_request_quotes,id',
            'commission_amount'   => 'required|numeric|min:0.0001',
            'commission_currency' => 'required|string|max:8',
        ]);

        $quote = DB::table('sourcing_request_quotes')->where('id', $validated['quote_id'])->first();
        if (!$quote) abort(404);

        $req = DB::table('sourcing_requests')->where('id', $quote->sourcing_request_id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        if ($req->commission_journal_entry_id) {
            return response()->json([
                'type'    => 'error',
                'message' => 'Commission already posted for this request',
            ], 422);
        }
        if (in_array($req->status, ['canceled', 'fulfilled'], true)) {
            return response()->json([
                'type'    => 'error',
                'message' => 'Cannot accept a quote on a ' . $req->status . ' request',
            ], 422);
        }

        $amount   = (float) $validated['commission_amount'];
        $currency = $validated['commission_currency'];

        try {
            $entryId = null;
            DB::transaction(function () use ($req, $quote, $amount, $currency, &$entryId) {
                $entryId = (new journalController())->record([
                    'entry_date'   => date('Y-m-d'),
                    'kind'         => 'sourcing_commission',
                    'description'  => 'Sourcing commission ' . $amount . ' ' . strtoupper($currency)
                                       . ' — ' . $req->request_number,
                    'source_table' => 'sourcing_requests',
                    'source_id'    => $req->id,
                    'transaction_number' => $req->request_number,
                    'branch_id'    => $req->branch_id ? (int) $req->branch_id : null,
                    // Cost-object tag — sets the dimension on both lines so
                    // activityForCostObject('sourcing_request', $id) returns
                    // the full economics of this request.
                    'cost_object_type' => 'sourcing_request',
                    'cost_object_id'   => (int) $req->id,
                    'lines'        => [
                        ['account_code' => '1100', 'dr' => $amount, 'cr' => 0, 'currency' => $currency,
                         'counterparty_type' => 'client', 'counterparty_id' => (int) $req->client_id,
                         'branch_id' => $req->branch_id ? (int) $req->branch_id : null,
                         'description' => 'AR — sourcing commission ' . $req->request_number],
                        ['account_code' => '4020', 'dr' => 0, 'cr' => $amount, 'currency' => $currency,
                         'counterparty_type' => 'client', 'counterparty_id' => (int) $req->client_id,
                         'branch_id' => $req->branch_id ? (int) $req->branch_id : null,
                         'description' => 'Sourcing commission revenue ' . $req->request_number],
                    ],
                ]);

                DB::table('sourcing_request_quotes')->where('id', $quote->id)->update([
                    'status'     => 'accepted',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                DB::table('sourcing_request_quotes')
                    ->where('sourcing_request_id', $req->id)
                    ->where('id', '!=', $quote->id)
                    ->where('status', 'proposed')
                    ->update([
                        'status'     => 'rejected',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                DB::table('sourcing_requests')->where('id', $req->id)->update([
                    'status'                      => 'accepted',
                    'commission_amount'           => $amount,
                    'commission_currency'         => $currency,
                    'commission_posted_at'        => date('Y-m-d H:i:s'),
                    'commission_journal_entry_id' => $entryId,
                    'updated_at'                  => date('Y-m-d H:i:s'),
                ]);

                $this->logAudit('sourcing_quote_accept', 'sourcing_requests', $req->id, [
                    'quote_id'          => $quote->id,
                    'commission_amount' => $amount,
                    'currency'          => $currency,
                    'journal_entry_id'  => $entryId,
                ], 'Sourcing commission posted');
            });

            return response()->json(['type' => 'success', 'journal_entry_id' => $entryId]);
        } catch (\Throwable $e) {
            Log::error('sourcing accept quote failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['type' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /* ------------------------------------------------------------
     *  Mark fulfilled (POST /sourcing/fulfill). Final state.
     * ------------------------------------------------------------ */
    public function markFulfilled(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $this->assertPeriodOpen(date('Y-m-d'));

        $id  = (int) $request->id;
        $row = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$row) abort(404);
        $this->assertCanAccessClient($row->client_id);

        if ($row->status !== 'accepted') {
            return response()->json([
                'type'    => 'error',
                'message' => 'Only accepted requests can be marked fulfilled',
            ], 422);
        }

        $marginJournalId = null;
        DB::transaction(function () use ($row, $id, &$marginJournalId) {
            DB::table('sourcing_requests')->where('id', $id)->update([
                'status'     => 'fulfilled',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Phase 13: final snapshot — locks "this is what was delivered".
            $this->snapshotProforma((int) $id, 'fulfilled');

            // Revenue recognition — fulfillment is the moment we've earned
            // the sourcing margin. Compute the per-line margin (unit_price
            // minus supplier unit_cost), convert each line to the proforma's
            // display currency via the frozen FX snapshot, and post one
            // journal entry crediting 4020 Sourcing commission revenue.
            //
            // Counter-entry: Dr 2000 Client deposits — the client's wallet
            // gets drawn down by the margin (we've kept the margin; their
            // remaining balance is what they're still owed against the
            // order). This mirrors the existing commission-withdraw pattern.
            //
            // Cost-object tagged to sourcing_request:{id} so per-sourcing
            // P&L picks up both the payments (already tagged in phase 2)
            // and now the revenue.
            $marginJournalId = $this->postFulfillmentMarginEntry($row);
        });

        $this->logAudit('sourcing_fulfill', 'sourcing_requests', $id, [
            'margin_journal_id' => $marginJournalId,
        ], 'Sourcing request fulfilled');

        return response()->json([
            'type' => 'success',
            'margin_journal_id' => $marginJournalId,
        ]);
    }

    /**
     * Compute the proforma's margin (sum across items of
     * quantity * (unit_price_to_client - unit_cost)) in the display
     * currency using the frozen FX snapshot, and post:
     *
     *   Dr 2000 Client deposits  $margin
     *   Cr 4020 Sourcing commission revenue  $margin
     *
     * Returns the new journal_entries.id, or null when there's no
     * margin to recognize (zero/negative margin, no items).
     */
    private function postFulfillmentMarginEntry($req): ?int
    {
        $items = DB::table('sourcing_request_items')
            ->where('sourcing_request_id', $req->id)
            ->get();
        if ($items->isEmpty()) return null;

        $displayCcy = strtolower((string) ($req->display_currency ?: $req->currency ?: 'usd'));
        $rates = [];
        if (!empty($req->fx_rate_snapshot)) {
            $rates = json_decode($req->fx_rate_snapshot, true) ?: [];
        }
        if (empty($rates)) {
            $rates = (new dataController())->currency_exchange_rates;
        }

        $margin = 0.0;
        foreach ($items as $it) {
            $lineMarginRaw = (float) $it->quantity * ((float) $it->unit_price_to_client - (float) $it->unit_cost);
            $lineCcy = strtolower((string) ($it->unit_cost_currency ?: 'usd'));
            $usd = $this->toUsd($lineMarginRaw, $lineCcy, $rates);
            $margin += $this->fromUsd($usd, $displayCcy, $rates);
        }
        // When the proforma uses 'visible_separate' commission mode the
        // displayed per-unit prices are typically the supplier cost (no
        // markup baked in), so the per-line "margin" above is zero. The
        // real margin lives in the explicit commission_amount field. Add
        // it on top so both modes produce the same fulfillment revenue.
        if ($req->commission_mode === 'visible_separate' && (float) $req->commission_amount > 0) {
            $cAmt = (float) $req->commission_amount;
            $cCcy = strtolower((string) ($req->commission_currency ?: $displayCcy));
            $cUsd = $this->toUsd($cAmt, $cCcy, $rates);
            $margin += $this->fromUsd($cUsd, $displayCcy, $rates);
        }

        if ($margin < 0.0001) return null;

        $rounded = round($margin, 4);
        return (new journalController())->record([
            'entry_date'         => date('Y-m-d'),
            'kind'               => 'sourcing_fulfilled',
            'description'        => 'Sourcing margin recognised — ' . $req->request_number,
            'source_table'       => 'sourcing_requests',
            'source_id'          => $req->id,
            'transaction_number' => $req->request_number,
            'branch_id'          => $req->branch_id ? (int) $req->branch_id : null,
            'cost_object_type'   => 'sourcing_request',
            'cost_object_id'     => (int) $req->id,
            'lines'              => [
                ['account_code' => '2000', 'dr' => $rounded, 'cr' => 0, 'currency' => $displayCcy,
                 'counterparty_type' => 'client', 'counterparty_id' => (int) $req->client_id,
                 'description' => 'Margin drawn against client wallet — ' . $req->request_number],
                ['account_code' => '4020', 'dr' => 0, 'cr' => $rounded, 'currency' => $displayCcy,
                 'counterparty_type' => 'client', 'counterparty_id' => (int) $req->client_id,
                 'description' => 'Sourcing commission revenue — ' . $req->request_number],
            ],
        ]);
    }

    /* ------------------------------------------------------------
     *  Commissions report (GET /sourcing/commissions).
     *  Total sourcing commissions in [from, to] grouped by currency
     *  — answers "how much sourcing commission did we earn?".
     * ------------------------------------------------------------ */
    public function commissionsReport(Request $request)
    {
        $this->requireAdmin();
        $from = $request->get('from', date('Y-m-01'));
        $to   = $request->get('to',   date('Y-m-t'));

        $totals = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.entry_id')
            ->where('jl.account_code', '4020')
            ->where('je.status', 'open')
            ->whereBetween('je.entry_date', [$from, $to])
            ->groupBy('jl.currency')
            ->select('jl.currency', DB::raw('SUM(jl.cr - jl.dr) as total'))
            ->get();

        $rows = DB::table('sourcing_requests as sr')
            ->leftJoin('clients as c', 'c.id', '=', 'sr.client_id')
            ->whereNotNull('sr.commission_journal_entry_id')
            ->whereBetween('sr.commission_posted_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->select('sr.*', 'c.name as client_name', 'c.code as client_code')
            ->orderBy('sr.commission_posted_at', 'desc')
            ->get();

        $lang = new langController();
        return view('pages.sourcing.commissions', [
            'from'    => $from,
            'to'      => $to,
            'totals'  => $totals,
            'rows'    => $rows,
            'lang'    => $lang,
            'section' => 'sourcing',
            'page'    => 'sourcing_commissions',
        ]);
    }

    /* ============================================================
     *  PROFORMA — Phase 1
     *
     *  Line items + photo gallery + payment plan + proforma settings.
     *  Phase 2 (PDF, share link, on-behalf approval) builds on top.
     * ============================================================ */

    /** Centralised list of accepted payment-plan templates and their splits. */
    private const PAYMENT_PLAN_TEMPLATES = [
        '100'        => [['label' => '100% upfront',        'percentage' => 100]],
        '50_50'      => [
            ['label' => 'Deposit 50%',        'percentage' => 50],
            ['label' => 'Balance 50%',        'percentage' => 50],
        ],
        '30_50_20'   => [
            ['label' => 'Deposit 30%',        'percentage' => 30],
            ['label' => 'Pre-shipping 50%',   'percentage' => 50],
            ['label' => 'On delivery 20%',    'percentage' => 20],
        ],
        '30_30_40'   => [
            ['label' => 'Deposit 30%',        'percentage' => 30],
            ['label' => 'Mid-production 30%', 'percentage' => 30],
            ['label' => 'On delivery 40%',    'percentage' => 40],
        ],
    ];

    /* ------------------------------------------------------------
     *  POST /sourcing/items/add
     *  Add a line item to a proforma. Body: sourcing_request_id,
     *  name, code, description, quantity, unit, unit_cost,
     *  unit_cost_currency, unit_price_to_client, weight_kg, cbm.
     * ------------------------------------------------------------ */
    public function addItem(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $this->assertPeriodOpen(date('Y-m-d'));

        $v = $request->validate([
            'sourcing_request_id'    => 'required|integer|exists:sourcing_requests,id',
            'name'                   => 'required|string|max:191',
            'code'                   => 'nullable|string|max:64',
            'description'            => 'nullable|string',
            'quantity'               => 'required|numeric|min:0.0001',
            'unit'                   => 'nullable|string|max:32',
            'unit_cost'              => 'required|numeric|min:0',
            'unit_cost_currency'     => 'required|string|max:8',
            'unit_price_to_client'   => 'required|numeric|min:0',
            'weight_kg'              => 'nullable|numeric|min:0',
            'cbm'                    => 'nullable|numeric|min:0',
            'source_quote_id'        => 'nullable|integer|exists:sourcing_request_quotes,id',
            // Phase 11: optional catalog provenance for usage analytics.
            'catalog_id'             => 'nullable|integer|exists:product_catalog,id',
            'save_to_catalog'        => 'nullable|boolean',
        ]);

        $req = DB::table('sourcing_requests')->where('id', $v['sourcing_request_id'])->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        if (in_array($req->status, ['accepted', 'fulfilled', 'canceled'], true)) {
            return response()->json([
                'type' => 'error',
                'message' => 'Cannot edit items on a ' . $req->status . ' proforma',
            ], 422);
        }

        $itemId = null;
        DB::transaction(function () use ($v, $req, &$itemId) {
            $nextSort = (int) DB::table('sourcing_request_items')
                ->where('sourcing_request_id', $v['sourcing_request_id'])
                ->max('sort_order');

            $itemId = DB::table('sourcing_request_items')->insertGetId([
                'sourcing_request_id'   => $v['sourcing_request_id'],
                'name'                  => $v['name'],
                'code'                  => $v['code'] ?? null,
                'description'           => $v['description'] ?? null,
                'quantity'              => $v['quantity'],
                'unit'                  => $v['unit'] ?? 'pcs',
                'unit_cost'             => $v['unit_cost'],
                'unit_cost_currency'    => $v['unit_cost_currency'],
                'unit_price_to_client'  => $v['unit_price_to_client'],
                'weight_kg'             => $v['weight_kg'] ?? null,
                'cbm'                   => $v['cbm'] ?? null,
                'sort_order'            => $nextSort + 1,
                'source_quote_id'       => $v['source_quote_id'] ?? null,
                'created_by'            => auth()->user()->id,
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);

            $this->recomputeProformaTotals($v['sourcing_request_id']);

            // Catalog provenance bookkeeping.
            if (!empty($v['catalog_id'])) {
                DB::table('product_catalog')->where('id', $v['catalog_id'])->update([
                    'usage_count'  => DB::raw('usage_count + 1'),
                    'last_used_at' => date('Y-m-d H:i:s'),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ]);
            } elseif (!empty($v['save_to_catalog'])) {
                // Operator ticked "save to catalog" — mint a new entry so
                // they can pick this product directly next time.
                $newCatId = DB::table('product_catalog')->insertGetId([
                    'name'                       => $v['name'],
                    'code'                       => $v['code'] ?? null,
                    'description'                => $v['description'] ?? null,
                    'unit'                       => $v['unit'] ?? 'pcs',
                    'default_unit_cost'          => $v['unit_cost'],
                    'default_unit_cost_currency' => $v['unit_cost_currency'],
                    'default_unit_price'         => $v['unit_price_to_client'],
                    'default_weight_kg'          => $v['weight_kg'] ?? null,
                    'default_cbm'                => $v['cbm'] ?? null,
                    'is_active'                  => true,
                    'usage_count'                => 1,
                    'last_used_at'               => date('Y-m-d H:i:s'),
                    'created_by'                 => auth()->user()->id,
                    'created_at'                 => date('Y-m-d H:i:s'),
                    'updated_at'                 => date('Y-m-d H:i:s'),
                ]);
                $this->logAudit('catalog_create_from_item', 'product_catalog', $newCatId, [
                    'from_item_name' => $v['name'],
                ], 'Catalog item created from proforma');
            }

            $this->logAudit('sourcing_item_add', 'sourcing_request_items', $itemId, [
                'sourcing_request_id' => $v['sourcing_request_id'],
                'name'                => $v['name'],
                'quantity'            => $v['quantity'],
                'unit_price'          => $v['unit_price_to_client'],
                'catalog_id'          => $v['catalog_id'] ?? null,
            ], 'Proforma item added');
        });

        return response()->json(['type' => 'success', 'id' => $itemId]);
    }

    /* ------------------------------------------------------------
     *  POST /sourcing/items/update
     * ------------------------------------------------------------ */
    public function updateItem(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $this->assertPeriodOpen(date('Y-m-d'));

        $v = $request->validate([
            'id'                     => 'required|integer|exists:sourcing_request_items,id',
            'name'                   => 'required|string|max:191',
            'code'                   => 'nullable|string|max:64',
            'description'            => 'nullable|string',
            'quantity'               => 'required|numeric|min:0.0001',
            'unit'                   => 'nullable|string|max:32',
            'unit_cost'              => 'required|numeric|min:0',
            'unit_cost_currency'     => 'required|string|max:8',
            'unit_price_to_client'   => 'required|numeric|min:0',
            'weight_kg'              => 'nullable|numeric|min:0',
            'cbm'                    => 'nullable|numeric|min:0',
        ]);

        $item = DB::table('sourcing_request_items')->where('id', $v['id'])->first();
        if (!$item) abort(404);

        $req = DB::table('sourcing_requests')->where('id', $item->sourcing_request_id)->first();
        $this->assertCanAccessClient($req->client_id);

        if (in_array($req->status, ['accepted', 'fulfilled', 'canceled'], true)) {
            return response()->json([
                'type' => 'error',
                'message' => 'Cannot edit items on a ' . $req->status . ' proforma',
            ], 422);
        }

        DB::transaction(function () use ($v, $item) {
            DB::table('sourcing_request_items')->where('id', $v['id'])->update([
                'name'                  => $v['name'],
                'code'                  => $v['code'] ?? null,
                'description'           => $v['description'] ?? null,
                'quantity'              => $v['quantity'],
                'unit'                  => $v['unit'] ?? 'pcs',
                'unit_cost'             => $v['unit_cost'],
                'unit_cost_currency'    => $v['unit_cost_currency'],
                'unit_price_to_client'  => $v['unit_price_to_client'],
                'weight_kg'             => $v['weight_kg'] ?? null,
                'cbm'                   => $v['cbm'] ?? null,
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);
            $this->recomputeProformaTotals($item->sourcing_request_id);
            $this->logAudit('sourcing_item_update', 'sourcing_request_items', $v['id'], $v, 'Proforma item updated');
        });

        return response()->json(['type' => 'success']);
    }

    /* ------------------------------------------------------------
     *  POST /sourcing/items/delete
     * ------------------------------------------------------------ */
    public function deleteItem(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $this->assertPeriodOpen(date('Y-m-d'));

        $id   = (int) $request->id;
        $item = DB::table('sourcing_request_items')->where('id', $id)->first();
        if (!$item) abort(404);

        $req = DB::table('sourcing_requests')->where('id', $item->sourcing_request_id)->first();
        $this->assertCanAccessClient($req->client_id);

        if (in_array($req->status, ['accepted', 'fulfilled', 'canceled'], true)) {
            return response()->json([
                'type' => 'error',
                'message' => 'Cannot edit items on a ' . $req->status . ' proforma',
            ], 422);
        }

        DB::transaction(function () use ($item, $id) {
            // Photos for this item — delete files from disk + rows from db.
            $photos = DB::table('sourcing_request_item_photos')->where('item_id', $id)->get();
            foreach ($photos as $p) {
                $abs = storage_path('app/public/' . ltrim($p->path, '/'));
                if (is_file($abs)) @unlink($abs);
            }
            DB::table('sourcing_request_item_photos')->where('item_id', $id)->delete();
            DB::table('sourcing_request_items')->where('id', $id)->delete();
            $this->recomputeProformaTotals($item->sourcing_request_id);
            $this->logAudit('sourcing_item_delete', 'sourcing_request_items', $id, [
                'sourcing_request_id' => $item->sourcing_request_id,
            ], 'Proforma item deleted');
        });

        return response()->json(['type' => 'success']);
    }

    /* ------------------------------------------------------------
     *  POST /sourcing/items/photos/upload
     *  Multipart form: item_id + photos[] (1..N images).
     *  Saves under storage/app/public/proforma/<request>/<item>/...
     * ------------------------------------------------------------ */
    public function uploadItemPhotos(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $request->validate([
            'item_id'  => 'required|integer|exists:sourcing_request_items,id',
            'photos'   => 'required|array|min:1|max:10',
            'photos.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120', // 5 MB each
        ]);

        $item = DB::table('sourcing_request_items')->where('id', $request->item_id)->first();
        $req  = DB::table('sourcing_requests')->where('id', $item->sourcing_request_id)->first();
        $this->assertCanAccessClient($req->client_id);

        if (in_array($req->status, ['canceled'], true)) {
            return response()->json(['type' => 'error', 'message' => 'Canceled — cannot upload'], 422);
        }

        $uploaded = [];
        $hadPrimaryBefore = (bool) DB::table('sourcing_request_item_photos')
            ->where('item_id', $item->id)->where('is_primary', true)->exists();

        DB::transaction(function () use ($request, $item, $req, &$uploaded, $hadPrimaryBefore) {
            $maxSort = (int) DB::table('sourcing_request_item_photos')
                ->where('item_id', $item->id)->max('sort_order');

            $i = 0;
            foreach ($request->file('photos') as $file) {
                $i++;
                $ext  = strtolower($file->getClientOriginalExtension() ?: 'jpg');
                $name = bin2hex(random_bytes(8)) . '.' . $ext;
                $dir  = 'proforma/' . $req->id . '/' . $item->id;
                $rel  = $dir . '/' . $name;
                $file->storeAs('public/' . $dir, $name);

                $isPrimary = !$hadPrimaryBefore && $i === 1;

                $photoId = DB::table('sourcing_request_item_photos')->insertGetId([
                    'item_id'       => $item->id,
                    'path'          => $rel,
                    'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 191),
                    'size_bytes'    => $file->getSize(),
                    'mime'          => $file->getClientMimeType(),
                    'is_primary'    => $isPrimary,
                    'sort_order'    => $maxSort + $i,
                    'uploaded_by'   => auth()->user()->id,
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
                $uploaded[] = ['id' => $photoId, 'path' => $rel, 'is_primary' => $isPrimary];
            }

            $this->logAudit('sourcing_item_photos_upload', 'sourcing_request_item_photos', null, [
                'item_id' => $item->id,
                'count'   => count($uploaded),
            ], 'Proforma item photos uploaded');
        });

        return response()->json(['type' => 'success', 'uploaded' => $uploaded]);
    }

    /* ------------------------------------------------------------
     *  POST /sourcing/items/photos/delete
     * ------------------------------------------------------------ */
    public function deleteItemPhoto(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $id = (int) $request->id;
        $photo = DB::table('sourcing_request_item_photos')->where('id', $id)->first();
        if (!$photo) abort(404);

        $item = DB::table('sourcing_request_items')->where('id', $photo->item_id)->first();
        $req  = DB::table('sourcing_requests')->where('id', $item->sourcing_request_id)->first();
        $this->assertCanAccessClient($req->client_id);

        $abs = storage_path('app/public/' . ltrim($photo->path, '/'));
        if (is_file($abs)) @unlink($abs);
        DB::table('sourcing_request_item_photos')->where('id', $id)->delete();

        // If we deleted the primary, promote the next photo (by sort order)
        // to primary so item thumbnails don't suddenly go blank.
        if ($photo->is_primary) {
            $next = DB::table('sourcing_request_item_photos')
                ->where('item_id', $photo->item_id)
                ->orderBy('sort_order')->orderBy('id')->first();
            if ($next) {
                DB::table('sourcing_request_item_photos')
                    ->where('id', $next->id)
                    ->update(['is_primary' => true, 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }

        return response()->json(['type' => 'success']);
    }

    /* ------------------------------------------------------------
     *  POST /sourcing/items/photos/set-primary
     * ------------------------------------------------------------ */
    public function setPrimaryPhoto(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $id = (int) $request->id;
        $photo = DB::table('sourcing_request_item_photos')->where('id', $id)->first();
        if (!$photo) abort(404);

        $item = DB::table('sourcing_request_items')->where('id', $photo->item_id)->first();
        $req  = DB::table('sourcing_requests')->where('id', $item->sourcing_request_id)->first();
        $this->assertCanAccessClient($req->client_id);

        DB::transaction(function () use ($photo) {
            DB::table('sourcing_request_item_photos')
                ->where('item_id', $photo->item_id)
                ->update(['is_primary' => false, 'updated_at' => date('Y-m-d H:i:s')]);
            DB::table('sourcing_request_item_photos')
                ->where('id', $photo->id)
                ->update(['is_primary' => true, 'updated_at' => date('Y-m-d H:i:s')]);
        });

        return response()->json(['type' => 'success']);
    }

    /* ------------------------------------------------------------
     *  POST /sourcing/proforma/settings
     *  Update display_currency, commission_mode, terms_text, and
     *  freeze fx_rate_snapshot when display_currency changes.
     * ------------------------------------------------------------ */
    public function updateProformaSettings(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $v = $request->validate([
            'id'               => 'required|integer|exists:sourcing_requests,id',
            'display_currency' => 'nullable|string|max:8',
            'commission_mode'  => 'nullable|in:visible_separate,hidden_in_prices',
            'commission_amount'=> 'nullable|numeric|min:0',
            'commission_currency' => 'nullable|string|max:8',
            'terms_text'       => 'nullable|string',
        ]);

        $req = DB::table('sourcing_requests')->where('id', $v['id'])->first();
        $this->assertCanAccessClient($req->client_id);

        if (in_array($req->status, ['accepted', 'fulfilled', 'canceled'], true)) {
            return response()->json([
                'type' => 'error',
                'message' => 'Cannot edit a ' . $req->status . ' proforma',
            ], 422);
        }

        $update = ['updated_at' => date('Y-m-d H:i:s')];
        if (array_key_exists('display_currency', $v) && $v['display_currency']) {
            $update['display_currency'] = $v['display_currency'];
            // Freeze the current FX table the moment the operator picks a
            // display currency — so a rate change tomorrow doesn't restate
            // an already-shown proforma.
            $dc = new dataController();
            $update['fx_rate_snapshot'] = json_encode($dc->currency_exchange_rates);
            $update['fx_frozen_on']     = date('Y-m-d');
        }
        if (array_key_exists('commission_mode', $v))    $update['commission_mode']    = $v['commission_mode'];
        if (array_key_exists('commission_amount', $v))  $update['commission_amount']  = $v['commission_amount'];
        if (array_key_exists('commission_currency', $v))$update['commission_currency']= $v['commission_currency'];
        if (array_key_exists('terms_text', $v))         $update['terms_text']         = $v['terms_text'];

        DB::transaction(function () use ($v, $update) {
            DB::table('sourcing_requests')->where('id', $v['id'])->update($update);
            $this->recomputeProformaTotals($v['id']);
            $this->logAudit('sourcing_proforma_settings', 'sourcing_requests', $v['id'], $update, 'Proforma settings updated');
        });

        return response()->json(['type' => 'success']);
    }

    /* ------------------------------------------------------------
     *  POST /sourcing/proforma/payment-plan
     *  Generate the payment schedule from a plan template. Wipes any
     *  existing scheduled rows that haven't been paid yet; paid rows
     *  are preserved (deleting them would orphan the linked client
     *  transactions).
     * ------------------------------------------------------------ */
    public function generatePaymentPlan(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $v = $request->validate([
            'id'   => 'required|integer|exists:sourcing_requests,id',
            'plan' => 'required|in:100,50_50,30_50_20,30_30_40,custom',
        ]);

        $req = DB::table('sourcing_requests')->where('id', $v['id'])->first();
        $this->assertCanAccessClient($req->client_id);

        // For 'custom' we just flip the marker — the UI lets the operator
        // add rows manually with addPayment / updatePayment.
        if ($v['plan'] === 'custom') {
            DB::table('sourcing_requests')->where('id', $v['id'])->update([
                'payment_plan' => 'custom',
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
            return response()->json(['type' => 'success']);
        }

        $total    = (float) ($req->proforma_total ?: 0);
        $currency = $req->display_currency ?: $req->currency ?: 'usd';
        $template = self::PAYMENT_PLAN_TEMPLATES[$v['plan']];

        DB::transaction(function () use ($req, $v, $template, $total, $currency) {
            // Wipe scheduled rows but preserve any paid history.
            DB::table('sourcing_request_payments')
                ->where('sourcing_request_id', $req->id)
                ->where('status', 'scheduled')
                ->delete();

            // Find the next sequence number (after any preserved paid rows).
            $startSeq = (int) DB::table('sourcing_request_payments')
                ->where('sourcing_request_id', $req->id)
                ->max('sequence');

            foreach ($template as $i => $row) {
                $amount = round($total * ($row['percentage'] / 100), 4);
                DB::table('sourcing_request_payments')->insert([
                    'sourcing_request_id' => $req->id,
                    'sequence'            => $startSeq + $i + 1,
                    'label'               => $row['label'],
                    'percentage'          => $row['percentage'],
                    'amount'              => $amount,
                    'currency'            => $currency,
                    'status'              => 'scheduled',
                    'created_at'          => date('Y-m-d H:i:s'),
                    'updated_at'          => date('Y-m-d H:i:s'),
                ]);
            }

            DB::table('sourcing_requests')->where('id', $req->id)->update([
                'payment_plan' => $v['plan'],
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

            // Phase 13: snapshot so before/after schedule diffs are
            // visible — useful when a client says "this isn't what I
            // agreed to".
            $this->snapshotProforma((int) $req->id, 'plan_regenerated', $v['plan']);

            $this->logAudit('sourcing_payment_plan', 'sourcing_requests', $req->id, [
                'plan' => $v['plan'], 'total' => $total, 'currency' => $currency,
            ], 'Payment plan generated');
        });

        return response()->json(['type' => 'success']);
    }

    /* ------------------------------------------------------------
     *  POST /sourcing/proforma/payments/update
     *  Edit a scheduled payment row (label, amount, due date).
     *  Paid rows are locked.
     * ------------------------------------------------------------ */
    public function updatePayment(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $v = $request->validate([
            'id'         => 'required|integer|exists:sourcing_request_payments,id',
            'label'      => 'required|string|max:191',
            'amount'     => 'required|numeric|min:0',
            'currency'   => 'required|string|max:8',
            'due_date'   => 'nullable|date',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'notes'      => 'nullable|string|max:500',
        ]);

        $row = DB::table('sourcing_request_payments')->where('id', $v['id'])->first();
        if (!$row) abort(404);

        $req = DB::table('sourcing_requests')->where('id', $row->sourcing_request_id)->first();
        $this->assertCanAccessClient($req->client_id);

        if (in_array($row->status, ['paid', 'canceled'], true)) {
            return response()->json([
                'type' => 'error',
                'message' => $row->status === 'paid'
                    ? 'Paid installments cannot be edited — record a new adjustment instead'
                    : 'Canceled installment',
            ], 422);
        }

        DB::table('sourcing_request_payments')->where('id', $v['id'])->update([
            'label'      => $v['label'],
            'amount'     => $v['amount'],
            'currency'   => $v['currency'],
            'due_date'   => $v['due_date'] ?? null,
            'percentage' => $v['percentage'] ?? $row->percentage,
            'notes'      => $v['notes'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return response()->json(['type' => 'success']);
    }

    /* ------------------------------------------------------------
     *  POST /sourcing/proforma/payments/add
     *  Add an extra installment to a custom plan.
     * ------------------------------------------------------------ */
    public function addPayment(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $v = $request->validate([
            'sourcing_request_id' => 'required|integer|exists:sourcing_requests,id',
            'label'               => 'required|string|max:191',
            'amount'              => 'required|numeric|min:0',
            'currency'            => 'required|string|max:8',
            'due_date'            => 'nullable|date',
            'percentage'          => 'nullable|numeric|min:0|max:100',
        ]);

        $req = DB::table('sourcing_requests')->where('id', $v['sourcing_request_id'])->first();
        $this->assertCanAccessClient($req->client_id);

        $nextSeq = ((int) DB::table('sourcing_request_payments')
            ->where('sourcing_request_id', $v['sourcing_request_id'])
            ->max('sequence')) + 1;

        $id = DB::table('sourcing_request_payments')->insertGetId([
            'sourcing_request_id' => $v['sourcing_request_id'],
            'sequence'            => $nextSeq,
            'label'               => $v['label'],
            'percentage'          => $v['percentage'] ?? 0,
            'amount'              => $v['amount'],
            'currency'            => $v['currency'],
            'due_date'            => $v['due_date'] ?? null,
            'status'              => 'scheduled',
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);
        // A manually added installment implies a custom plan.
        DB::table('sourcing_requests')->where('id', $v['sourcing_request_id'])->update([
            'payment_plan' => 'custom',
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        return response()->json(['type' => 'success', 'id' => $id]);
    }

    /* ------------------------------------------------------------
     *  POST /sourcing/proforma/payments/delete
     *  Delete a scheduled installment. Paid rows are protected.
     * ------------------------------------------------------------ */
    public function deletePayment(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $id  = (int) $request->id;
        $row = DB::table('sourcing_request_payments')->where('id', $id)->first();
        if (!$row) abort(404);
        if ($row->status !== 'scheduled') {
            return response()->json(['type' => 'error', 'message' => 'Only scheduled rows can be deleted'], 422);
        }
        $req = DB::table('sourcing_requests')->where('id', $row->sourcing_request_id)->first();
        $this->assertCanAccessClient($req->client_id);

        DB::table('sourcing_request_payments')->where('id', $id)->delete();
        return response()->json(['type' => 'success']);
    }

    /**
     * Recompute items_subtotal + proforma_total on the parent request and
     * persist them so list views / reports don't have to re-sum.
     *   items_subtotal  = SUM(quantity * unit_price_to_client)
     *                     all converted to display_currency at frozen FX
     *   proforma_total  = items_subtotal + commission_amount (when
     *                     commission_mode='visible_separate'; otherwise
     *                     commission is already baked into prices)
     */
    private function recomputeProformaTotals(int $requestId): void
    {
        $req = DB::table('sourcing_requests')->where('id', $requestId)->first();
        if (!$req) return;

        $displayCcy = strtolower((string) ($req->display_currency ?: $req->currency ?: 'usd'));
        $rates = [];
        if (!empty($req->fx_rate_snapshot)) {
            $rates = json_decode($req->fx_rate_snapshot, true) ?: [];
        }
        if (empty($rates)) {
            // Fall back to the live table if no snapshot has been frozen.
            $rates = (new dataController())->currency_exchange_rates;
        }

        $items = DB::table('sourcing_request_items')
            ->where('sourcing_request_id', $requestId)->get();

        $subtotal = 0.0;
        foreach ($items as $it) {
            $lineCcy = strtolower((string) ($it->unit_cost_currency ?: 'usd'));
            $lineTotal = (float) $it->quantity * (float) $it->unit_price_to_client;
            // Convert line to display currency via USD as base.
            $usd = $this->toUsd($lineTotal, $lineCcy, $rates);
            $inDisplay = $this->fromUsd($usd, $displayCcy, $rates);
            $subtotal += $inDisplay;
        }

        $total = $subtotal;
        if ($req->commission_mode === 'visible_separate') {
            $cAmt = (float) ($req->commission_amount ?: 0);
            $cCcy = strtolower((string) ($req->commission_currency ?: $displayCcy));
            $cUsd = $this->toUsd($cAmt, $cCcy, $rates);
            $cInDisplay = $this->fromUsd($cUsd, $displayCcy, $rates);
            $total += $cInDisplay;
        }

        DB::table('sourcing_requests')->where('id', $requestId)->update([
            'items_subtotal' => round($subtotal, 4),
            'proforma_total' => round($total, 4),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /* ------------------------------------------------------------
     *  Client account portal — Phase 9
     *
     *  /portal/{client_token}  (PUBLIC, no login)
     *
     *  Shows every proforma we have for that client with quick links.
     *  Token is per-client, long-lived; the admin can mint or rotate it
     *  via POST /sourcing/clients/{id}/portal-token.
     * ------------------------------------------------------------ */
    public function publicClientPortal($token)
    {
        $client = DB::table('clients')->where('portal_token', $token)
            ->where('deleted', 'false')->first();
        if (!$client) abort(404, 'Portal link not found');

        // Fetch every non-trashed proforma for this client, newest first.
        $rows = DB::table('sourcing_requests')
            ->where('client_id', $client->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->select(
                'id', 'request_number', 'title', 'status', 'display_currency', 'currency',
                'proforma_total', 'sent_at', 'approved_at', 'share_token', 'share_token_expires_at',
                'created_at'
            )
            ->get();

        $settings = (new settingsController())->get();
        return view('pages.sourcing.public_portal', [
            'client'   => $client,
            'rows'     => $rows,
            'settings' => $settings,
        ]);
    }

    /**
     * Mint / rotate the portal token for a client. Returns the public URL.
     */
    public function mintClientPortalToken(Request $request, $clientId)
    {
        $this->requireAdminOrBranchAdmin();
        $client = DB::table('clients')->where('id', $clientId)->first();
        if (!$client || $client->deleted !== 'false') abort(404);
        $this->assertCanAccessClient($clientId);

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        DB::table('clients')->where('id', $clientId)->update([
            'portal_token'           => $token,
            'portal_token_issued_at' => date('Y-m-d H:i:s'),
        ]);

        $this->logAudit('client_portal_token', 'clients', $clientId, [
            'first8' => substr($token, 0, 8) . '…',
        ], 'Client portal token (re)issued');

        return response()->json([
            'type'       => 'success',
            'token'      => $token,
            'public_url' => url('/portal/' . $token),
        ]);
    }

    /* ------------------------------------------------------------
     *  Cost-plus markup calculator — Phase 10
     *
     *  POST /sourcing/{id}/apply-markup
     *     body: markup_percent (e.g. 25 for 25%)
     *
     *  Recomputes every item's unit_price_to_client as:
     *     unit_cost * (1 + markup_percent / 100)
     *
     *  Useful when the operator wants to set a flat margin across all
     *  items instead of pricing each one by hand. Locked once the
     *  proforma is accepted/fulfilled/canceled. Recomputes totals.
     * ------------------------------------------------------------ */
    public function applyMarkup(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $this->assertPeriodOpen(date('Y-m-d'));

        $v = $request->validate([
            'markup_percent' => 'required|numeric|min:-99|max:1000',
        ]);

        $req = DB::table('sourcing_requests')->where('id', $id)
            ->whereNull('deleted_at')->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        if (in_array($req->status, ['accepted', 'fulfilled', 'canceled'], true)) {
            return response()->json([
                'type' => 'error',
                'message' => 'Cannot apply markup to a ' . $req->status . ' proforma',
            ], 422);
        }

        $multiplier = 1 + ((float) $v['markup_percent'] / 100);
        $items = DB::table('sourcing_request_items')->where('sourcing_request_id', $id)->get();

        $updated = 0;
        DB::transaction(function () use ($items, $multiplier, &$updated, $id, $v) {
            // Snapshot BEFORE the change so the operator can compare
            // pre-markup vs post-markup pricing.
            $this->snapshotProforma((int) $id, 'markup_applied', 'Before ' . $v['markup_percent'] . '% markup');

            foreach ($items as $it) {
                $newPrice = round((float) $it->unit_cost * $multiplier, 4);
                DB::table('sourcing_request_items')->where('id', $it->id)->update([
                    'unit_price_to_client' => $newPrice,
                    'updated_at'           => date('Y-m-d H:i:s'),
                ]);
                $updated++;
            }
            $this->recomputeProformaTotals($id);
            $this->logAudit('sourcing_apply_markup', 'sourcing_requests', $id, [
                'markup_percent' => $v['markup_percent'],
                'items_updated'  => $updated,
            ], 'Applied ' . $v['markup_percent'] . '% markup to ' . $updated . ' item(s)');
        });

        return response()->json([
            'type'    => 'success',
            'updated' => $updated,
        ]);
    }

    /* ------------------------------------------------------------
     *  Bulk operations — Phase 10
     *
     *  POST /sourcing/bulk/trash    { ids: [N, N, ...] }
     *  POST /sourcing/bulk/restore  { ids: [N, N, ...] }
     *
     *  Validates per-row access (branch admins can only touch their
     *  branch's rows). Returns counts so the UI can show "trashed N of M".
     * ------------------------------------------------------------ */
    public function bulkTrash(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $v = $request->validate([
            'ids'   => 'required|array|min:1|max:100',
            'ids.*' => 'integer',
        ]);

        $user = auth()->user();
        $rows = DB::table('sourcing_requests')->whereIn('id', $v['ids'])
            ->whereNull('deleted_at')->get();
        if ($user->type !== 'admin') {
            $rows = $rows->filter(fn($r) => (int) $r->branch_id === (int) $user->branch)->values();
        }

        $trashedAt = date('Y-m-d H:i:s');
        $count = 0;
        DB::transaction(function () use ($rows, $trashedAt, &$count) {
            foreach ($rows as $r) {
                DB::table('sourcing_requests')->where('id', $r->id)->update([
                    'deleted_at' => $trashedAt,
                    'updated_at' => $trashedAt,
                ]);
                $count++;
            }
            $this->logAudit('sourcing_bulk_trash', 'sourcing_requests', null, [
                'ids'    => $rows->pluck('id')->all(),
                'count'  => $count,
            ], 'Bulk-trashed ' . $count . ' proformas');
        });

        return response()->json(['type' => 'success', 'count' => $count]);
    }

    public function bulkRestore(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $v = $request->validate([
            'ids'   => 'required|array|min:1|max:100',
            'ids.*' => 'integer',
        ]);

        $user = auth()->user();
        $rows = DB::table('sourcing_requests')->whereIn('id', $v['ids'])
            ->whereNotNull('deleted_at')->get();
        if ($user->type !== 'admin') {
            $rows = $rows->filter(fn($r) => (int) $r->branch_id === (int) $user->branch)->values();
        }

        $count = 0;
        DB::transaction(function () use ($rows, &$count) {
            foreach ($rows as $r) {
                DB::table('sourcing_requests')->where('id', $r->id)->update([
                    'deleted_at' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $count++;
            }
            $this->logAudit('sourcing_bulk_restore', 'sourcing_requests', null, [
                'ids'    => $rows->pluck('id')->all(),
                'count'  => $count,
            ], 'Bulk-restored ' . $count . ' proformas');
        });

        return response()->json(['type' => 'success', 'count' => $count]);
    }

    /* ============================================================
     *  SMART INSIGHTS — Phase 14
     *
     *  Supplier reliability, client negotiation patterns, deal health.
     *  All read-only — these are derived metrics, no schema changes.
     * ============================================================ */

    /**
     * Aggregate supplier reliability metrics from the linked-PO chain.
     * Computes per supplier_name:
     *   - total_pos:        every PO ever
     *   - delivered_pos:    those with status='DELIVERED'
     *   - cancelled_pos:    CANCELLED + RETURNED + REFUNDED
     *   - avg_lead_days:    avg(delivered_at - purchasing_started_at) in days
     *   - on_time_rate:     % of items linked to this supplier's POs
     *                       where supplier_confirmed_date <= promised_delivery_date
     *
     * Returns rows sorted by total_pos DESC. Caller decides display.
     */
    public function supplierInsights(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $rows = DB::table('purchase_orders')
            ->whereNotNull('supplier_name')->where('supplier_name', '!=', '')
            ->select(
                'supplier_name',
                DB::raw('COUNT(*) as total_pos'),
                DB::raw("SUM(CASE WHEN status='DELIVERED' THEN 1 ELSE 0 END) as delivered_pos"),
                DB::raw("SUM(CASE WHEN status IN ('CANCELLED','RETURNED','REFUNDED') THEN 1 ELSE 0 END) as cancelled_pos"),
                DB::raw("AVG(CASE WHEN purchasing_started_at IS NOT NULL AND delivered_at IS NOT NULL
                                  THEN TIMESTAMPDIFF(DAY, purchasing_started_at, delivered_at) END) as avg_lead_days")
            )
            ->groupBy('supplier_name')
            ->orderByDesc('total_pos')
            ->limit(50)
            ->get();

        // On-time rate via item-level dates on proformas linked to each
        // supplier's POs. One query that joins through the link table.
        $onTimeBySupplier = DB::table('sourcing_request_items as i')
            ->join('sourcing_request_purchase_orders as l', 'l.sourcing_request_id', '=', 'i.sourcing_request_id')
            ->join('purchase_orders as po', 'po.id', '=', 'l.purchase_order_id')
            ->whereNotNull('i.promised_delivery_date')
            ->whereNotNull('i.supplier_confirmed_date')
            ->whereNotNull('po.supplier_name')
            ->select(
                'po.supplier_name',
                DB::raw('COUNT(*) as total_items'),
                DB::raw('SUM(CASE WHEN i.supplier_confirmed_date <= i.promised_delivery_date THEN 1 ELSE 0 END) as on_time_items')
            )
            ->groupBy('po.supplier_name')
            ->get()->keyBy('supplier_name');

        $enriched = $rows->map(function ($r) use ($onTimeBySupplier) {
            $ot = $onTimeBySupplier->get($r->supplier_name);
            $r->on_time_items = $ot ? (int) $ot->on_time_items : 0;
            $r->on_time_total = $ot ? (int) $ot->total_items : 0;
            $r->on_time_rate  = $r->on_time_total > 0
                ? round(100 * $r->on_time_items / $r->on_time_total, 1)
                : null;
            $r->cancel_rate   = $r->total_pos > 0
                ? round(100 * $r->cancelled_pos / $r->total_pos, 1)
                : 0;
            return $r;
        });

        $lang = new langController();
        return view('pages.sourcing.insights_suppliers', [
            'rows'    => $enriched,
            'lang'    => $lang,
            'section' => 'sourcing',
            'page'    => 'sourcing_insights',
        ]);
    }

    /**
     * Per-client negotiation pattern summary. Looks at the client's
     * change-request history and classifies what they typically push
     * back on. Useful so the operator can anticipate.
     *
     * Returns: { total_proformas, total_change_requests, qty_changes_pct,
     *           price_changes_pct, terms_changes_pct, avg_response_hours,
     *           most_common_action }
     */
    private function computeClientPatterns(int $clientId): array
    {
        $proformaIds = DB::table('sourcing_requests')
            ->where('client_id', $clientId)->whereNull('deleted_at')
            ->pluck('id');
        if ($proformaIds->isEmpty()) {
            return [
                'total_proformas' => 0, 'total_change_requests' => 0,
                'qty_changes_pct' => 0, 'price_changes_pct' => 0, 'terms_changes_pct' => 0,
                'avg_response_hours' => null, 'most_common_action' => null,
            ];
        }

        $crs = DB::table('sourcing_request_change_requests')
            ->whereIn('sourcing_request_id', $proformaIds)
            ->get();

        $qtyChanges = 0; $priceChanges = 0; $termsChanges = 0;
        foreach ($crs as $cr) {
            $sg = $cr->suggested_changes ? (json_decode($cr->suggested_changes, true) ?: []) : [];
            $sawQty = false; $sawPrice = false;
            foreach ($sg as $row) {
                if (isset($row['qty']))                  $sawQty   = true;
                if (isset($row['unit_price_to_client'])) $sawPrice = true;
            }
            if ($sawQty)   $qtyChanges++;
            if ($sawPrice) $priceChanges++;
            // Free-form comment with payment/terms cues — quick heuristic
            // because the comment text is the only structured signal we
            // have for things outside qty/price.
            $comment = strtolower((string) $cr->comment);
            if (preg_match('/payment|terms|deposit|installment|delivery|deadline|date/', $comment)) {
                $termsChanges++;
            }
        }

        $total = $crs->count();
        $resolved = $crs->whereIn('status', ['responded', 'dismissed'])->whereNotNull('responded_at')->whereNotNull('created_at');
        $avgHours = null;
        if ($resolved->count() > 0) {
            $sumHours = 0;
            foreach ($resolved as $r) {
                $sumHours += (strtotime($r->responded_at) - strtotime($r->created_at)) / 3600;
            }
            $avgHours = round($sumHours / $resolved->count(), 1);
        }

        // Pick the most-leaned-on lever.
        $tally = [
            'quantity'      => $qtyChanges,
            'price'         => $priceChanges,
            'terms/timing'  => $termsChanges,
        ];
        arsort($tally);
        $mostCommon = $total > 0 ? array_key_first(array_filter($tally)) : null;

        return [
            'total_proformas'       => $proformaIds->count(),
            'total_change_requests' => $total,
            'qty_changes_pct'       => $total > 0 ? round(100 * $qtyChanges   / $total) : 0,
            'price_changes_pct'     => $total > 0 ? round(100 * $priceChanges / $total) : 0,
            'terms_changes_pct'     => $total > 0 ? round(100 * $termsChanges / $total) : 0,
            'avg_response_hours'    => $avgHours,
            'most_common_action'    => $mostCommon,
        ];
    }

    /**
     * Composite 0-100 deal health score. Each signal contributes a
     * portion; missing data points pull toward "unknown" rather than
     * penalizing. Returns:
     *   { score, factors: [{label, points, note}] }
     *
     * Heuristic — tuned to be a useful traffic-light, not a contract.
     * Scoring rubric (max 100):
     *   - Status currency      (max 20) — sent recently / approved bonus
     *   - Client engagement    (max 25) — they viewed it
     *   - Negotiation hygiene  (max 15) — change requests resolved
     *   - Payment cadence      (max 20) — installments paid on schedule
     *   - Delivery health      (max 20) — items on track
     */
    private function computeDealHealth($req, $payments, $items, $changeRequests): array
    {
        $factors = [];
        $score = 0;
        $today = date('Y-m-d');

        // (1) Status currency
        if ($req->status === 'fulfilled') {
            $score += 20; $factors[] = ['label' => 'Fulfilled', 'points' => 20, 'note' => 'Closed deal'];
        } elseif ($req->status === 'accepted') {
            $score += 18; $factors[] = ['label' => 'Accepted', 'points' => 18, 'note' => 'Client agreed'];
        } elseif ($req->status === 'quoted' && $req->sent_at) {
            $daysSent = (time() - strtotime($req->sent_at)) / 86400;
            $bucket = $daysSent <= 3 ? 14 : ($daysSent <= 7 ? 10 : ($daysSent <= 14 ? 5 : 0));
            $score += $bucket;
            $factors[] = ['label' => 'Recently sent', 'points' => $bucket, 'note' => round($daysSent) . ' day(s) since sent'];
        } elseif ($req->status === 'canceled') {
            $factors[] = ['label' => 'Canceled', 'points' => 0, 'note' => 'Deal dropped'];
        } else {
            $factors[] = ['label' => 'Pre-send draft', 'points' => 5, 'note' => 'Not yet sent'];
            $score += 5;
        }

        // (2) Client engagement — view count + approval path
        if ($req->client_view_count > 0) {
            $vBucket = min(25, 8 + 3 * min(5, (int) $req->client_view_count));
            $score += $vBucket;
            $factors[] = ['label' => 'Client viewed', 'points' => $vBucket, 'note' => $req->client_view_count . ' view(s)'];
        } else {
            $factors[] = ['label' => 'No client views', 'points' => 0, 'note' => 'Share link not opened'];
        }

        // (3) Negotiation hygiene
        $crCount = count($changeRequests);
        if ($crCount === 0) {
            $score += 15; $factors[] = ['label' => 'No change requests', 'points' => 15, 'note' => 'Frictionless'];
        } else {
            $pendingCount = 0;
            foreach ($changeRequests as $cr) {
                if ($cr->status === 'pending') $pendingCount++;
            }
            $hygiene = $pendingCount === 0 ? 12 : max(0, 12 - ($pendingCount * 6));
            $score += $hygiene;
            $factors[] = [
                'label' => 'Change requests',
                'points' => $hygiene,
                'note' => $crCount . ' total, ' . $pendingCount . ' pending',
            ];
        }

        // (4) Payment cadence
        $payCount = count($payments);
        if ($payCount > 0) {
            $paidCount = 0; $overdueCount = 0;
            foreach ($payments as $p) {
                if ($p->status === 'paid') $paidCount++;
                if (in_array($p->status, ['scheduled', 'partial'], true)
                    && $p->due_date && $p->due_date < $today) $overdueCount++;
            }
            $payRate = $paidCount / $payCount;
            $payPenalty = $overdueCount * 4;
            $payScore = max(0, min(20, round($payRate * 20) - $payPenalty));
            $score += $payScore;
            $factors[] = [
                'label' => 'Payments',
                'points' => $payScore,
                'note' => $paidCount . '/' . $payCount . ' paid' . ($overdueCount > 0 ? ', ' . $overdueCount . ' overdue' : ''),
            ];
        } else {
            $factors[] = ['label' => 'No payment plan', 'points' => 0, 'note' => 'Schedule not set'];
        }

        // (5) Delivery health
        $itemCount = count($items);
        if ($itemCount > 0) {
            $slipping = 0; $delivered = 0;
            foreach ($items as $it) {
                $promised = $it->promised_delivery_date ?? null;
                $confirmed = $it->supplier_confirmed_date ?? null;
                if (($it->delivery_status ?? 'pending') === 'delivered') $delivered++;
                if ($promised && $confirmed && $confirmed > $promised) $slipping++;
                if ($promised && !$confirmed && in_array($it->delivery_status, ['pending','sourced','in_production'], true)
                    && $promised < $today) {
                    $slipping++;
                }
            }
            $deliveryRate = $delivered / $itemCount;
            $deliveryScore = max(0, min(20, round($deliveryRate * 20) - ($slipping * 4)));
            $score += $deliveryScore;
            $factors[] = [
                'label' => 'Delivery',
                'points' => $deliveryScore,
                'note' => $delivered . '/' . $itemCount . ' delivered' . ($slipping > 0 ? ', ' . $slipping . ' slipping' : ''),
            ];
        } else {
            $factors[] = ['label' => 'No items yet', 'points' => 0, 'note' => '—'];
        }

        $score = max(0, min(100, $score));
        return ['score' => $score, 'factors' => $factors];
    }

    /* ============================================================
     *  VERSIONING — Phase 13
     *
     *  Full snapshot history of a proforma. snapshotProforma() captures
     *  the current shape (proforma row + items + payments) and stores it
     *  in sourcing_request_versions. Triggered at sent / approved /
     *  fulfilled / markup-applied / manual.
     * ============================================================ */

    /**
     * Capture the current shape of a proforma + all its items + payments
     * as a single JSON snapshot. Idempotent in the sense that calling it
     * twice creates two versions — the caller decides whether that's
     * useful.
     *
     * Returns the new version_no.
     */
    private function snapshotProforma(int $sourcingRequestId, string $trigger = 'manual', ?string $label = null): int
    {
        $req = DB::table('sourcing_requests')->where('id', $sourcingRequestId)->first();
        if (!$req) return 0;

        $items = DB::table('sourcing_request_items')
            ->where('sourcing_request_id', $sourcingRequestId)
            ->orderBy('sort_order')->orderBy('id')
            ->get()->toArray();
        $payments = DB::table('sourcing_request_payments')
            ->where('sourcing_request_id', $sourcingRequestId)
            ->orderBy('sequence')->orderBy('id')
            ->get()->toArray();

        $snapshot = [
            'captured_at' => date('Y-m-d H:i:s'),
            'proforma'    => (array) $req,
            'items'       => array_map(fn($r) => (array) $r, $items),
            'payments'    => array_map(fn($r) => (array) $r, $payments),
        ];

        $nextNo = ((int) DB::table('sourcing_request_versions')
            ->where('sourcing_request_id', $sourcingRequestId)
            ->max('version_no')) + 1;

        $userId = null;
        try { $userId = auth()->user()->id ?? null; } catch (\Throwable $e) {}

        DB::table('sourcing_request_versions')->insert([
            'sourcing_request_id'    => $sourcingRequestId,
            'version_no'             => $nextNo,
            'trigger'                => $trigger,
            'label'                  => $label,
            'snapshot_json'          => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'status_at_snapshot'     => $req->status,
            'total_at_snapshot'      => (float) $req->proforma_total,
            'currency_at_snapshot'   => $req->display_currency ?: $req->currency,
            'item_count_at_snapshot' => count($items),
            'created_by_user_id'     => $userId,
            'created_at'             => date('Y-m-d H:i:s'),
            'updated_at'             => date('Y-m-d H:i:s'),
        ]);

        return $nextNo;
    }

    /** POST /sourcing/{id}/snapshot  body: label? */
    public function manualSnapshot(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->whereNull('deleted_at')->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        $label = trim((string) $request->get('label', '')) ?: null;
        $no = $this->snapshotProforma((int) $id, 'manual', $label);

        $this->logAudit('sourcing_snapshot', 'sourcing_requests', $id, [
            'version_no' => $no, 'trigger' => 'manual', 'label' => $label,
        ], 'Manual snapshot captured (v' . $no . ')');

        return response()->json(['type' => 'success', 'version_no' => $no]);
    }

    /**
     * GET /sourcing/{id}/versions/{versionNo} — read-only PDF of a past
     * version. Uses the same proforma_pdf template fed with the
     * snapshot data instead of live data.
     */
    public function versionPdf($id, $versionNo)
    {
        $this->requireAdminOrBranchAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        $v = DB::table('sourcing_request_versions')
            ->where('sourcing_request_id', $id)
            ->where('version_no', $versionNo)
            ->first();
        if (!$v) abort(404, 'Version not found');

        $snap = json_decode($v->snapshot_json, true) ?: [];
        $proformaData = $snap['proforma'] ?? [];
        $itemsData    = $snap['items'] ?? [];
        $paymentsData = $snap['payments'] ?? [];

        // Cast to objects for the blade template's $req->X / $it->Y access.
        $reqObj = (object) $proformaData;
        $itemObjs = collect($itemsData)->map(fn($r) => (object) $r);
        $payObjs  = collect($paymentsData)->map(fn($r) => (object) $r);

        $client = DB::table('clients')->where('id', $reqObj->client_id ?? 0)->first();
        $branch = !empty($reqObj->branch_id)
            ? DB::table('branches')->where('id', $reqObj->branch_id)->first()
            : null;
        $settings = (new settingsController())->get();
        $lang = new langController();
        $data = new dataController();

        $html = view('pages.sourcing.proforma_pdf', [
            'req'         => $reqObj,
            'client'      => $client,
            'branch'      => $branch,
            'items'       => $itemObjs,
            'payments'    => $payObjs,
            'photos'      => [],     // historical photos not snapshotted
            'documents'   => [],
            'settings'    => $settings,
            'lang'        => $lang,
            'data'        => $data,
        ])->render();

        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4',
            'default_font'   => 'dejavusans',
            'margin_top'     => 12, 'margin_bottom' => 12,
            'margin_left'    => 14, 'margin_right' => 14,
        ]);
        $mpdf->WriteHTML($html);
        $filename = 'proforma-' . ($reqObj->request_number ?? $id) . '-v' . $versionNo . '.pdf';
        return response($mpdf->Output($filename, 'I'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /**
     * GET /sourcing/{id}/diff?a=N&b=M — side-by-side comparison view.
     * Defaults to the two latest versions (b = newest, a = newest-1).
     * If only one version exists, compares it against the LIVE current
     * state so the operator can see "what changed since v1".
     */
    public function diff(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->whereNull('deleted_at')->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        $versions = DB::table('sourcing_request_versions')
            ->where('sourcing_request_id', $id)
            ->orderBy('version_no')->get();
        if ($versions->isEmpty()) abort(404, 'No versions yet — snapshot once and try again');

        $aNo = $request->get('a');
        $bNo = $request->get('b');
        // Default: compare last two versions when available; otherwise
        // compare the only version against live.
        $latest = $versions->last();
        if ($bNo === null) $bNo = $latest->version_no;
        if ($aNo === null) {
            $prev = $versions->where('version_no', '<', $latest->version_no)->last();
            $aNo = $prev ? $prev->version_no : 'live';
        }

        $sideA = $this->resolveDiffSide($id, $aNo, $versions);
        $sideB = $this->resolveDiffSide($id, $bNo, $versions);
        if (!$sideA || !$sideB) abort(404, 'Could not resolve version');

        $changes = $this->computeDiff($sideA['snapshot'], $sideB['snapshot']);

        $lang = new langController();
        return view('pages.sourcing.diff', [
            'req'      => $req,
            'sideA'    => $sideA,
            'sideB'    => $sideB,
            'changes'  => $changes,
            'versions' => $versions,
            'lang'     => $lang,
            'section'  => 'sourcing',
            'page'     => 'sourcing',
        ]);
    }

    /**
     * Resolve a diff side from either a version_no string or 'live'.
     * Returns ['label' => '...', 'snapshot' => [...], 'captured_at' => ...]
     * or null when nothing matches.
     */
    private function resolveDiffSide(int $reqId, $key, $versions): ?array
    {
        if ($key === 'live') {
            // Snapshot the current state (without persisting) so the
            // computeDiff() call gets uniform input on both sides.
            $req = DB::table('sourcing_requests')->where('id', $reqId)->first();
            return [
                'key'         => 'live',
                'label'       => 'Live (now)',
                'captured_at' => date('Y-m-d H:i:s'),
                'snapshot'    => [
                    'proforma' => (array) $req,
                    'items'    => DB::table('sourcing_request_items')
                        ->where('sourcing_request_id', $reqId)
                        ->orderBy('sort_order')->orderBy('id')
                        ->get()->map(fn($r) => (array) $r)->toArray(),
                    'payments' => DB::table('sourcing_request_payments')
                        ->where('sourcing_request_id', $reqId)
                        ->orderBy('sequence')->orderBy('id')
                        ->get()->map(fn($r) => (array) $r)->toArray(),
                ],
            ];
        }
        $v = $versions->firstWhere('version_no', (int) $key);
        if (!$v) return null;
        return [
            'key'         => (string) $v->version_no,
            'label'       => 'v' . $v->version_no . ($v->label ? ' — ' . $v->label : ''),
            'captured_at' => $v->created_at,
            'trigger'     => $v->trigger,
            'snapshot'    => json_decode($v->snapshot_json, true) ?: [],
        ];
    }

    /**
     * Compare two snapshots and produce a list of human-readable changes.
     * Keep the structure shallow — the view renders it as a flat list.
     */
    private function computeDiff(array $a, array $b): array
    {
        $changes = [
            'proforma' => [],
            'items'    => ['added' => [], 'removed' => [], 'modified' => []],
            'payments' => ['added' => [], 'removed' => [], 'modified' => []],
        ];

        // Proforma-level fields we track in the diff. Keep this list
        // small and meaningful — there's no value diffing updated_at.
        $tracked = [
            'title', 'description', 'status', 'display_currency',
            'commission_mode', 'commission_amount', 'commission_currency',
            'payment_plan', 'terms_text', 'items_subtotal', 'proforma_total',
        ];
        foreach ($tracked as $f) {
            $av = $a['proforma'][$f] ?? null;
            $bv = $b['proforma'][$f] ?? null;
            if ((string) $av !== (string) $bv) {
                $changes['proforma'][] = ['field' => $f, 'from' => $av, 'to' => $bv];
            }
        }

        // Match items by id so reorderings / edits don't look like
        // "removed + added".
        $byIdA = collect($a['items'] ?? [])->keyBy('id');
        $byIdB = collect($b['items'] ?? [])->keyBy('id');
        foreach ($byIdB as $id => $itB) {
            if (!isset($byIdA[$id])) {
                $changes['items']['added'][] = $itB;
                continue;
            }
            $itA = $byIdA[$id];
            $itemFields = ['name', 'code', 'quantity', 'unit', 'unit_cost',
                           'unit_cost_currency', 'unit_price_to_client',
                           'weight_kg', 'cbm', 'delivery_status'];
            $diffs = [];
            foreach ($itemFields as $f) {
                if ((string) ($itA[$f] ?? '') !== (string) ($itB[$f] ?? '')) {
                    $diffs[] = ['field' => $f, 'from' => $itA[$f] ?? null, 'to' => $itB[$f] ?? null];
                }
            }
            if (!empty($diffs)) {
                $changes['items']['modified'][] = ['item' => $itB, 'changes' => $diffs];
            }
        }
        foreach ($byIdA as $id => $itA) {
            if (!isset($byIdB[$id])) $changes['items']['removed'][] = $itA;
        }

        // Same shape for payments — match by id.
        $payA = collect($a['payments'] ?? [])->keyBy('id');
        $payB = collect($b['payments'] ?? [])->keyBy('id');
        foreach ($payB as $id => $pB) {
            if (!isset($payA[$id])) { $changes['payments']['added'][] = $pB; continue; }
            $pA = $payA[$id];
            $payFields = ['label', 'percentage', 'amount', 'currency', 'due_date', 'status'];
            $diffs = [];
            foreach ($payFields as $f) {
                if ((string) ($pA[$f] ?? '') !== (string) ($pB[$f] ?? '')) {
                    $diffs[] = ['field' => $f, 'from' => $pA[$f] ?? null, 'to' => $pB[$f] ?? null];
                }
            }
            if (!empty($diffs)) {
                $changes['payments']['modified'][] = ['payment' => $pB, 'changes' => $diffs];
            }
        }
        foreach ($payA as $id => $pA) {
            if (!isset($payB[$id])) $changes['payments']['removed'][] = $pA;
        }
        return $changes;
    }

    /* ============================================================
     *  PROCUREMENT INTEGRATION — Phase 12
     *
     *  Many-to-many link between proformas and purchase_orders, plus
     *  per-item delivery commitments (promised vs supplier-confirmed).
     *  Lets the operator cross-check "we promised the client X by date
     *  Y" against "the supplier confirmed shipment on date Z".
     * ============================================================ */

    /**
     * POST /sourcing/{id}/po-link  body: po_id, note?
     * POST /sourcing/{id}/po-unlink  body: link_id
     * POST /sourcing/items/dates  body: id, promised_delivery_date?, supplier_confirmed_date?
     * GET  /sourcing/po-search?q=...  (JSON for picker)
     */
    public function poLink(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $v = $request->validate([
            'po_id' => 'required|integer|exists:purchase_orders,id',
            'note'  => 'nullable|string|max:500',
        ]);
        $req = DB::table('sourcing_requests')->where('id', $id)->whereNull('deleted_at')->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        // The unique index handles "already linked" — catch the duplicate
        // gracefully so the operator just sees a no-op.
        try {
            $linkId = DB::table('sourcing_request_purchase_orders')->insertGetId([
                'sourcing_request_id' => $req->id,
                'purchase_order_id'   => $v['po_id'],
                'note'                => $v['note'] ?? null,
                'linked_by_user_id'   => auth()->user()->id,
                'created_at'          => date('Y-m-d H:i:s'),
                'updated_at'          => date('Y-m-d H:i:s'),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == '23000') {
                return response()->json(['type' => 'noop', 'message' => 'Already linked']);
            }
            throw $e;
        }

        $this->logAudit('sourcing_po_link', 'sourcing_request_purchase_orders', $linkId, [
            'sourcing_request_id' => $req->id,
            'purchase_order_id'   => $v['po_id'],
        ], 'Linked PO to proforma');

        return response()->json(['type' => 'success', 'id' => $linkId]);
    }

    public function poUnlink(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $v = $request->validate([
            'link_id' => 'required|integer|exists:sourcing_request_purchase_orders,id',
        ]);
        $link = DB::table('sourcing_request_purchase_orders')->where('id', $v['link_id'])->first();
        if (!$link || (int) $link->sourcing_request_id !== (int) $id) abort(404);

        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        $this->assertCanAccessClient($req->client_id);

        DB::table('sourcing_request_purchase_orders')->where('id', $v['link_id'])->delete();
        $this->logAudit('sourcing_po_unlink', 'sourcing_request_purchase_orders', $v['link_id'], [
            'sourcing_request_id' => $id,
            'purchase_order_id'   => $link->purchase_order_id,
        ], 'Unlinked PO from proforma');

        return response()->json(['type' => 'success']);
    }

    /**
     * GET /sourcing/po-search?q=... — JSON picker. Live-search POs by
     * order_number / supplier_name / customer_id. Returns the 50 most
     * recent matches plus a status hint so the operator can avoid
     * linking already-delivered/cancelled POs.
     */
    public function poSearch(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $search = trim((string) $request->get('q', ''));
        $clientId = $request->get('client_id');

        $q = DB::table('purchase_orders as po')
            ->select('po.id', 'po.order_number', 'po.status', 'po.supplier_name',
                     'po.customer_id', 'po.estimated_total_usd', 'po.actual_total_usd',
                     'po.purchasing_started_at', 'po.delivered_at')
            ->orderByDesc('po.id')
            ->limit(50);
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('po.order_number', 'like', "%$search%")
                  ->orWhere('po.supplier_name', 'like', "%$search%");
            });
        }
        if ($clientId) {
            // Show this client's POs first when picking from a proforma
            // — bumps relevance without hiding cross-client options.
            $q->orderByRaw("CASE WHEN customer_id = ? THEN 0 ELSE 1 END", [(int) $clientId]);
        }
        return response()->json(['type' => 'success', 'items' => $q->get()]);
    }

    /**
     * POST /sourcing/items/dates — update promised + supplier-confirmed
     * delivery dates on a single item. Both nullable; null clears.
     */
    public function updateItemDates(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $v = $request->validate([
            'id'                      => 'required|integer|exists:sourcing_request_items,id',
            'promised_delivery_date'  => 'nullable|date',
            'supplier_confirmed_date' => 'nullable|date',
        ]);
        $item = DB::table('sourcing_request_items')->where('id', $v['id'])->first();
        if (!$item) abort(404);
        $req = DB::table('sourcing_requests')->where('id', $item->sourcing_request_id)->first();
        $this->assertCanAccessClient($req->client_id);

        DB::table('sourcing_request_items')->where('id', $v['id'])->update([
            'promised_delivery_date'  => $v['promised_delivery_date'] ?? null,
            'supplier_confirmed_date' => $v['supplier_confirmed_date'] ?? null,
            'updated_at'              => date('Y-m-d H:i:s'),
        ]);
        $this->logAudit('sourcing_item_dates', 'sourcing_request_items', $v['id'], $v, 'Item delivery dates updated');

        return response()->json(['type' => 'success']);
    }

    /**
     * Helper — fetch every PO linked to the given proforma, plus join in
     * the live PO status/totals for display.
     */
    private function loadLinkedPOs(int $sourcingRequestId)
    {
        return DB::table('sourcing_request_purchase_orders as l')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'l.purchase_order_id')
            ->leftJoin('users as u', 'u.id', '=', 'l.linked_by_user_id')
            ->where('l.sourcing_request_id', $sourcingRequestId)
            ->orderByDesc('l.created_at')
            ->select(
                'l.id as link_id', 'l.note', 'l.created_at as linked_at',
                'po.id as po_id', 'po.order_number', 'po.status', 'po.supplier_name',
                'po.estimated_total_usd', 'po.actual_total_usd',
                'po.purchasing_started_at', 'po.delivered_at', 'po.shipped_at',
                'u.name as linked_by_name'
            )->get();
    }

    /* ------------------------------------------------------------
     *  Auto-reminder runner — Phase 11
     *
     *  Picks every quoted proforma where:
     *    - sent_at is older than `min_age_days` (default 3)
     *    - approved_at is NULL
     *    - status is 'quoted'
     *    - the client has an email
     *    - we haven't already sent a reminder in the last
     *      `cooldown_days` (default 5) — uses audit_log
     *
     *  POST /sourcing/reminders/run  (admin trigger)
     *  Also callable from the Artisan command 'sourcing:remind'.
     *
     *  Returns { sent, skipped, failed } counts so the UI can show
     *  results without scraping the audit log.
     * ------------------------------------------------------------ */
    public function runReminders(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        return response()->json($this->runRemindersImpl(
            (int) ($request->get('min_age_days') ?? 3),
            (int) ($request->get('cooldown_days') ?? 5),
            (int) ($request->get('limit') ?? 50)
        ));
    }

    /**
     * Public so the Artisan command can call it. Returns a summary
     * dict — no HTTP response involved.
     */
    public function runRemindersImpl(int $minAgeDays = 3, int $cooldownDays = 5, int $limit = 50): array
    {
        $cutoffSent     = date('Y-m-d H:i:s', strtotime("-{$minAgeDays} days"));
        $cutoffCooldown = date('Y-m-d H:i:s', strtotime("-{$cooldownDays} days"));

        // Candidate proformas. We deliberately don't branch-scope here —
        // the Artisan command runs without a user context.
        $candidates = DB::table('sourcing_requests as sr')
            ->leftJoin('clients as c', 'c.id', '=', 'sr.client_id')
            ->whereNull('sr.deleted_at')
            ->where('sr.status', 'quoted')
            ->whereNotNull('sr.sent_at')
            ->where('sr.sent_at', '<=', $cutoffSent)
            ->whereNotNull('sr.share_token')
            ->whereNotNull('c.email')
            ->where('c.email', '!=', '')
            ->select('sr.*', 'c.name as client_name', 'c.email as client_email')
            ->orderBy('sr.sent_at')
            ->limit($limit)->get();

        // Filter out anyone we've already reminded recently.
        $recentReminders = DB::table('audit_log')
            ->where('action', 'sourcing_reminder_sent')
            ->where('created_at', '>=', $cutoffCooldown)
            ->pluck('target_id')->all();
        $recentReminders = array_flip($recentReminders);

        $settings = (new settingsController())->get();
        $company = $settings['company_name'] ?? '';
        $subjectTpl = $settings['proforma_reminder_subject'] ?: 'Friendly reminder — Proforma {number}';
        $bodyTpl    = $settings['proforma_reminder_body']    ?: "Dear {client},\n\nProforma {number} is awaiting your review:\n\n{link}\n\nThank you,\n{company}";

        $sent = 0; $skipped = 0; $failed = 0;
        foreach ($candidates as $req) {
            if (isset($recentReminders[$req->id])) { $skipped++; continue; }

            $publicUrl = url('/proforma/' . $req->share_token);
            $subject = str_replace(['{link}','{number}','{client}','{total}','{company}'], [
                $publicUrl, $req->request_number, $req->client_name ?? '',
                number_format((float) $req->proforma_total, 2) . ' ' . strtoupper($req->display_currency ?: $req->currency ?: 'usd'),
                $company,
            ], $subjectTpl);
            $body = str_replace(['{link}','{number}','{client}','{total}','{company}'], [
                $publicUrl, $req->request_number, $req->client_name ?? '',
                number_format((float) $req->proforma_total, 2) . ' ' . strtoupper($req->display_currency ?: $req->currency ?: 'usd'),
                $company,
            ], $bodyTpl);

            try {
                Mail::send([], [], function ($m) use ($req, $subject, $body) {
                    $m->to($req->client_email)->subject($subject)->text($body);
                });
                DB::table('audit_log')->insert([
                    'user_id'      => null,
                    'user_type'    => 'system',
                    'action'       => 'sourcing_reminder_sent',
                    'target_table' => 'sourcing_requests',
                    'target_id'    => (int) $req->id,
                    'payload'      => json_encode([
                        'to' => $req->client_email,
                        'subject' => $subject,
                        'auto' => true,
                    ]),
                    'context'      => 'Auto-reminder sent by sourcing:remind',
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('sourcing auto-reminder failed: ' . $e->getMessage(), ['request_id' => $req->id]);
                $failed++;
            }
        }

        return compact('sent', 'skipped', 'failed') + ['scanned' => count($candidates)];
    }

    /* ------------------------------------------------------------
     *  Pipeline funnel analytics — Phase 11
     *
     *  GET /sourcing/funnel?from=&to=&stuck_days=
     *
     *  Counts per stage + stage-to-stage conversion rates + average
     *  hours between status changes (mined from the audit log) +
     *  stuck-deal list (in the same status for >N days).
     * ------------------------------------------------------------ */
    public function funnel(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $from = $request->get('from', date('Y-m-01', strtotime('-2 months')));
        $to   = $request->get('to',   date('Y-m-t'));
        $stuckDays = max(1, (int) $request->get('stuck_days', 7));

        $user = auth()->user();
        $base = DB::table('sourcing_requests')->whereNull('deleted_at')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
        if ($user->type !== 'admin') $base->where('branch_id', $user->branch);

        // Counts by terminal observation: every proforma created in the
        // window contributes to the most-advanced stage it reached. We
        // count by current status; the audit-derived dwell times handle
        // "how long did it sit" separately.
        $stages = ['open', 'searching', 'quoted', 'accepted', 'fulfilled'];
        $counts = array_fill_keys($stages, 0);
        foreach ((clone $base)->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->get() as $r) {
            if (isset($counts[$r->status])) $counts[$r->status] = (int) $r->n;
        }
        // Cumulative funnel: anyone fulfilled was also accepted/quoted/etc.
        $cumulative = [
            'open'      => array_sum($counts),
            'searching' => $counts['searching'] + $counts['quoted'] + $counts['accepted'] + $counts['fulfilled'],
            'quoted'    => $counts['quoted'] + $counts['accepted'] + $counts['fulfilled'],
            'accepted'  => $counts['accepted'] + $counts['fulfilled'],
            'fulfilled' => $counts['fulfilled'],
        ];

        // Conversion ratios between adjacent cumulative buckets.
        $conv = [];
        $pairs = [['open','searching'], ['searching','quoted'], ['quoted','accepted'], ['accepted','fulfilled']];
        foreach ($pairs as [$a, $b]) {
            $conv["{$a}_to_{$b}"] = $cumulative[$a] > 0
                ? round(100 * $cumulative[$b] / $cumulative[$a], 1)
                : 0.0;
        }

        // Average dwell between transitions — derived from audit_log
        // entries with action 'sourcing_quick_move' (Phase 10 endpoint).
        // For unbounded movement (sent / approved / fulfilled) we lean
        // on the direct columns on sourcing_requests.
        $dwellRows = (clone $base)
            ->select(
                DB::raw("AVG(TIMESTAMPDIFF(HOUR, created_at, sent_at)) as avg_create_to_send"),
                DB::raw("AVG(TIMESTAMPDIFF(HOUR, sent_at, approved_at)) as avg_send_to_approve")
            )
            ->whereNotNull('sent_at')
            ->first();
        $dwell = [
            'create_to_send'  => $dwellRows && $dwellRows->avg_create_to_send  !== null ? round((float) $dwellRows->avg_create_to_send, 1) : null,
            'send_to_approve' => $dwellRows && $dwellRows->avg_send_to_approve !== null ? round((float) $dwellRows->avg_send_to_approve, 1) : null,
        ];

        // Stuck deals — same status for >= N days. Cheap proxy:
        // updated_at < (now - N days) AND status is non-terminal.
        $stuck = (clone $base)
            ->whereIn('status', ['open', 'searching', 'quoted', 'accepted'])
            ->whereDate('updated_at', '<', date('Y-m-d', strtotime("-{$stuckDays} days")))
            ->leftJoin('clients as c', 'c.id', '=', 'sourcing_requests.client_id')
            ->select(
                'sourcing_requests.id', 'sourcing_requests.request_number',
                'sourcing_requests.title', 'sourcing_requests.status',
                'sourcing_requests.proforma_total', 'sourcing_requests.currency',
                'sourcing_requests.updated_at',
                'c.name as client_name', 'c.code as client_code'
            )
            ->orderBy('sourcing_requests.updated_at')
            ->limit(50)->get();

        $lang = new langController();
        return view('pages.sourcing.funnel', [
            'from'       => $from,
            'to'         => $to,
            'stuckDays'  => $stuckDays,
            'counts'     => $counts,
            'cumulative' => $cumulative,
            'conv'       => $conv,
            'dwell'      => $dwell,
            'stuck'      => $stuck,
            'lang'       => $lang,
            'section'    => 'sourcing',
            'page'       => 'sourcing_funnel',
        ]);
    }

    /* ============================================================
     *  PRODUCT CATALOG — Phase 11
     *
     *  Reusable product library. Operators add common items here once
     *  and pick from the picker when adding to a proforma. Avoids the
     *  retype-the-same-widget-every-time problem.
     * ============================================================ */

    /** GET /sourcing/catalog (JSON, used by the picker). */
    public function catalogIndex(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $search = trim((string) $request->get('q', ''));

        $q = DB::table('product_catalog')->where('is_active', true);
        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%$search%")
                  ->orWhere('code', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%");
            });
        }
        $rows = $q->orderByDesc('usage_count')->orderBy('name')->limit(200)->get();
        return response()->json([
            'type' => 'success',
            'items' => $rows,
        ]);
    }

    /** POST /sourcing/catalog/save */
    public function catalogSave(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $v = $request->validate([
            'id'                         => 'nullable|integer|exists:product_catalog,id',
            'name'                       => 'required|string|max:191',
            'code'                       => 'nullable|string|max:64',
            'description'                => 'nullable|string',
            'unit'                       => 'nullable|string|max:32',
            'default_unit_cost'          => 'required|numeric|min:0',
            'default_unit_cost_currency' => 'required|string|max:8',
            'default_unit_price'         => 'required|numeric|min:0',
            'default_weight_kg'          => 'nullable|numeric|min:0',
            'default_cbm'                => 'nullable|numeric|min:0',
            'is_active'                  => 'nullable|boolean',
        ]);

        $payload = [
            'name'                        => $v['name'],
            'code'                        => $v['code'] ?? null,
            'description'                 => $v['description'] ?? null,
            'unit'                        => $v['unit'] ?? 'pcs',
            'default_unit_cost'           => $v['default_unit_cost'],
            'default_unit_cost_currency'  => $v['default_unit_cost_currency'],
            'default_unit_price'          => $v['default_unit_price'],
            'default_weight_kg'           => $v['default_weight_kg'] ?? null,
            'default_cbm'                 => $v['default_cbm'] ?? null,
            'is_active'                   => array_key_exists('is_active', $v) ? (bool) $v['is_active'] : true,
            'updated_at'                  => date('Y-m-d H:i:s'),
        ];

        if (!empty($v['id'])) {
            DB::table('product_catalog')->where('id', $v['id'])->update($payload);
            $id = $v['id'];
            $this->logAudit('catalog_update', 'product_catalog', $id, ['name' => $v['name']], 'Catalog item updated');
        } else {
            $payload['created_by'] = auth()->user()->id;
            $payload['created_at'] = date('Y-m-d H:i:s');
            $id = DB::table('product_catalog')->insertGetId($payload);
            $this->logAudit('catalog_create', 'product_catalog', $id, ['name' => $v['name']], 'Catalog item created');
        }

        return response()->json(['type' => 'success', 'id' => $id]);
    }

    /** POST /sourcing/catalog/delete */
    public function catalogDelete(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $id = (int) $request->id;
        $row = DB::table('product_catalog')->where('id', $id)->first();
        if (!$row) abort(404);

        // Soft via is_active=false — operators may have proformas that
        // pointed at this row historically; we don't want orphan refs.
        DB::table('product_catalog')->where('id', $id)->update([
            'is_active' => false,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->logAudit('catalog_delete', 'product_catalog', $id, null, 'Catalog item deactivated');
        return response()->json(['type' => 'success']);
    }

    /** GET /sourcing/catalog/manage — admin UI to manage the library. */
    public function catalogManage()
    {
        $this->requireAdminOrBranchAdmin();
        $rows = DB::table('product_catalog')
            ->orderByDesc('is_active')
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->limit(500)->get();
        $dataController = new dataController();
        $lang = new langController();
        return view('pages.sourcing.catalog_manage', [
            'rows'    => $rows,
            'data'    => $dataController,
            'lang'    => $lang,
            'section' => 'sourcing',
            'page'    => 'sourcing',
        ]);
    }

    /* ------------------------------------------------------------
     *  Sourcing pipeline kanban — Phase 10
     *
     *  GET /sourcing/board
     *
     *  Renders proformas as cards in 5 status columns. Branch admins
     *  see only their branch's pipeline; admins see everything (or a
     *  branch slice via ?branch=N).
     * ------------------------------------------------------------ */
    public function board(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $user = auth()->user();
        $branchFilter = null;
        if ($user->type === 'admin') {
            $b = $request->get('branch');
            $branchFilter = is_numeric($b) && (int) $b > 0 ? (int) $b : null;
        } else {
            $branchFilter = (int) $user->branch;
        }

        $q = DB::table('sourcing_requests as sr')
            ->leftJoin('clients as c', 'c.id', '=', 'sr.client_id')
            ->whereNull('sr.deleted_at')
            ->whereNotIn('sr.status', ['canceled'])
            ->select(
                'sr.id', 'sr.request_number', 'sr.title', 'sr.status',
                'sr.display_currency', 'sr.currency', 'sr.proforma_total',
                'sr.sent_at', 'sr.approved_at', 'sr.client_view_count',
                'sr.client_id', 'sr.created_at',
                'c.name as client_name', 'c.code as client_code'
            );
        if ($branchFilter !== null) $q->where('sr.branch_id', $branchFilter);

        $rows = $q->orderByDesc('sr.id')->limit(500)->get();

        // Bucket the cards by status; preserve the canonical order so
        // the columns render left-to-right by funnel stage.
        $columns = ['open' => [], 'searching' => [], 'quoted' => [], 'accepted' => [], 'fulfilled' => []];
        foreach ($rows as $r) {
            if (isset($columns[$r->status])) $columns[$r->status][] = $r;
        }

        // Next-installment due date per proforma — tiny info-density boost
        // on the card without an extra query per row.
        $reqIds = $rows->pluck('id')->all();
        $nextDueByReq = [];
        if (!empty($reqIds)) {
            $nextDueRows = DB::table('sourcing_request_payments')
                ->whereIn('sourcing_request_id', $reqIds)
                ->where('status', 'scheduled')
                ->whereNotNull('due_date')
                ->select('sourcing_request_id', DB::raw('MIN(due_date) as next_due'))
                ->groupBy('sourcing_request_id')->get();
            foreach ($nextDueRows as $nd) $nextDueByReq[$nd->sourcing_request_id] = $nd->next_due;
        }

        $allBranches = $user->type === 'admin'
            ? DB::table('branches')->where('deleted', 'false')->orderBy('id')->get(['id', 'name'])
            : collect();

        $lang = new langController();
        return view('pages.sourcing.board', [
            'columns'         => $columns,
            'nextDueByReq'    => $nextDueByReq,
            'allBranches'     => $allBranches,
            'branchFilter'    => $branchFilter,
            'lang'            => $lang,
            'today'           => date('Y-m-d'),
            'section'         => 'sourcing',
            'page'            => 'sourcing',
        ]);
    }

    /**
     * POST /sourcing/{id}/status
     * Quick-move a proforma between funnel states from the kanban. Stops
     * short of states that have side effects (approval, fulfillment) —
     * those still go through their dedicated endpoints so the journal /
     * timeline stays consistent.
     */
    public function quickMoveStatus(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $v = $request->validate([
            'status' => 'required|in:open,searching,quoted',
        ]);
        $req = DB::table('sourcing_requests')->where('id', $id)->whereNull('deleted_at')->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        if (!in_array($req->status, ['open', 'searching', 'quoted'], true)) {
            return response()->json([
                'type' => 'error',
                'message' => 'Can only move open/searching/quoted proformas from the board. Use the full show page for accepted/fulfilled.',
            ], 422);
        }
        if ($req->status === $v['status']) return response()->json(['type' => 'noop']);

        DB::table('sourcing_requests')->where('id', $id)->update([
            'status'     => $v['status'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->logAudit('sourcing_quick_move', 'sourcing_requests', $id, [
            'from' => $req->status, 'to' => $v['status'],
        ], 'Quick-moved on board: ' . $req->status . ' → ' . $v['status']);

        return response()->json(['type' => 'success']);
    }

    /* ------------------------------------------------------------
     *  Soft-delete / restore — Phase 9
     *
     *  POST /sourcing/{id}/trash    — set deleted_at
     *  POST /sourcing/{id}/restore  — null deleted_at
     *  POST /sourcing/{id}/destroy  — hard-delete (only when canceled AND
     *                                 trashed; cleans up files + audit
     *                                 rows + cost-object journal lines
     *                                 left orphaned)
     * ------------------------------------------------------------ */
    public function trash(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        if ($req->deleted_at) {
            return response()->json(['type' => 'noop']);
        }

        DB::table('sourcing_requests')->where('id', $id)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->logAudit('sourcing_trash', 'sourcing_requests', $id, [
            'status' => $req->status,
        ], 'Moved to trash');
        return response()->json(['type' => 'success']);
    }

    public function restore(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        DB::table('sourcing_requests')->where('id', $id)->update([
            'deleted_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->logAudit('sourcing_restore', 'sourcing_requests', $id, null, 'Restored from trash');
        return response()->json(['type' => 'success']);
    }

    /**
     * Hard-delete — irreversible. Only allowed when the proforma is both
     * 'canceled' (business outcome closed) AND already in the trash
     * (operator chose to hide it). Removes child rows, uploaded files,
     * audit rows. Journal entries are LEFT IN PLACE — accounting is
     * append-only, and reversing posts already happened on cancel.
     */
    public function destroy(Request $request, $id)
    {
        $this->requireAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);
        if ($req->status !== 'canceled' || !$req->deleted_at) {
            return response()->json([
                'type' => 'error',
                'message' => 'Hard-delete requires a canceled + trashed proforma. Cancel first, then trash, then destroy.',
            ], 422);
        }

        DB::transaction(function () use ($req) {
            // Item photos — files first, then rows.
            $photos = DB::table('sourcing_request_item_photos as p')
                ->join('sourcing_request_items as i', 'i.id', '=', 'p.item_id')
                ->where('i.sourcing_request_id', $req->id)
                ->select('p.path', 'p.id')->get();
            foreach ($photos as $p) {
                $abs = storage_path('app/public/' . ltrim($p->path, '/'));
                if (is_file($abs)) @unlink($abs);
            }
            // Document files.
            $docs = DB::table('sourcing_request_documents')->where('sourcing_request_id', $req->id)->get();
            foreach ($docs as $d) {
                $abs = storage_path('app/public/' . ltrim($d->path, '/'));
                if (is_file($abs)) @unlink($abs);
            }

            // Child rows.
            DB::table('sourcing_request_item_photos')
                ->whereIn('item_id', DB::table('sourcing_request_items')->where('sourcing_request_id', $req->id)->pluck('id'))
                ->delete();
            DB::table('sourcing_request_items')->where('sourcing_request_id', $req->id)->delete();
            DB::table('sourcing_request_quotes')->where('sourcing_request_id', $req->id)->delete();
            DB::table('sourcing_request_payments')->where('sourcing_request_id', $req->id)->delete();
            DB::table('sourcing_request_documents')->where('sourcing_request_id', $req->id)->delete();
            DB::table('sourcing_request_change_requests')->where('sourcing_request_id', $req->id)->delete();
            DB::table('audit_log')->where('target_table', 'sourcing_requests')->where('target_id', $req->id)->delete();
            DB::table('sourcing_requests')->where('id', $req->id)->delete();
        });

        $this->logAudit('sourcing_hard_delete', 'sourcing_requests', $id, [
            'request_number' => $req->request_number,
            'client_id'      => $req->client_id,
        ], 'Hard-deleted ' . $req->request_number);

        return response()->json(['type' => 'success']);
    }

    /* ------------------------------------------------------------
     *  Rotate share token — Phase 8
     *
     *  POST /sourcing/{id}/rotate-token
     *
     *  Replaces the share_token with a fresh 32-byte URL-safe value.
     *  The old link returns 404 ("Proforma not found") because the
     *  publicProforma lookup is keyed on the live token. Useful when:
     *
     *    - the link was forwarded too widely and the operator wants the
     *      old chain dead
     *    - the proforma was sent to the wrong client and needs to be
     *      retargeted
     *
     *  Resets client_viewed_at + client_view_count so the new tracking
     *  starts from this issuance.
     * ------------------------------------------------------------ */
    public function rotateToken(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        if (!$req->share_token) {
            return response()->json([
                'type' => 'error',
                'message' => 'No active share link to rotate. Send the proforma first.',
            ], 422);
        }
        if (in_array($req->status, ['canceled', 'fulfilled'], true)) {
            return response()->json([
                'type' => 'error',
                'message' => 'Cannot rotate the link on a ' . $req->status . ' proforma.',
            ], 422);
        }

        $oldFirst8 = substr($req->share_token, 0, 8) . '…';
        $newToken  = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $newExp    = date('Y-m-d H:i:s', strtotime('+90 days'));

        DB::table('sourcing_requests')->where('id', $id)->update([
            'share_token'            => $newToken,
            'share_token_expires_at' => $newExp,
            'client_viewed_at'       => null,
            'client_view_count'      => 0,
            'updated_at'             => date('Y-m-d H:i:s'),
        ]);

        $this->logAudit('sourcing_token_rotate', 'sourcing_requests', $id, [
            'old_first8' => $oldFirst8,
            'new_first8' => substr($newToken, 0, 8) . '…',
            'expires_at' => $newExp,
        ], 'Share token rotated — old link invalidated');

        return response()->json([
            'type'       => 'success',
            'token'      => $newToken,
            'public_url' => url('/proforma/' . $newToken),
            'expires_at' => $newExp,
        ]);
    }

    /* ------------------------------------------------------------
     *  Container → item status sync — Phase 7
     *
     *  POST /sourcing/{id}/sync-from-container
     *
     *  Pulls the linked freight container's state and bulk-updates the
     *  proforma's items' delivery_status. Today the mapping is:
     *
     *    container exists + not canceled → all items become 'shipped'
     *    container canceled              → all items revert to 'pending'
     *
     *  Items already at 'delivered' are preserved — the operator marks
     *  those individually as physical delivery happens at the other end.
     *
     *  Returns the number of items actually changed.
     * ------------------------------------------------------------ */
    public function syncFromContainer(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        if (!$req->freight_kind || !$req->freight_container_id) {
            return response()->json([
                'type' => 'error',
                'message' => 'No freight container linked — use Send to freight first.',
            ], 422);
        }

        $table = $req->freight_kind === 'sky' ? 'containers_sky' : 'containers_sea';
        $container = DB::table($table)->where('id', $req->freight_container_id)->first();
        if (!$container) {
            return response()->json([
                'type' => 'error',
                'message' => 'Linked container no longer exists',
            ], 422);
        }

        $isCanceled = ($container->canceled ?? 'false') === 'true';
        $targetStatus = $isCanceled ? 'pending' : 'shipped';

        // Items at 'delivered' are preserved on a non-canceled sync —
        // physical delivery has already happened and shouldn't regress.
        $items = DB::table('sourcing_request_items')
            ->where('sourcing_request_id', $req->id)->get();

        $updated = 0;
        foreach ($items as $it) {
            $current = $it->delivery_status ?: 'pending';
            if (!$isCanceled && $current === 'delivered') continue;
            if ($current === $targetStatus) continue;
            DB::table('sourcing_request_items')->where('id', $it->id)->update([
                'delivery_status'   => $targetStatus,
                'status_changed_at' => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);
            $updated++;
        }

        $this->logAudit('sourcing_container_sync', 'sourcing_requests', $req->id, [
            'container_id'  => $req->freight_container_id,
            'kind'          => $req->freight_kind,
            'target_status' => $targetStatus,
            'updated_items' => $updated,
        ], 'Synced item statuses from container');

        return response()->json([
            'type'    => 'success',
            'updated' => $updated,
        ]);
    }

    /* ------------------------------------------------------------
     *  Client change requests — Phase 7
     *
     *  PUBLIC:
     *    POST /proforma/{token}/request-changes
     *       body: comment, suggested_changes?, reply_to_email?
     *
     *  ADMIN:
     *    POST /sourcing/change-requests/respond
     *       body: id, response, status (responded|dismissed)
     *
     *  When a new change request lands, any prior pending request gets
     *  marked 'superseded' so the admin only sees the latest ask.
     * ------------------------------------------------------------ */
    public function publicRequestChanges(Request $request, $token)
    {
        $req = DB::table('sourcing_requests')->where('share_token', $token)
            ->whereNull('deleted_at')->first();
        if (!$req) abort(404);
        if ($req->share_token_expires_at && strtotime($req->share_token_expires_at) < time()) {
            abort(410);
        }
        if (in_array($req->status, ['accepted', 'fulfilled', 'canceled'], true)) {
            return response()->json([
                'type' => 'error',
                'message' => 'Proforma is already ' . $req->status . ' — please contact us directly.',
            ], 422);
        }

        $v = $request->validate([
            'comment'           => 'required|string|max:5000',
            'suggested_changes' => 'nullable|array',
            'reply_to_email'    => 'nullable|email|max:191',
        ]);

        $newId = null;
        DB::transaction(function () use ($req, $v, $token, $request, &$newId) {
            // Roll forward any unresolved older requests so the admin only
            // looks at the latest one.
            DB::table('sourcing_request_change_requests')
                ->where('sourcing_request_id', $req->id)
                ->where('status', 'pending')
                ->update([
                    'status'     => 'superseded',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $newId = DB::table('sourcing_request_change_requests')->insertGetId([
                'sourcing_request_id' => $req->id,
                'comment'             => $v['comment'],
                'suggested_changes'   => !empty($v['suggested_changes'])
                                            ? json_encode($v['suggested_changes'], JSON_UNESCAPED_UNICODE)
                                            : null,
                'reply_to_email'      => $v['reply_to_email'] ?? null,
                'status'              => 'pending',
                'share_token_used'    => $token,
                'client_ip'           => $request->ip(),
                'created_at'          => date('Y-m-d H:i:s'),
                'updated_at'          => date('Y-m-d H:i:s'),
            ]);

            $this->logAudit('sourcing_change_request', 'sourcing_request_change_requests', $newId, [
                'sourcing_request_id' => $req->id,
                'ip'                  => $request->ip(),
            ], 'Client submitted change request');
        });

        return response()->json(['type' => 'success', 'id' => $newId]);
    }

    public function respondChangeRequest(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $v = $request->validate([
            'id'       => 'required|integer|exists:sourcing_request_change_requests,id',
            'response' => 'nullable|string|max:5000',
            'status'   => 'required|in:responded,dismissed',
        ]);

        $cr = DB::table('sourcing_request_change_requests')->where('id', $v['id'])->first();
        if (!$cr) abort(404);
        $req = DB::table('sourcing_requests')->where('id', $cr->sourcing_request_id)->first();
        $this->assertCanAccessClient($req->client_id);

        DB::table('sourcing_request_change_requests')->where('id', $v['id'])->update([
            'status'                => $v['status'],
            'response'              => $v['response'] ?? null,
            'responded_at'          => date('Y-m-d H:i:s'),
            'responded_by_user_id'  => auth()->user()->id,
            'updated_at'            => date('Y-m-d H:i:s'),
        ]);

        $this->logAudit('sourcing_change_request_respond', 'sourcing_request_change_requests', $v['id'], [
            'sourcing_request_id' => $cr->sourcing_request_id,
            'status'              => $v['status'],
        ], $v['status'] === 'responded' ? 'Responded to change request' : 'Dismissed change request');

        return response()->json(['type' => 'success']);
    }

    /* ------------------------------------------------------------
     *  Bulk PDF export — Phase 6
     *
     *  POST /sourcing/bulk-pdf  body: { ids: [1, 2, 3, ...] }
     *
     *  Renders each proforma into the same PDF, page-broken between them.
     *  Up to 25 proformas per call — past that the renderer slows enough
     *  that the operator should split the export.
     * ------------------------------------------------------------ */
    public function bulkPdf(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $v = $request->validate([
            'ids'   => 'required|array|min:1|max:25',
            'ids.*' => 'integer|exists:sourcing_requests,id',
        ]);

        $reqs = DB::table('sourcing_requests')->whereIn('id', $v['ids'])
            ->whereNull('deleted_at')
            ->orderBy('id')->get();

        // Branch scoping: drop any rows the user can't see (so a smuggled
        // id in the array can't leak another branch's proforma).
        $user = auth()->user();
        if ($user->type !== 'admin') {
            $reqs = $reqs->filter(fn($r) => (int) $r->branch_id === (int) $user->branch)->values();
        }
        if ($reqs->isEmpty()) {
            return response()->json(['type' => 'error', 'message' => 'No accessible proformas'], 422);
        }

        $isRtl = (auth()->user()->lang ?? 'en') === 'ar';
        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4',
            'default_font'   => 'dejavusans',
            'directionality' => $isRtl ? 'rtl' : 'ltr',
            'margin_top'     => 12, 'margin_bottom' => 12,
            'margin_left'    => 14, 'margin_right' => 14,
        ]);

        $first = true;
        foreach ($reqs as $req) {
            $this->assertCanAccessClient($req->client_id);
            $payload = $this->loadProformaForRender($req);
            $html = view('pages.sourcing.proforma_pdf', $payload)->render();
            if (!$first) {
                $mpdf->AddPage();
            }
            $mpdf->WriteHTML($html);
            $first = false;
        }

        $this->logAudit('sourcing_bulk_pdf', 'sourcing_requests', null, [
            'ids' => $reqs->pluck('id')->all(),
        ], 'Bulk PDF exported for ' . $reqs->count() . ' proformas');

        $filename = 'proformas-bundle-' . date('Ymd-His') . '.pdf';
        return response($mpdf->Output($filename, 'I'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /* ------------------------------------------------------------
     *  Proforma documents — Phase 6
     *
     *  Upload / list / delete / toggle visibility of files attached at
     *  the proforma level (contracts, certificates, packing lists, …).
     *  Separate from item photos.
     *
     *  POST /sourcing/documents/upload   (multipart: sourcing_request_id, files[], label?, visibility?)
     *  POST /sourcing/documents/delete   ({ id })
     *  POST /sourcing/documents/visibility ({ id, visibility })
     * ------------------------------------------------------------ */
    public function uploadDocuments(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $request->validate([
            'sourcing_request_id' => 'required|integer|exists:sourcing_requests,id',
            'files'               => 'required|array|min:1|max:10',
            'files.*'             => 'file|max:20480|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
            'label'               => 'nullable|string|max:191',
            'visibility'          => 'nullable|in:internal,client_visible',
        ]);

        $req = DB::table('sourcing_requests')->where('id', $request->sourcing_request_id)->first();
        $this->assertCanAccessClient($req->client_id);

        $uploaded = [];
        DB::transaction(function () use ($request, $req, &$uploaded) {
            foreach ($request->file('files') as $file) {
                $ext  = strtolower($file->getClientOriginalExtension() ?: 'bin');
                $name = bin2hex(random_bytes(8)) . '.' . $ext;
                $dir  = 'proforma/' . $req->id . '/docs';
                $rel  = $dir . '/' . $name;
                $file->storeAs('public/' . $dir, $name);

                $id = DB::table('sourcing_request_documents')->insertGetId([
                    'sourcing_request_id' => $req->id,
                    'path'                => $rel,
                    'original_name'       => mb_substr((string) $file->getClientOriginalName(), 0, 191),
                    'mime'                => $file->getClientMimeType(),
                    'size_bytes'          => $file->getSize(),
                    'label'               => $request->input('label') ? mb_substr($request->input('label'), 0, 191) : null,
                    'visibility'          => $request->input('visibility', 'internal'),
                    'uploaded_by'         => auth()->user()->id,
                    'created_at'          => date('Y-m-d H:i:s'),
                    'updated_at'          => date('Y-m-d H:i:s'),
                ]);
                $uploaded[] = ['id' => $id, 'path' => $rel];
            }
            $this->logAudit('sourcing_documents_upload', 'sourcing_request_documents', null, [
                'sourcing_request_id' => $req->id,
                'count'               => count($uploaded),
            ], 'Proforma documents uploaded');
        });

        return response()->json(['type' => 'success', 'uploaded' => $uploaded]);
    }

    public function deleteDocument(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $id  = (int) $request->id;
        $doc = DB::table('sourcing_request_documents')->where('id', $id)->first();
        if (!$doc) abort(404);

        $req = DB::table('sourcing_requests')->where('id', $doc->sourcing_request_id)->first();
        $this->assertCanAccessClient($req->client_id);

        $abs = storage_path('app/public/' . ltrim($doc->path, '/'));
        if (is_file($abs)) @unlink($abs);
        DB::table('sourcing_request_documents')->where('id', $id)->delete();

        $this->logAudit('sourcing_document_delete', 'sourcing_request_documents', $id, [
            'sourcing_request_id' => $doc->sourcing_request_id,
        ], 'Proforma document deleted');

        return response()->json(['type' => 'success']);
    }

    public function setDocumentVisibility(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $v = $request->validate([
            'id'         => 'required|integer|exists:sourcing_request_documents,id',
            'visibility' => 'required|in:internal,client_visible',
        ]);
        $doc = DB::table('sourcing_request_documents')->where('id', $v['id'])->first();
        $req = DB::table('sourcing_requests')->where('id', $doc->sourcing_request_id)->first();
        $this->assertCanAccessClient($req->client_id);

        DB::table('sourcing_request_documents')->where('id', $v['id'])->update([
            'visibility' => $v['visibility'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return response()->json(['type' => 'success']);
    }

    /* ------------------------------------------------------------
     *  Update per-item delivery status — Phase 5
     *
     *  POST /sourcing/items/status
     *  body: { id, delivery_status }
     *
     *  No accounting side-effects — this is operational tracking.
     *  Allowed values:
     *    pending → sourced → in_production → shipped → delivered
     *    (and 'pending' as a reset).
     * ------------------------------------------------------------ */
    public function updateItemStatus(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $v = $request->validate([
            'id'              => 'required|integer|exists:sourcing_request_items,id',
            'delivery_status' => 'required|in:pending,sourced,in_production,shipped,delivered',
        ]);

        $item = DB::table('sourcing_request_items')->where('id', $v['id'])->first();
        if (!$item) abort(404);
        $req = DB::table('sourcing_requests')->where('id', $item->sourcing_request_id)->first();
        $this->assertCanAccessClient($req->client_id);

        DB::table('sourcing_request_items')->where('id', $v['id'])->update([
            'delivery_status'   => $v['delivery_status'],
            'status_changed_at' => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $this->logAudit('sourcing_item_status', 'sourcing_request_items', $v['id'], [
            'sourcing_request_id' => $item->sourcing_request_id,
            'from'                => $item->delivery_status,
            'to'                  => $v['delivery_status'],
        ], 'Item delivery status: ' . $item->delivery_status . ' → ' . $v['delivery_status']);

        // Phase 7: auto-fulfill if this status change completes delivery
        // AND payments are all in. The helper is a no-op when conditions
        // aren't met, so it's cheap to always call.
        $autoFulfillId = $this->maybeAutoFulfill((int) $item->sourcing_request_id);

        return response()->json([
            'type' => 'success',
            'auto_fulfilled'       => (bool) $autoFulfillId,
            'margin_journal_id'    => $autoFulfillId,
        ]);
    }

    /* ------------------------------------------------------------
     *  Send reminder email — Phase 5
     *
     *  POST /sourcing/{id}/reminder
     *
     *  Like emailToClient() but uses a stock "friendly nudge" body and
     *  only works on proformas that have been sent but not approved /
     *  fulfilled. Reuses the existing email infra.
     * ------------------------------------------------------------ */
    public function sendReminder(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        if (!$req->sent_at || !$req->share_token) {
            return response()->json(['type' => 'error', 'message' => 'Proforma has not been sent yet'], 422);
        }
        if (in_array($req->status, ['accepted', 'fulfilled', 'canceled'], true)) {
            return response()->json(['type' => 'error', 'message' => 'Proforma is already ' . $req->status], 422);
        }

        $client = DB::table('clients')->where('id', $req->client_id)->first();
        $to = $request->get('to') ?: ($client->email ?? null);
        if (!$to) {
            return response()->json(['type' => 'error', 'message' => 'Client has no email on file'], 422);
        }

        $publicUrl = url('/proforma/' . $req->share_token);
        $clientName = $client->name ?? '';
        $company = (new settingsController())->get()['company_name'] ?? '';

        // Use the company-configured templates when present; the placeholder
        // substitution below handles {link} / {number} / {client} / {total} /
        // {company} the same way for any text.
        $settings = (new settingsController())->get();
        $subject = $request->get('subject') ?: ($settings['proforma_reminder_subject'] ?: 'Friendly reminder — Proforma {number}');
        $body    = $request->get('body')    ?: ($settings['proforma_reminder_body']    ?: "Dear {client},\n\nJust a friendly reminder that proforma {number} is awaiting your review:\n\n{link}\n\nThank you,\n{company}");

        $subject = str_replace(['{link}','{number}','{client}','{total}','{company}'], [
            $publicUrl, $req->request_number, $clientName,
            number_format((float) $req->proforma_total, 2) . ' ' . strtoupper($req->display_currency ?: $req->currency ?: 'usd'),
            $company,
        ], $subject);
        $body = str_replace(['{link}','{number}','{client}','{total}','{company}'], [
            $publicUrl, $req->request_number, $clientName,
            number_format((float) $req->proforma_total, 2) . ' ' . strtoupper($req->display_currency ?: $req->currency ?: 'usd'),
            $company,
        ], $body);

        try {
            Mail::send([], [], function ($message) use ($to, $subject, $body) {
                $message->to($to)->subject($subject)->text($body);
            });
        } catch (\Throwable $e) {
            Log::error('proforma reminder failed: ' . $e->getMessage(), ['sourcing_request_id' => $id]);
            return response()->json(['type' => 'error', 'message' => 'Email send failed: ' . $e->getMessage()], 500);
        }

        $this->logAudit('sourcing_reminder_sent', 'sourcing_requests', $id, [
            'to' => $to, 'subject' => $subject,
        ], 'Reminder emailed to client');

        return response()->json(['type' => 'success']);
    }

    /* ------------------------------------------------------------
     *  Clone a proforma — Phase 5
     *
     *  POST /sourcing/{id}/clone
     *
     *  Duplicates the proforma's client + branch + items + photos + the
     *  settings (display_currency, commission_mode, terms_text) as a new
     *  draft. Skips: share_token, sent_at, approved_*, payments, freight
     *  links, commission_journal_entry_id — these all belong to the
     *  original transaction's lifecycle and don't carry over.
     *
     *  Item photos are duplicated by copying files on disk so editing
     *  one set never affects the other.
     * ------------------------------------------------------------ */
    public function cloneProforma(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $this->assertPeriodOpen(date('Y-m-d'));

        $source = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$source) abort(404);
        $this->assertCanAccessClient($source->client_id);

        $newId = null;
        DB::transaction(function () use ($source, &$newId) {
            $nextNum = DB::table('sourcing_requests')->max('id') + 1;
            $requestNumber = 'SRC-' . date('Ymd') . '-' . str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);

            $newId = DB::table('sourcing_requests')->insertGetId([
                'request_number'      => $requestNumber,
                'client_id'           => $source->client_id,
                'branch_id'           => $source->branch_id,
                'title'               => '[CLONE] ' . $source->title,
                'description'         => $source->description,
                'target_quantity'     => $source->target_quantity,
                'target_unit'         => $source->target_unit,
                'target_unit_price'   => $source->target_unit_price,
                'currency'            => $source->currency,
                'display_currency'    => $source->display_currency,
                'commission_mode'     => $source->commission_mode,
                'commission_amount'   => $source->commission_amount,
                'commission_currency' => $source->commission_currency,
                'terms_text'          => $source->terms_text,
                'status'              => 'open',
                'created_by'          => auth()->user()->id,
                'created_at'          => date('Y-m-d H:i:s'),
                'updated_at'          => date('Y-m-d H:i:s'),
                'items_subtotal'      => 0, // recomputed after items copy
                'proforma_total'      => 0,
            ]);

            // Copy items
            $items = DB::table('sourcing_request_items')
                ->where('sourcing_request_id', $source->id)
                ->orderBy('sort_order')->orderBy('id')->get();
            $itemMap = [];
            foreach ($items as $it) {
                $newItemId = DB::table('sourcing_request_items')->insertGetId([
                    'sourcing_request_id'   => $newId,
                    'name'                  => $it->name,
                    'code'                  => $it->code,
                    'description'           => $it->description,
                    'quantity'              => $it->quantity,
                    'unit'                  => $it->unit,
                    'unit_cost'             => $it->unit_cost,
                    'unit_cost_currency'    => $it->unit_cost_currency,
                    'unit_price_to_client'  => $it->unit_price_to_client,
                    'weight_kg'             => $it->weight_kg,
                    'cbm'                   => $it->cbm,
                    'sort_order'            => $it->sort_order,
                    'created_by'            => auth()->user()->id,
                    'created_at'            => date('Y-m-d H:i:s'),
                    'updated_at'            => date('Y-m-d H:i:s'),
                ]);
                $itemMap[$it->id] = $newItemId;
            }

            // Copy photos (file + row). Skip silently when the source file
            // is missing — the row would point at nothing useful.
            foreach ($itemMap as $oldItemId => $newItemId) {
                $photos = DB::table('sourcing_request_item_photos')
                    ->where('item_id', $oldItemId)->get();
                foreach ($photos as $p) {
                    $srcAbs = storage_path('app/public/' . ltrim($p->path, '/'));
                    if (!is_file($srcAbs)) continue;
                    $ext = pathinfo($p->path, PATHINFO_EXTENSION) ?: 'jpg';
                    $name = bin2hex(random_bytes(8)) . '.' . $ext;
                    $relDir = 'proforma/' . $newId . '/' . $newItemId;
                    $absDir = storage_path('app/public/' . $relDir);
                    if (!is_dir($absDir)) @mkdir($absDir, 0775, true);
                    if (@copy($srcAbs, $absDir . '/' . $name)) {
                        DB::table('sourcing_request_item_photos')->insert([
                            'item_id'       => $newItemId,
                            'path'          => $relDir . '/' . $name,
                            'original_name' => $p->original_name,
                            'size_bytes'    => $p->size_bytes,
                            'mime'          => $p->mime,
                            'is_primary'    => $p->is_primary,
                            'sort_order'    => $p->sort_order,
                            'uploaded_by'   => auth()->user()->id,
                            'created_at'    => date('Y-m-d H:i:s'),
                            'updated_at'    => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }

            $this->recomputeProformaTotals($newId);

            // Phase 13: initial snapshot so the clone has version history
            // from day one. Label records the provenance.
            $this->snapshotProforma($newId, 'cloned_from', 'Cloned from ' . $source->request_number);

            $this->logAudit('sourcing_clone', 'sourcing_requests', $newId, [
                'cloned_from_id'     => $source->id,
                'cloned_from_number' => $source->request_number,
                'items_copied'       => count($itemMap),
            ], 'Cloned from ' . $source->request_number);
        });

        return response()->json([
            'type'     => 'success',
            'id'       => $newId,
            'redirect' => '/sourcing/' . $newId,
        ]);
    }

    /* ------------------------------------------------------------
     *  Sourcing analytics dashboard — Phase 5
     *
     *  GET /sourcing/dashboard?from=YYYY-MM-DD&to=YYYY-MM-DD
     *
     *  The owner's at-a-glance view: pipeline value, conversion rate
     *  (sent → approved), revenue recognised in period, top clients,
     *  branch breakdown, and a recent activity feed.
     * ------------------------------------------------------------ */
    public function dashboard(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $from = $request->get('from', date('Y-m-01'));
        $to   = $request->get('to',   date('Y-m-t'));

        // Branch admins are locked to their branch. Admins can optionally
        // scope the dashboard to a single branch via ?branch=N — when
        // omitted they see the whole company.
        $user = auth()->user();
        if ($user->type === 'admin') {
            $branchFilter = $request->get('branch');
            $branchFilter = is_numeric($branchFilter) && (int) $branchFilter > 0 ? (int) $branchFilter : null;
        } else {
            $branchFilter = (int) $user->branch;
        }

        $base = DB::table('sourcing_requests')->whereNull('deleted_at');
        if ($branchFilter !== null) $base->where('branch_id', $branchFilter);

        // ---- KPI counters ----
        $created = (clone $base)->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
        $createdCount = (clone $created)->count();

        $sent = (clone $base)->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
        $sentCount = (clone $sent)->count();

        $approved = (clone $base)->whereNotNull('approved_at')
            ->whereBetween('approved_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
        $approvedCount = (clone $approved)->count();

        $fulfilled = (clone $base)->where('status', 'fulfilled')
            ->whereBetween('updated_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
        $fulfilledCount = (clone $fulfilled)->count();

        // Conversion ratio over the same period
        $conversion = $sentCount > 0 ? round(100 * $approvedCount / $sentCount, 1) : 0.0;

        // Pipeline value: USD-equivalent of every accepted-but-not-fulfilled
        // proforma's total. Naive conversion using LIVE rates — for the
        // dashboard view this is fine; per-proforma reports use the frozen
        // snapshot.
        $dc = new dataController();
        $rates = $dc->currency_exchange_rates;
        $pipelineRows = (clone $base)
            ->whereIn('status', ['quoted', 'accepted'])
            ->select('display_currency', 'currency', 'proforma_total')
            ->get();
        $pipelineUsd = 0.0;
        foreach ($pipelineRows as $r) {
            $ccy = strtolower((string) ($r->display_currency ?: $r->currency ?: 'usd'));
            $amt = (float) $r->proforma_total;
            $pipelineUsd += $ccy === 'usd' ? $amt : (($rates[$ccy] ?? 0) > 0 ? $amt / (float) $rates[$ccy] : 0);
        }

        // Revenue recognised in period — sum of 4020 credits cost-objected
        // to sourcing_request, with entry_date in window. This IS the right
        // way to measure "we earned this": straight from the ledger.
        $revenueRows = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.entry_id')
            ->where('jl.account_code', '4020')
            ->where('jl.cost_object_type', 'sourcing_request')
            ->whereBetween('je.entry_date', [$from, $to])
            ->where('je.status', 'open')
            ->select('jl.currency', DB::raw('SUM(jl.cr - jl.dr) as total'))
            ->groupBy('jl.currency')->get();
        $revenueByCcy = [];
        foreach ($revenueRows as $r) $revenueByCcy[strtoupper($r->currency)] = (float) $r->total;

        // ---- Top clients ----
        $topClients = (clone $base)
            ->leftJoin('clients as c', 'c.id', '=', 'sourcing_requests.client_id')
            ->whereIn('sourcing_requests.status', ['quoted', 'accepted', 'fulfilled'])
            ->whereBetween('sourcing_requests.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->select(
                'c.id', 'c.name', 'c.code',
                DB::raw('COUNT(*) as proforma_count'),
                DB::raw('SUM(sourcing_requests.proforma_total) as total_value')
            )
            ->groupBy('c.id', 'c.name', 'c.code')
            ->orderByDesc('total_value')
            ->limit(10)->get();

        // ---- Per-branch breakdown (admin only, and only when not already
        // scoped to a single branch — showing one row would be silly). ----
        $branchBreakdown = [];
        if ($user->type === 'admin' && $branchFilter === null) {
            $branchBreakdown = DB::table('sourcing_requests as sr')
                ->leftJoin('branches as b', 'b.id', '=', 'sr.branch_id')
                ->whereBetween('sr.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->select(
                    'b.id', 'b.name',
                    DB::raw("SUM(CASE WHEN sr.status='quoted' THEN 1 ELSE 0 END) as quoted_n"),
                    DB::raw("SUM(CASE WHEN sr.status='accepted' THEN 1 ELSE 0 END) as accepted_n"),
                    DB::raw("SUM(CASE WHEN sr.status='fulfilled' THEN 1 ELSE 0 END) as fulfilled_n"),
                    DB::raw("SUM(sr.proforma_total) as total_value")
                )
                ->groupBy('b.id', 'b.name')
                ->orderByDesc('total_value')->get();
        }

        // Branches available for the filter dropdown.
        $allBranches = $user->type === 'admin'
            ? DB::table('branches')->where('deleted', 'false')->orderBy('id')->get(['id', 'name'])
            : collect();

        // Phase 12 — at-risk slice: items with a promised_delivery_date
        // that's either already past (and not delivered) OR confirmed by
        // the supplier for a later date than promised.
        $today = date('Y-m-d');
        $atRiskItems = DB::table('sourcing_request_items as i')
            ->join('sourcing_requests as sr', 'sr.id', '=', 'i.sourcing_request_id')
            ->leftJoin('clients as c', 'c.id', '=', 'sr.client_id')
            ->whereNull('sr.deleted_at')
            ->whereNotIn('sr.status', ['canceled', 'fulfilled'])
            ->whereNotNull('i.promised_delivery_date')
            ->where(function ($w) use ($today) {
                $w->where(function ($ww) use ($today) {
                    $ww->whereNotIn('i.delivery_status', ['delivered'])
                       ->where('i.promised_delivery_date', '<', $today);
                })
                ->orWhereRaw('i.supplier_confirmed_date IS NOT NULL AND i.supplier_confirmed_date > i.promised_delivery_date');
            });
        if ($branchFilter !== null) $atRiskItems->where('sr.branch_id', $branchFilter);

        $atRiskCount = (clone $atRiskItems)->count();
        $atRiskTop = (clone $atRiskItems)
            ->select(
                'sr.id', 'sr.request_number', 'sr.title',
                'c.name as client_name',
                'i.name as item_name',
                'i.promised_delivery_date',
                'i.supplier_confirmed_date',
                'i.delivery_status'
            )
            ->orderBy('i.promised_delivery_date')
            ->limit(5)->get();

        // ---- Recent activity ----
        $recent = DB::table('audit_log')
            ->leftJoin('users as u', 'u.id', '=', 'audit_log.user_id')
            ->whereIn('audit_log.action', [
                'sourcing_create', 'sourcing_proforma_send',
                'sourcing_proforma_approve', 'sourcing_installment_paid',
                'sourcing_fulfill', 'sourcing_freight_handoff',
                'sourcing_client_viewed',
            ])
            ->orderByDesc('audit_log.created_at')
            ->select('audit_log.*', 'u.name as user_name')
            ->limit(25)->get();

        // Resolve recent target_ids → proforma request_numbers for the feed.
        $reqIds = $recent->pluck('target_id')->filter()->unique()->all();
        $reqLookup = empty($reqIds) ? [] : DB::table('sourcing_requests')
            ->whereIn('id', $reqIds)
            ->select('id', 'request_number', 'client_id')
            ->get()->keyBy('id');

        $lang = new langController();
        return view('pages.sourcing.dashboard', [
            'from'             => $from,
            'to'               => $to,
            'createdCount'     => $createdCount,
            'sentCount'        => $sentCount,
            'approvedCount'    => $approvedCount,
            'fulfilledCount'   => $fulfilledCount,
            'conversion'       => $conversion,
            'pipelineUsd'      => $pipelineUsd,
            'revenueByCcy'     => $revenueByCcy,
            'topClients'       => $topClients,
            'branchBreakdown'  => $branchBreakdown,
            'recent'           => $recent,
            'reqLookup'        => $reqLookup,
            'allBranches'      => $allBranches,
            'branchFilter'     => $branchFilter,
            'atRiskCount'      => $atRiskCount,
            'atRiskTop'        => $atRiskTop,
            'today'            => $today,
            'healthWatch'      => $this->loadHealthWatch($branchFilter),
            'lang'             => $lang,
            'data'             => $dc,
            'section'          => 'sourcing',
            'page'             => 'sourcing_dashboard',
        ]);
    }

    /* ============================================================
     *  HEALTH WATCH — Phase 15
     *
     *  Closes the loop on Phase 14 (smart insights): instead of waiting
     *  for the operator to open each proforma, surface the worst-health
     *  active deals on the dashboard so the operator can act first.
     *
     *  Two surfaces:
     *    - dashboard widget: top-5 lowest-score active proformas via
     *      a batch query (no per-proforma child-table loads).
     *    - daily snapshot artisan command (sourcing:health-snapshot)
     *      writes one row per (proforma, day) into
     *      sourcing_deal_health_snapshots — gives a 14-day trend chart
     *      on the proforma show page.
     * ============================================================ */

    /**
     * Compute a lightweight health brief for every active proforma in
     * scope, then return the 5 worst plus the count of "needs attention"
     * (score < 60). Pre-aggregates child tables in 3 SQL passes so we
     * never N+1 across the list.
     *
     * Returns ['count' => int, 'rows' => [{id, request_number, title,
     *   client_name, status, score, factors[]}, ...]].
     */
    private function loadHealthWatch(?int $branchFilter): array
    {
        $base = DB::table('sourcing_requests as sr')
            ->whereNull('sr.deleted_at')
            ->whereIn('sr.status', ['open', 'searching', 'quoted', 'accepted']);
        if ($branchFilter !== null) $base->where('sr.branch_id', $branchFilter);

        $proformas = (clone $base)
            ->leftJoin('clients as c', 'c.id', '=', 'sr.client_id')
            ->select(
                'sr.id', 'sr.request_number', 'sr.title', 'sr.status',
                'sr.sent_at', 'sr.client_view_count',
                'c.name as client_name'
            )
            ->get();
        if ($proformas->isEmpty()) return ['count' => 0, 'rows' => []];

        $ids = $proformas->pluck('id')->all();

        // Pre-aggregate the 3 child collections we need for scoring.
        $payAgg = DB::table('sourcing_request_payments')
            ->whereIn('sourcing_request_id', $ids)
            ->select(
                'sourcing_request_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) as paid"),
                DB::raw("SUM(CASE WHEN status IN ('scheduled','partial') AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue")
            )
            ->groupBy('sourcing_request_id')
            ->get()->keyBy('sourcing_request_id');

        $itemAgg = DB::table('sourcing_request_items')
            ->whereIn('sourcing_request_id', $ids)
            ->select(
                'sourcing_request_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN delivery_status='delivered' THEN 1 ELSE 0 END) as delivered"),
                DB::raw("SUM(
                    CASE
                        WHEN supplier_confirmed_date IS NOT NULL AND supplier_confirmed_date > promised_delivery_date THEN 1
                        WHEN supplier_confirmed_date IS NULL AND promised_delivery_date IS NOT NULL
                             AND promised_delivery_date < CURDATE()
                             AND delivery_status IN ('pending','sourced','in_production') THEN 1
                        ELSE 0
                    END
                ) as slipping")
            )
            ->groupBy('sourcing_request_id')
            ->get()->keyBy('sourcing_request_id');

        $crAgg = DB::table('sourcing_request_change_requests')
            ->whereIn('sourcing_request_id', $ids)
            ->select(
                'sourcing_request_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending")
            )
            ->groupBy('sourcing_request_id')
            ->get()->keyBy('sourcing_request_id');

        $scored = $proformas->map(function ($p) use ($payAgg, $itemAgg, $crAgg) {
            $brief = $this->computeHealthBrief(
                $p,
                $payAgg->get($p->id),
                $itemAgg->get($p->id),
                $crAgg->get($p->id),
            );
            $p->score   = $brief['score'];
            $p->factors = $brief['factors'];
            return $p;
        })->sortBy('score')->values();

        $needsAttention = $scored->filter(fn ($r) => $r->score < 60)->count();

        return [
            'count' => $needsAttention,
            'rows'  => $scored->take(5)->values()->all(),
        ];
    }

    /**
     * Pure scoring on pre-aggregated inputs — no DB calls so it can be
     * reused by the batch dashboard path AND the artisan snapshot job.
     * Mirrors the buckets used by computeDealHealth() so the score the
     * operator sees on the show page matches what's on the dashboard.
     */
    private function computeHealthBrief($req, $pay, $items, $cr): array
    {
        $factors = [];
        $score = 0;

        // (1) Status currency
        if ($req->status === 'accepted') {
            $score += 18; $factors[] = ['label' => 'Accepted', 'points' => 18];
        } elseif ($req->status === 'quoted' && $req->sent_at) {
            $daysSent = (time() - strtotime($req->sent_at)) / 86400;
            $bucket = $daysSent <= 3 ? 14 : ($daysSent <= 7 ? 10 : ($daysSent <= 14 ? 5 : 0));
            $score += $bucket;
            $factors[] = ['label' => 'Sent', 'points' => $bucket, 'note' => round($daysSent) . 'd ago'];
        } else {
            $score += 5; $factors[] = ['label' => 'Draft', 'points' => 5];
        }

        // (2) Client engagement
        $views = (int) ($req->client_view_count ?? 0);
        if ($views > 0) {
            $vBucket = min(25, 8 + 3 * min(5, $views));
            $score += $vBucket;
            $factors[] = ['label' => 'Viewed', 'points' => $vBucket, 'note' => $views . 'x'];
        } else {
            $factors[] = ['label' => 'No views', 'points' => 0];
        }

        // (3) Negotiation hygiene
        if ($cr && (int) $cr->total > 0) {
            $pending = (int) $cr->pending;
            $hygiene = $pending === 0 ? 12 : max(0, 12 - ($pending * 6));
            $score += $hygiene;
            $factors[] = ['label' => 'Change reqs', 'points' => $hygiene, 'note' => $pending . ' pending'];
        } else {
            $score += 15;
            $factors[] = ['label' => 'Frictionless', 'points' => 15];
        }

        // (4) Payments
        if ($pay && (int) $pay->total > 0) {
            $total   = (int) $pay->total;
            $paid    = (int) $pay->paid;
            $overdue = (int) $pay->overdue;
            $payScore = max(0, min(20, (int) round(($paid / $total) * 20) - ($overdue * 4)));
            $score += $payScore;
            $factors[] = [
                'label' => 'Payments',
                'points' => $payScore,
                'note' => $paid . '/' . $total . ($overdue > 0 ? ' (' . $overdue . ' overdue)' : ''),
            ];
        } else {
            $factors[] = ['label' => 'No plan', 'points' => 0];
        }

        // (5) Delivery
        if ($items && (int) $items->total > 0) {
            $total     = (int) $items->total;
            $delivered = (int) $items->delivered;
            $slipping  = (int) $items->slipping;
            $deliveryScore = max(0, min(20, (int) round(($delivered / $total) * 20) - ($slipping * 4)));
            $score += $deliveryScore;
            $factors[] = [
                'label' => 'Delivery',
                'points' => $deliveryScore,
                'note' => $delivered . '/' . $total . ($slipping > 0 ? ' (' . $slipping . ' slipping)' : ''),
            ];
        } else {
            $factors[] = ['label' => 'No items', 'points' => 0];
        }

        return ['score' => max(0, min(100, $score)), 'factors' => $factors];
    }

    /**
     * Walk every active proforma, compute the brief, UPSERT one row per
     * (proforma, today) into sourcing_deal_health_snapshots. Called by
     * the `sourcing:health-snapshot` artisan command on a daily cron.
     *
     * Returns ['scanned' => int, 'snapshotted' => int, 'failed' => int].
     */
    public function runHealthSnapshotImpl(int $retainDays = 60): array
    {
        $today = date('Y-m-d');
        $stats = ['scanned' => 0, 'snapshotted' => 0, 'failed' => 0];

        // Walk the whole company — no branch filter at the cron level.
        $brief = $this->loadHealthWatchAll();
        $stats['scanned'] = count($brief);

        foreach ($brief as $row) {
            try {
                DB::table('sourcing_deal_health_snapshots')->upsert(
                    [[
                        'sourcing_request_id' => $row['id'],
                        'snapshot_date'       => $today,
                        'score'               => $row['score'],
                        'factors'             => json_encode($row['factors']),
                        'computed_at'         => date('Y-m-d H:i:s'),
                    ]],
                    ['sourcing_request_id', 'snapshot_date'],
                    ['score', 'factors', 'computed_at']
                );
                $stats['snapshotted']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                \Log::warning('sourcing:health-snapshot upsert failed', [
                    'sourcing_request_id' => $row['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Retention — keep ~60 days by default. Cheap delete since the
        // table has a snapshot_date index.
        DB::table('sourcing_deal_health_snapshots')
            ->where('snapshot_date', '<', date('Y-m-d', strtotime("-{$retainDays} days")))
            ->delete();

        return $stats;
    }

    /**
     * Same shape as loadHealthWatch() but unfiltered + returns the full
     * scored set. Used by the snapshot cron.
     */
    private function loadHealthWatchAll(): array
    {
        $proformas = DB::table('sourcing_requests as sr')
            ->whereNull('sr.deleted_at')
            ->whereIn('sr.status', ['open', 'searching', 'quoted', 'accepted'])
            ->select('sr.id', 'sr.request_number', 'sr.title', 'sr.status',
                     'sr.sent_at', 'sr.client_view_count')
            ->get();
        if ($proformas->isEmpty()) return [];

        $ids = $proformas->pluck('id')->all();
        $payAgg = DB::table('sourcing_request_payments')
            ->whereIn('sourcing_request_id', $ids)
            ->select(
                'sourcing_request_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) as paid"),
                DB::raw("SUM(CASE WHEN status IN ('scheduled','partial') AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue")
            )->groupBy('sourcing_request_id')->get()->keyBy('sourcing_request_id');
        $itemAgg = DB::table('sourcing_request_items')
            ->whereIn('sourcing_request_id', $ids)
            ->select(
                'sourcing_request_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN delivery_status='delivered' THEN 1 ELSE 0 END) as delivered"),
                DB::raw("SUM(
                    CASE
                        WHEN supplier_confirmed_date IS NOT NULL AND supplier_confirmed_date > promised_delivery_date THEN 1
                        WHEN supplier_confirmed_date IS NULL AND promised_delivery_date IS NOT NULL
                             AND promised_delivery_date < CURDATE()
                             AND delivery_status IN ('pending','sourced','in_production') THEN 1
                        ELSE 0
                    END
                ) as slipping")
            )->groupBy('sourcing_request_id')->get()->keyBy('sourcing_request_id');
        $crAgg = DB::table('sourcing_request_change_requests')
            ->whereIn('sourcing_request_id', $ids)
            ->select(
                'sourcing_request_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending")
            )->groupBy('sourcing_request_id')->get()->keyBy('sourcing_request_id');

        return $proformas->map(function ($p) use ($payAgg, $itemAgg, $crAgg) {
            $brief = $this->computeHealthBrief(
                $p,
                $payAgg->get($p->id),
                $itemAgg->get($p->id),
                $crAgg->get($p->id),
            );
            return [
                'id'      => (int) $p->id,
                'score'   => $brief['score'],
                'factors' => $brief['factors'],
            ];
        })->all();
    }

    /**
     * 14-day health trend for a single proforma. Returns rows sorted by
     * snapshot_date ASC so the chart renders left→right. Used by the
     * proforma show page.
     */
    public function healthTrend(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        $rows = DB::table('sourcing_deal_health_snapshots')
            ->where('sourcing_request_id', $id)
            ->where('snapshot_date', '>=', date('Y-m-d', strtotime('-14 days')))
            ->orderBy('snapshot_date')
            ->select('snapshot_date', 'score')
            ->get();

        return response()->json([
            'type'   => 'success',
            'series' => $rows->map(fn ($r) => [
                'date'  => (string) $r->snapshot_date,
                'score' => (int) $r->score,
            ])->all(),
        ]);
    }

    /* ------------------------------------------------------------
     *  Email the proforma to the client — Phase 4
     *
     *  POST /sourcing/{id}/email
     *
     *  Sends a short message containing the public share link, with the
     *  rendered PDF attached. The operator can override the subject /
     *  body in the modal before sending. If the proforma has no share
     *  token yet (never sent), we mint one transparently so the link in
     *  the email is live.
     * ------------------------------------------------------------ */
    public function emailToClient(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        $v = $request->validate([
            'to'      => 'required|email',
            'cc'      => 'nullable|string|max:500',
            'subject' => 'required|string|max:191',
            'body'    => 'required|string|max:5000',
            'attach_pdf' => 'nullable|boolean',
        ]);

        // Ensure a share token exists so the link in the email is live.
        // Defer sent_at to the actual send time so a draft sent twice
        // doesn't keep bumping the timestamp.
        $token = $req->share_token;
        if (!$token) {
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            DB::table('sourcing_requests')->where('id', $id)->update([
                'share_token'            => $token,
                'share_token_expires_at' => date('Y-m-d H:i:s', strtotime('+90 days')),
                'sent_at'                => $req->sent_at ?: date('Y-m-d H:i:s'),
                'updated_at'             => date('Y-m-d H:i:s'),
            ]);
            if (in_array($req->status, ['open', 'searching'], true)) {
                DB::table('sourcing_requests')->where('id', $id)->update(['status' => 'quoted']);
            }
            $req = DB::table('sourcing_requests')->where('id', $id)->first();
        }

        $publicUrl = url('/proforma/' . $token);

        // Substitute placeholders the operator may have left in the body.
        $finalBody = str_replace(
            ['{link}', '{number}', '{client}', '{total}', '{company}'],
            [
                $publicUrl,
                $req->request_number,
                (DB::table('clients')->where('id', $req->client_id)->value('name') ?? ''),
                number_format((float) $req->proforma_total, 2) . ' ' . strtoupper($req->display_currency ?: $req->currency ?: 'usd'),
                (new settingsController())->get()['company_name'] ?? '',
            ],
            $v['body']
        );

        // Build PDF to a temp file when attach_pdf is requested. Keeping
        // it on disk briefly is simpler than passing bytes through the
        // mailer's attachData() and lets the OS clean up tail-end errors.
        $tmpPdfPath = null;
        if (!empty($v['attach_pdf'])) {
            $payload = $this->loadProformaForRender($req);
            $html = view('pages.sourcing.proforma_pdf', $payload)->render();
            $mpdf = new Mpdf([
                'mode'           => 'utf-8',
                'format'         => 'A4',
                'default_font'   => 'dejavusans',
                'margin_top'     => 12, 'margin_bottom' => 12,
                'margin_left'    => 14, 'margin_right' => 14,
            ]);
            $mpdf->WriteHTML($html);
            $tmpPdfPath = tempnam(sys_get_temp_dir(), 'proforma_');
            $mpdf->Output($tmpPdfPath, 'F');
        }

        $ccList = array_filter(array_map('trim', preg_split('/[,;]/', (string) ($v['cc'] ?? ''))));

        try {
            Mail::send([], [], function ($message) use ($v, $finalBody, $ccList, $tmpPdfPath, $req) {
                $message->to($v['to'])
                        ->subject($v['subject'])
                        ->text($finalBody);
                foreach ($ccList as $cc) {
                    if (filter_var($cc, FILTER_VALIDATE_EMAIL)) $message->cc($cc);
                }
                if ($tmpPdfPath) {
                    $message->attach($tmpPdfPath, [
                        'as'   => 'proforma-' . $req->request_number . '.pdf',
                        'mime' => 'application/pdf',
                    ]);
                }
            });
        } catch (\Throwable $e) {
            if ($tmpPdfPath && is_file($tmpPdfPath)) @unlink($tmpPdfPath);
            Log::error('proforma email failed: ' . $e->getMessage(), [
                'sourcing_request_id' => $id,
                'to' => $v['to'],
            ]);
            return response()->json([
                'type'    => 'error',
                'message' => 'Email send failed: ' . $e->getMessage(),
            ], 500);
        }
        if ($tmpPdfPath && is_file($tmpPdfPath)) @unlink($tmpPdfPath);

        $this->logAudit('sourcing_email_sent', 'sourcing_requests', $id, [
            'to'        => $v['to'],
            'cc'        => $ccList,
            'subject'   => $v['subject'],
            'attached'  => !empty($v['attach_pdf']),
        ], 'Proforma emailed to client');

        return response()->json(['type' => 'success', 'public_url' => $publicUrl]);
    }

    /* ------------------------------------------------------------
     *  Per-proforma profit dashboard — Phase 4
     *
     *  GET /sourcing/{id}/profit
     *
     *  Combines two slices of the ledger:
     *    - sourcing-side: every journal_line tagged cost_object_type=
     *      'sourcing_request' and cost_object_id={id} (sourcing margin
     *      recognised on fulfill + any payments routed via the proforma)
     *    - freight-side: every journal_line tagged cost_object_type=
     *      'container_sky'|'container_sea' and cost_object_id={linked
     *      container id} (freight revenue from client_withd, supplier
     *      and broker payments tagged to this container)
     *
     *  Each slice is bucketed by account type (revenue / expense /
     *  asset-liability), and a "cash settled" figure pulled from the
     *  1000 cash credits gives the operator the real-cash answer to
     *  "did this proforma actually make money?".
     * ------------------------------------------------------------ */
    public function profitDashboard($id)
    {
        $this->requireAdminOrBranchAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        $client = DB::table('clients')->where('id', $req->client_id)->first();

        $journal = new journalController();
        $currencies = ['usd', 'eur', 'den', 'cny'];

        // Sourcing-side: cost_object_type='sourcing_request', id=$id
        $sourcingActivity = $journal->activityForCostObject('sourcing_request', (int) $id);
        $sourcing = $this->bucketize($sourcingActivity, $currencies);

        // Freight-side: linked container, if any.
        $freight = ['revenue' => [], 'expense' => [], 'other' => [],
                    'totals'  => ['revenue' => array_fill_keys($currencies, 0.0),
                                  'expense' => array_fill_keys($currencies, 0.0),
                                  'cash_outflow' => array_fill_keys($currencies, 0.0)]];
        $linkedContainer = null;
        if ($req->freight_container_id && $req->freight_kind) {
            $costObjKey = 'container_' . $req->freight_kind;
            $freightActivity = $journal->activityForCostObject($costObjKey, (int) $req->freight_container_id);
            $freight = $this->bucketize($freightActivity, $currencies);
            $table = $req->freight_kind === 'sky' ? 'containers_sky' : 'containers_sea';
            $linkedContainer = DB::table($table)->where('id', $req->freight_container_id)->first();
        }

        // Grand totals — straight per-currency sum.
        $grand = [
            'revenue'      => array_fill_keys($currencies, 0.0),
            'expense'      => array_fill_keys($currencies, 0.0),
            'cash_outflow' => array_fill_keys($currencies, 0.0),
            'net'          => array_fill_keys($currencies, 0.0),
        ];
        foreach ($currencies as $c) {
            $grand['revenue'][$c]      = $sourcing['totals']['revenue'][$c]      + $freight['totals']['revenue'][$c];
            $grand['expense'][$c]      = $sourcing['totals']['expense'][$c]      + $freight['totals']['expense'][$c];
            $grand['cash_outflow'][$c] = $sourcing['totals']['cash_outflow'][$c] + $freight['totals']['cash_outflow'][$c];
            $grand['net'][$c]          = $grand['revenue'][$c] - $grand['expense'][$c];
        }

        $lang = new langController();
        $data = new dataController();
        return view('pages.sourcing.profit', [
            'req'              => $req,
            'client'           => $client,
            'sourcing'         => $sourcing,
            'freight'          => $freight,
            'linkedContainer'  => $linkedContainer,
            'grand'            => $grand,
            'currencies'       => $currencies,
            'lang'             => $lang,
            'data'             => $data,
            'section'          => 'sourcing',
            'page'             => 'sourcing',
        ]);
    }

    /**
     * Turn the raw activity map ({code => {ccy => signed_net}}) into
     * revenue / expense / other buckets with totals. Same shape as
     * profitsController::container() so the view can render either.
     */
    private function bucketize(array $activity, array $currencies): array
    {
        $codes = array_keys($activity);
        $accounts = empty($codes) ? collect() : DB::table('chart_of_accounts')
            ->whereIn('code', $codes)->get()->keyBy('code');

        $revenue = []; $expense = []; $other = [];
        $totals  = [
            'revenue'      => array_fill_keys($currencies, 0.0),
            'expense'      => array_fill_keys($currencies, 0.0),
            'cash_outflow' => array_fill_keys($currencies, 0.0),
        ];

        foreach ($activity as $code => $amounts) {
            $a = $accounts->get($code);
            if (!$a) continue;
            $sign = $a->normal_balance === 'debit' ? 1 : -1;
            $natural = array_fill_keys($currencies, 0.0);
            foreach ($currencies as $c) {
                $natural[$c] = $sign * (float) ($amounts[$c] ?? 0);
            }
            $row = ['code' => $a->code, 'name' => $a->name, 'amounts' => $natural];
            if ($a->type === 'revenue') {
                $revenue[] = $row;
                foreach ($currencies as $c) $totals['revenue'][$c] += $natural[$c];
            } elseif ($a->type === 'expense') {
                $expense[] = $row;
                foreach ($currencies as $c) $totals['expense'][$c] += $natural[$c];
            } else {
                $other[] = $row + ['acct_type' => $a->type];
            }
            if ($a->code === '1000') {
                foreach ($currencies as $c) {
                    $totals['cash_outflow'][$c] += -1 * $natural[$c];
                }
            }
        }
        return ['revenue' => $revenue, 'expense' => $expense, 'other' => $other, 'totals' => $totals];
    }

    /* ------------------------------------------------------------
     *  Freight handoff — Phase 3
     *
     *  Proforma → container in one form. The proforma supplies client +
     *  items + weights; the operator adds AWB/B/L, carrier, branch,
     *  freight cost + client price for shipping. Creates a row in
     *  containers_sky / containers_sea linked back to the proforma so
     *  per-proforma reports can show the shipping side too.
     *
     *  GET /sourcing/{id}/handoff/{kind}  → form
     *  POST /sourcing/{id}/handoff        → create container + link
     * ------------------------------------------------------------ */
    public function handoffForm($id, $kind)
    {
        $this->requireAdminOrBranchAdmin();
        if (!in_array($kind, ['sky', 'sea'], true)) abort(404);

        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        if (!in_array($req->status, ['accepted', 'fulfilled'], true)) {
            abort(422, 'Proforma must be approved before sending to freight');
        }
        if ($req->freight_container_id) {
            // Already linked — bounce to the linked container.
            return redirect('/' . $req->freight_kind . '?from_proforma=' . $id);
        }

        $items   = DB::table('sourcing_request_items')->where('sourcing_request_id', $id)
                    ->orderBy('sort_order')->orderBy('id')->get();
        $client  = DB::table('clients')->where('id', $req->client_id)->first();
        $branches = DB::table('branches')->where('deleted', 'false')->orderBy('id')->get();
        $suppliers = DB::table('suppliers')
            ->where('deleted', 'false')
            ->where('sky_sea', $kind)
            ->orderBy('name')->get();

        // Pre-compute aggregates so the operator sees what they're about to
        // ship and can override anything if needed before submit.
        $totals = [
            'pieces'    => 0.0,
            'weight_kg' => 0.0,
            'cbm'       => 0.0,
            'cost_usd'  => 0.0,
        ];
        $rates = !empty($req->fx_rate_snapshot) ? (json_decode($req->fx_rate_snapshot, true) ?: []) : (new dataController())->currency_exchange_rates;
        foreach ($items as $it) {
            $totals['pieces']    += (float) $it->quantity;
            $totals['weight_kg'] += (float) ($it->weight_kg ?? 0);
            $totals['cbm']       += (float) ($it->cbm ?? 0);
            $lineCost = (float) $it->quantity * (float) $it->unit_cost;
            $totals['cost_usd'] += $this->toUsd($lineCost, strtolower((string) $it->unit_cost_currency), $rates);
        }

        $lang = new langController();
        return view('pages.sourcing.handoff_form', [
            'req'       => $req,
            'kind'      => $kind,
            'items'     => $items,
            'client'    => $client,
            'branches'  => $branches,
            'suppliers' => $suppliers,
            'totals'    => $totals,
            'lang'      => $lang,
            'section'   => 'sourcing',
            'page'      => 'sourcing',
        ]);
    }

    public function handoffSubmit(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $this->assertPeriodOpen(date('Y-m-d'));

        $v = $request->validate([
            'kind'             => 'required|in:sky,sea',
            'awb_number'       => 'required|string|max:191',
            'container_name'   => 'nullable|string|max:191',
            'arrival'          => 'required|date',
            'size'             => 'nullable|string|max:32',
            'ship_from'        => 'nullable|string|max:191',
            'supplier'         => 'required|integer|exists:suppliers,id',
            'branch'           => 'required|integer|exists:branches,id',
            'payment_supplier' => 'required|in:pay1,pay2',
            'payment'          => 'required|in:pay1,pay2',
            'cost'             => 'required|numeric|min:0',
            'client_price'     => 'required|numeric|min:0',
            'commission'       => 'nullable|numeric|min:0',
            'packaging_type'   => 'nullable|string|max:64',
            'notes'            => 'nullable|string|max:1000',
        ]);

        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);
        if (!in_array($req->status, ['accepted', 'fulfilled'], true)) {
            return response()->json(['type' => 'error', 'message' => 'Proforma must be approved first'], 422);
        }
        if ($req->freight_container_id) {
            return response()->json(['type' => 'error', 'message' => 'Already handed off to ' . $req->freight_kind . ' container #' . $req->freight_container_id], 422);
        }

        $table = $v['kind'] === 'sky' ? 'containers_sky' : 'containers_sea';
        $containerId = null;

        DB::transaction(function () use ($v, $req, $table, &$containerId) {
            // Insert into the container shell. Most columns are TEXT in the
            // legacy schema; we still cast numerically here so eg. cost
            // sums correctly in profit reports.
            $insert = [
                'client_id'        => (string) $req->client_id,
                'supplier'         => (string) $v['supplier'],
                'payment_supplier' => $v['payment_supplier'],
                'cost'             => (string) $v['cost'],
                'commission'       => (string) ($v['commission'] ?? 0),
                'client_price'     => (string) $v['client_price'],
                'profit'           => (string) ((float) $v['client_price'] + (float) ($v['commission'] ?? 0) - (float) $v['cost']),
                'branch'           => (string) $v['branch'],
                'ship_from'        => $v['ship_from'] ?? null,
                'type'             => 'custom',
                'name'             => $v['container_name'] ?: $req->request_number,
                'number'           => $v['awb_number'],
                'size'             => $v['size'] ?? null,
                'arrival'          => $v['arrival'],
                'packaging_type'   => $v['packaging_type'] ?? null,
                'status'           => 'processing',
                'created_date'     => date('Y-m-d'),
                'created_time'     => date('H:i:s'),
                'created_by'       => (string) auth()->user()->id,
                'canceled'         => 'false',
                'notes'            => $v['notes'] ?? null,
            ];
            // Sea also has custom_status — required for the custom flow to
            // show up in the right tabs of /sea.
            if ($v['kind'] === 'sea') {
                $insert['custom_status'] = 'approved';
            }
            $containerId = DB::table($table)->insertGetId($insert);

            DB::table('sourcing_requests')->where('id', $req->id)->update([
                'freight_kind'         => $v['kind'],
                'freight_container_id' => $containerId,
                'updated_at'           => date('Y-m-d H:i:s'),
            ]);

            $this->logAudit('sourcing_freight_handoff', 'sourcing_requests', $req->id, [
                'kind'         => $v['kind'],
                'container_id' => $containerId,
                'awb_number'   => $v['awb_number'],
                'supplier'     => $v['supplier'],
                'branch'       => $v['branch'],
            ], 'Proforma handed off to ' . $v['kind'] . ' container');
        });

        return response()->json([
            'type'         => 'success',
            'container_id' => $containerId,
            'redirect'     => '/' . $v['kind'],
        ]);
    }

    /* ------------------------------------------------------------
     *  Open balances report — every installment across every proforma,
     *  filtered by status / date / overdue flag. Per-currency totals.
     *
     *  GET /sourcing/payments?status=scheduled&from=YYYY-MM-DD&to=...&overdue=1
     * ------------------------------------------------------------ */
    public function openBalancesReport(Request $request)
    {
        $this->requireAdminOrBranchAdmin();

        $status   = $request->get('status');
        $from     = $request->get('from');
        $to       = $request->get('to');
        $onlyOverdue = (bool) $request->get('overdue');
        $today    = date('Y-m-d');

        $q = DB::table('sourcing_request_payments as p')
            ->join('sourcing_requests as r', 'r.id', '=', 'p.sourcing_request_id')
            ->whereNull('r.deleted_at')
            ->leftJoin('clients as c', 'c.id', '=', 'r.client_id')
            ->select(
                'p.*',
                'r.request_number',
                'r.title',
                'r.client_id',
                'r.status as request_status',
                'r.branch_id',
                'c.name as client_name',
                'c.code as client_code'
            );

        // Branch admin: scope to their branch's clients.
        $user = auth()->user();
        if ($user->type !== 'admin') {
            $q->where('r.branch_id', $user->branch);
        }

        if ($status && in_array($status, ['scheduled', 'partial', 'paid', 'canceled'], true)) {
            $q->where('p.status', $status);
        }
        if ($from) $q->whereDate('p.due_date', '>=', $from);
        if ($to)   $q->whereDate('p.due_date', '<=', $to);
        if ($onlyOverdue) {
            $q->whereIn('p.status', ['scheduled', 'partial'])
              ->whereNotNull('p.due_date')
              ->whereDate('p.due_date', '<', $today);
        }

        $rows = $q->orderByRaw("FIELD(p.status,'scheduled','partial','paid','canceled')")
            ->orderBy('p.due_date')
            ->orderBy('p.sequence')
            ->limit(2000)
            ->get();

        // Aggregate per currency, per status — gives the operator the
        // bottom-line numbers without scrolling the full list.
        $totals = ['outstanding' => [], 'paid' => [], 'overdue' => []];
        foreach ($rows as $r) {
            $ccy = strtoupper($r->currency);
            $outstanding = (float) $r->amount - (float) $r->paid_amount;
            if (in_array($r->status, ['scheduled', 'partial'], true) && $outstanding > 0.0001) {
                $totals['outstanding'][$ccy] = ($totals['outstanding'][$ccy] ?? 0) + $outstanding;
                if ($r->due_date && $r->due_date < $today) {
                    $totals['overdue'][$ccy] = ($totals['overdue'][$ccy] ?? 0) + $outstanding;
                }
            }
            if ($r->status === 'paid' || $r->status === 'partial') {
                $totals['paid'][$ccy] = ($totals['paid'][$ccy] ?? 0) + (float) $r->paid_amount;
            }
        }

        $lang = new langController();
        return view('pages.sourcing.open_balances', [
            'rows'    => $rows,
            'totals'  => $totals,
            'status'  => $status,
            'from'    => $from,
            'to'      => $to,
            'overdue' => $onlyOverdue,
            'today'   => $today,
            'lang'    => $lang,
            'section' => 'sourcing',
            'page'    => 'sourcing_payments',
        ]);
    }

    /* ============================================================
     *  PROFORMA — Phase 2
     *
     *  PDF rendering, public share link, dual approval paths,
     *  installment payment, freight handoff.
     * ============================================================ */

    /* ------------------------------------------------------------
     *  GET /sourcing/{id}/pdf
     *  Render the proforma as a PDF (inline view).
     * ------------------------------------------------------------ */
    public function proformaPdf($id)
    {
        $this->requireAdminOrBranchAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        $payload = $this->loadProformaForRender($req);

        $html = view('pages.sourcing.proforma_pdf', $payload)->render();

        $isRtl = (auth()->user()->lang ?? 'en') === 'ar';
        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4',
            'default_font'   => 'dejavusans',
            'directionality' => $isRtl ? 'rtl' : 'ltr',
            'margin_top'     => 12, 'margin_bottom' => 12,
            'margin_left'    => 14, 'margin_right' => 14,
        ]);
        $mpdf->WriteHTML($html);
        $filename = 'proforma-' . $req->request_number . '.pdf';
        return response($mpdf->Output($filename, 'I'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /* ------------------------------------------------------------
     *  POST /sourcing/{id}/send
     *  Generate a share token, set sent_at, advance status to 'quoted'.
     *  Returns the public URL the operator can copy to the client.
     * ------------------------------------------------------------ */
    public function sendProforma(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        if (in_array($req->status, ['canceled', 'fulfilled'], true)) {
            return response()->json(['type' => 'error', 'message' => 'Cannot send a ' . $req->status . ' proforma'], 422);
        }
        if ((int) DB::table('sourcing_request_items')->where('sourcing_request_id', $id)->count() === 0) {
            return response()->json(['type' => 'error', 'message' => 'Add at least one item before sending'], 422);
        }

        // Reuse the existing token if it's still good, otherwise mint a new
        // one. URL-safe random — 32 bytes = 256 bits of entropy, plenty.
        $token = $req->share_token ?: rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        // Token valid for 90 days — enough for a slow-decision client to
        // come back and approve without us re-issuing. After expiry the
        // public page returns 410 Gone.
        $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));

        $update = [
            'share_token'            => $token,
            'share_token_expires_at' => $expiresAt,
            'sent_at'                => $req->sent_at ?: date('Y-m-d H:i:s'),
            'updated_at'             => date('Y-m-d H:i:s'),
        ];
        if (in_array($req->status, ['open', 'searching'], true)) {
            $update['status'] = 'quoted';
        }
        DB::table('sourcing_requests')->where('id', $id)->update($update);

        // Phase 13: snapshot at the moment we send. Captures exactly what
        // the client is about to see, so any later edits stay diff-able.
        $this->snapshotProforma((int) $id, 'sent');

        $this->logAudit('sourcing_proforma_send', 'sourcing_requests', $id, [
            'token_first8' => substr($token, 0, 8) . '…',
            'expires_at'   => $expiresAt,
        ], 'Proforma sent — share link generated');

        return response()->json([
            'type'       => 'success',
            'token'      => $token,
            'public_url' => url('/proforma/' . $token),
            'expires_at' => $expiresAt,
        ]);
    }

    /* ------------------------------------------------------------
     *  GET /proforma/{token}
     *  PUBLIC — no login required. Renders the client-facing view.
     * ------------------------------------------------------------ */
    public function publicProforma($token)
    {
        $req = DB::table('sourcing_requests')->where('share_token', $token)
            ->whereNull('deleted_at')
            ->first();
        if (!$req) abort(404, 'Proforma not found');
        if ($req->share_token_expires_at && strtotime($req->share_token_expires_at) < time()) {
            abort(410, 'Proforma link has expired');
        }
        if (in_array($req->status, ['canceled'], true)) {
            abort(410, 'Proforma was canceled');
        }

        // View-tracking: record the first time the client opens the link
        // and count every subsequent view. Lets the admin see "client
        // opened on X, viewed N times" before they chase approvals.
        DB::table('sourcing_requests')->where('id', $req->id)->update([
            'client_viewed_at'  => $req->client_viewed_at ?: date('Y-m-d H:i:s'),
            'client_view_count' => (int) $req->client_view_count + 1,
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
        if (!$req->client_viewed_at) {
            // First view — log it for the admin timeline.
            $this->logAudit('sourcing_client_viewed', 'sourcing_requests', $req->id, [
                'ip' => request()->ip(),
                'ua' => substr((string) request()->userAgent(), 0, 191),
            ], 'Client opened share link for the first time');
        }
        $req = DB::table('sourcing_requests')->where('id', $req->id)->first();

        $payload = $this->loadProformaForRender($req);
        $payload['public']  = true;
        $payload['token']   = $token;
        $payload['timeline']= $this->buildPublicTimeline($req);
        $payload['section'] = 'sourcing';
        $payload['page']    = 'sourcing';

        return view('pages.sourcing.public_proforma', $payload);
    }

    /** Public-facing summarised timeline — only client-visible milestones. */
    private function buildPublicTimeline($req): array
    {
        $events = [];
        if ($req->sent_at) {
            $events[] = ['at' => $req->sent_at, 'label' => 'Proforma sent to you'];
        }
        if ($req->client_viewed_at) {
            $events[] = ['at' => $req->client_viewed_at, 'label' => 'You opened this link' .
                        ($req->client_view_count > 1 ? ' (viewed ' . $req->client_view_count . ' times)' : '')];
        }
        if ($req->approved_at) {
            $label = 'Approved';
            if ($req->approved_via === 'client_portal') {
                $label = 'You approved this proforma';
            } elseif ($req->approved_via === 'on_behalf') {
                $label = 'Approved on your behalf by our team';
            } elseif ($req->approved_via === 'admin_direct') {
                $label = 'Approved by our team';
            }
            $events[] = ['at' => $req->approved_at, 'label' => $label];
        }
        return $events;
    }

    /* ------------------------------------------------------------
     *  GET /proforma/{token}/pdf
     *  Public PDF download — same renderer, no login.
     * ------------------------------------------------------------ */
    public function publicProformaPdf($token)
    {
        $req = DB::table('sourcing_requests')->where('share_token', $token)
            ->whereNull('deleted_at')->first();
        if (!$req) abort(404);
        if ($req->share_token_expires_at && strtotime($req->share_token_expires_at) < time()) {
            abort(410);
        }

        $payload = $this->loadProformaForRender($req);
        $html = view('pages.sourcing.proforma_pdf', $payload)->render();

        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4',
            'default_font'   => 'dejavusans',
            'directionality' => 'ltr',
            'margin_top'     => 12, 'margin_bottom' => 12,
            'margin_left'    => 14, 'margin_right' => 14,
        ]);
        $mpdf->WriteHTML($html);
        $filename = 'proforma-' . $req->request_number . '.pdf';
        return response($mpdf->Output($filename, 'I'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /* ------------------------------------------------------------
     *  POST /proforma/{token}/approve
     *  PUBLIC — client clicks Approve on the share page.
     *  Flips status to 'accepted' with approved_via='client_portal'.
     * ------------------------------------------------------------ */
    public function publicApprove(Request $request, $token)
    {
        $req = DB::table('sourcing_requests')->where('share_token', $token)
            ->whereNull('deleted_at')->first();
        if (!$req) abort(404);
        if ($req->share_token_expires_at && strtotime($req->share_token_expires_at) < time()) {
            abort(410);
        }

        return $this->doApprove(
            $req->id,
            'client_portal',
            null,
            'Approved by client via public share link'
        );
    }

    /* ------------------------------------------------------------
     *  POST /sourcing/{id}/approve-on-behalf
     *  Internal user approves the proforma for a client who confirmed
     *  via WhatsApp / phone / in person. Same target state.
     * ------------------------------------------------------------ */
    public function approveOnBehalf(Request $request, $id)
    {
        $this->requireAdminOrBranchAdmin();
        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        $this->assertCanAccessClient($req->client_id);

        return $this->doApprove(
            $id,
            'on_behalf',
            (int) auth()->user()->id,
            'Approved on behalf of client by ' . (auth()->user()->name ?? 'admin')
        );
    }

    /** Shared approval implementation — same target state on both paths. */
    private function doApprove(int $id, string $via, ?int $userId, string $auditMsg)
    {
        $req = DB::table('sourcing_requests')->where('id', $id)->first();
        if (!$req) abort(404);
        if (!in_array($req->status, ['open', 'searching', 'quoted'], true)) {
            return response()->json([
                'type' => 'error',
                'message' => 'Cannot approve a ' . $req->status . ' proforma',
            ], 422);
        }

        DB::table('sourcing_requests')->where('id', $id)->update([
            'status'              => 'accepted',
            'approved_via'        => $via,
            'approved_by_user_id' => $userId,
            'approved_at'         => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        // Phase 13: snapshot the moment of approval — this is the
        // canonical "what the client agreed to" record.
        $this->snapshotProforma((int) $id, 'approved');

        $this->logAudit('sourcing_proforma_approve', 'sourcing_requests', $id, [
            'approved_via' => $via,
            'user_id'      => $userId,
        ], $auditMsg);

        return response()->json(['type' => 'success', 'approved_via' => $via]);
    }

    /* ------------------------------------------------------------
     *  POST /sourcing/proforma/payments/mark-paid
     *
     *  Two paths:
     *   - 'wallet'   — client already has credit with us. Just mark the
     *                  installment paid; no journal entry (the cash was
     *                  recognized when they originally deposited).
     *   - 'cash'     — client paid us now. Post a client deposit and link.
     *
     *  Either way, sourcing_request_payments.settled_by_transaction_id is
     *  populated (NULL for wallet — there is no new transaction row to
     *  point at; the existing wallet balance is the audit trail).
     * ------------------------------------------------------------ */
    public function markInstallmentPaid(Request $request)
    {
        $this->requireAdminOrBranchAdmin();
        $this->assertPeriodOpen(date('Y-m-d'));

        $v = $request->validate([
            'id'           => 'required|integer|exists:sourcing_request_payments,id',
            'method'       => 'required|in:wallet,cash',
            'amount'       => 'required|numeric|min:0.0001',
            'branch'       => 'nullable|integer|exists:branches,id',
            'notes'        => 'nullable|string|max:500',
        ]);

        $row = DB::table('sourcing_request_payments')->where('id', $v['id'])->first();
        if (!$row) abort(404);
        if (in_array($row->status, ['paid', 'canceled'], true)) {
            return response()->json([
                'type' => 'error',
                'message' => 'Installment already ' . $row->status,
            ], 422);
        }

        $req = DB::table('sourcing_requests')->where('id', $row->sourcing_request_id)->first();
        $this->assertCanAccessClient($req->client_id);

        $amount = (float) $v['amount'];
        $linkedTxn = null;

        DB::transaction(function () use ($v, $row, $req, $amount, &$linkedTxn) {
            // Cash path posts a real client deposit through the normal
            // clientsController — we don't reinvent the wheel. The deposit
            // hits 1000 / 2000 via the existing journal logic, and we
            // tag the journal entry with cost_object_type=sourcing_request
            // by patching the resulting row (a clean refactor for later
            // would push cost_object down into clientsController::deposit).
            if ($v['method'] === 'cash') {
                $dataController = new dataController();
                $branchesCtrl   = new branchesController();
                $treasury       = new treasuryController();

                $tr_code = $dataController->tr_code;
                $last = DB::table('clients_transactions')->orderByDesc('auto_id')->limit(1)->first();
                $auto_id = $last ? $last->auto_id + 1 : $tr_code;
                $txnNumber = 'sourcing_pay_' . $row->id . '_' . date('Ymdhis');

                $remaining = (new clientsController())->calc_balance($req->client_id, $v['amount'] ? $row->currency : 'usd', true);

                $depositRowId = DB::table('clients_transactions')->insertGetId([
                    'transaction_number' => $txnNumber,
                    'value'        => $amount,
                    'status'       => 'approved',
                    'currency'     => $row->currency,
                    'commission'   => 0,
                    'auto_id'      => $auto_id,
                    'type'         => 'deposit',
                    'plus_minus'   => 'plus',
                    'branch'       => $v['branch'] ?? null,
                    'remaining_balance' => $remaining + $amount,
                    'notes'        => 'Sourcing installment #' . $row->sequence . ' — ' . $req->request_number,
                    'purpose'      => 'sourcing_payment',
                    'client_id'    => $req->client_id,
                    'created_by'   => auth()->user()->id,
                    'created_date' => date('Y-m-d'),
                    'created_time' => date('H:i:s'),
                ]);

                (new clientsController())->update_balance($req->client_id);
                if (!empty($v['branch'])) $branchesCtrl->update_balance($v['branch'], $row->currency);

                $treasury->insert($txnNumber, 'deposit', 'plus', $auto_id, null, $amount, $row->currency, 0, $v['branch'] ?? null, $v['notes'] ?? null, $req->client_id, $remaining + $amount);

                $journalLines = [
                    ['account_code' => '1000', 'dr' => $amount, 'cr' => 0, 'currency' => $row->currency,
                     'counterparty_type' => 'client', 'counterparty_id' => (int) $req->client_id,
                     'branch_id' => $v['branch'] ?? null,
                     'cost_object_type' => 'sourcing_request', 'cost_object_id' => (int) $req->id,
                     'description' => 'Sourcing payment received — ' . $req->request_number],
                    ['account_code' => '2000', 'dr' => 0, 'cr' => $amount, 'currency' => $row->currency,
                     'counterparty_type' => 'client', 'counterparty_id' => (int) $req->client_id,
                     'branch_id' => $v['branch'] ?? null,
                     'cost_object_type' => 'sourcing_request', 'cost_object_id' => (int) $req->id,
                     'description' => 'Credited to client wallet'],
                ];
                (new journalController())->record([
                    'entry_date'         => date('Y-m-d'),
                    'kind'               => 'sourcing_payment',
                    'description'        => 'Sourcing installment #' . $row->sequence . ' — ' . $req->request_number,
                    'source_table'       => 'clients_transactions',
                    'source_id'          => $depositRowId,
                    'transaction_number' => $txnNumber,
                    'branch_id'          => $v['branch'] ?? null,
                    'cost_object_type'   => 'sourcing_request',
                    'cost_object_id'     => (int) $req->id,
                    'lines'              => $journalLines,
                ]);

                $linkedTxn = $depositRowId;
            }
            // Wallet path: no journal — the cash was recognised the day
            // the client's wallet was originally credited. Marking this
            // installment paid is just an internal allocation.

            $newPaid   = (float) $row->paid_amount + $amount;
            $newStatus = $newPaid + 0.0001 >= (float) $row->amount ? 'paid' : 'partial';

            DB::table('sourcing_request_payments')->where('id', $row->id)->update([
                'paid_amount'                => $newPaid,
                'status'                     => $newStatus,
                'settled_at'                 => $newStatus === 'paid' ? date('Y-m-d H:i:s') : null,
                'settled_by_transaction_id'  => $linkedTxn ?: $row->settled_by_transaction_id,
                'notes'                      => $v['notes'] ?? $row->notes,
                'updated_at'                 => date('Y-m-d H:i:s'),
            ]);

            $this->logAudit('sourcing_installment_paid', 'sourcing_request_payments', $row->id, [
                'method'             => $v['method'],
                'amount'             => $amount,
                'currency'           => $row->currency,
                'linked_transaction' => $linkedTxn,
            ], 'Sourcing installment marked paid (' . $v['method'] . ')');
        });

        // Phase 7: auto-fulfill if this payment was the last one needed.
        $autoFulfillId = $this->maybeAutoFulfill((int) $row->sourcing_request_id);

        return response()->json([
            'type'                 => 'success',
            'linked_transaction'   => $linkedTxn,
            'auto_fulfilled'       => (bool) $autoFulfillId,
            'margin_journal_id'    => $autoFulfillId,
        ]);
    }

    /* ------------------------------------------------------------
     *  Load the proforma + relations into a render payload. Shared by
     *  the internal show page, the PDF, and the public client view.
     * ------------------------------------------------------------ */
    private function loadProformaForRender($req): array
    {
        $client   = DB::table('clients')->where('id', $req->client_id)->first();
        $branch   = $req->branch_id
            ? DB::table('branches')->where('id', $req->branch_id)->first()
            : null;
        $items    = DB::table('sourcing_request_items')
            ->where('sourcing_request_id', $req->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get();
        $itemIds  = $items->pluck('id')->all();
        $photoRows = empty($itemIds) ? collect() : DB::table('sourcing_request_item_photos')
            ->whereIn('item_id', $itemIds)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $photos = [];
        foreach ($photoRows as $p) {
            $photos[$p->item_id] = $photos[$p->item_id] ?? [];
            $photos[$p->item_id][] = $p;
        }
        $payments = DB::table('sourcing_request_payments')
            ->where('sourcing_request_id', $req->id)
            ->orderBy('sequence')->orderBy('id')
            ->get();

        $documents = DB::table('sourcing_request_documents')
            ->where('sourcing_request_id', $req->id)
            ->orderByDesc('id')
            ->get();

        $settings = (new settingsController())->get();
        $lang     = new langController();
        $data     = new dataController();

        return [
            'req'         => $req,
            'client'      => $client,
            'branch'      => $branch,
            'items'       => $items,
            'photos'      => $photos,
            'payments'    => $payments,
            'documents'   => $documents,
            'settings'    => $settings,
            'lang'        => $lang,
            'data'        => $data,
        ];
    }

    /**
     * Phase 7 — auto-fulfill check.
     *
     * If the proforma is in 'accepted' state AND every item has reached
     * delivery_status='delivered' AND every installment is 'paid' (or
     * we'd never finish a no-installment proforma), transition straight
     * to 'fulfilled' and post the revenue recognition entry.
     *
     * Called from updateItemStatus() and markInstallmentPaid() — the two
     * mutations that can make these conditions newly true.
     *
     * Returns the journal entry id when fulfillment posted, or null when
     * conditions weren't met (or the proforma was already fulfilled).
     */
    private function maybeAutoFulfill(int $sourcingRequestId): ?int
    {
        $req = DB::table('sourcing_requests')->where('id', $sourcingRequestId)->first();
        if (!$req || $req->status !== 'accepted') return null;

        // 1) All items delivered.
        $items = DB::table('sourcing_request_items')
            ->where('sourcing_request_id', $req->id)->get();
        if ($items->isEmpty()) return null;
        foreach ($items as $it) {
            if (($it->delivery_status ?: 'pending') !== 'delivered') return null;
        }

        // 2) Every installment fully paid. A proforma with no payment plan
        // at all also gates here (none-paid → cannot auto-finish), since we
        // can't tell whether the operator intended a cash deal vs forgot
        // to log payments.
        $installments = DB::table('sourcing_request_payments')
            ->where('sourcing_request_id', $req->id)
            ->whereIn('status', ['scheduled', 'partial', 'paid'])
            ->get();
        if ($installments->isEmpty()) return null;
        foreach ($installments as $p) {
            if ($p->status !== 'paid') return null;
        }

        // Conditions met — flip status + post the margin entry. This is
        // the same logic markFulfilled() runs, factored out so both the
        // explicit button and the auto-trigger reach the same end state.
        $journalId = null;
        DB::transaction(function () use ($req, &$journalId) {
            DB::table('sourcing_requests')->where('id', $req->id)->update([
                'status'     => 'fulfilled',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $journalId = $this->postFulfillmentMarginEntry($req);
            // Phase 13: snapshot at auto-fulfillment too.
            $this->snapshotProforma((int) $req->id, 'fulfilled');
        });

        $this->logAudit('sourcing_auto_fulfill', 'sourcing_requests', $req->id, [
            'margin_journal_id' => $journalId,
            'trigger'           => 'all_items_delivered_and_paid',
        ], 'Auto-fulfilled: all items delivered and all installments paid');

        return $journalId;
    }

    /** Convert an amount in $ccy to USD using the rate map (foreign per USD). */
    private function toUsd(float $amount, string $ccy, array $rates): float
    {
        if ($ccy === 'usd' || $amount == 0.0) return $amount;
        $rate = (float) ($rates[$ccy] ?? 0);
        return $rate > 0 ? $amount / $rate : 0.0;
    }

    /** Convert a USD amount to $ccy. */
    private function fromUsd(float $usdAmount, string $ccy, array $rates): float
    {
        if ($ccy === 'usd' || $usdAmount == 0.0) return $usdAmount;
        return $usdAmount * (float) ($rates[$ccy] ?? 1);
    }

    private function requireAdmin(): void
    {
        if ((auth()->user()->type ?? null) !== 'admin') {
            abort(403, 'Admin only');
        }
    }

    private function requireAdminOrBranchAdmin(): void
    {
        $t = auth()->user()->type ?? null;
        if (!in_array($t, ['admin', 'branch_admin'], true)) {
            abort(403, 'Unauthorized');
        }
    }
}
