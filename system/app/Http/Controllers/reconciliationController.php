<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reconciliation report — read-only.
 *
 * For every active client and every branch, recompute the running balance
 * from the transactions table and compare against the stored cached value
 * on the parent row. Anything that disagrees by more than EPSILON is a
 * candidate for investigation.
 *
 * This controller deliberately does NOT write anywhere. Auto-fixing a
 * money discrepancy is a head-office decision, not a button on a webpage.
 * The job here is to surface the discrepancy and let an admin go look at
 * the underlying transactions.
 */
class reconciliationController extends Controller
{
    /** Anything below this is treated as a float-rounding artifact, not a real drift. */
    private const EPSILON = 0.01;

    private const CURRENCIES = ['usd', 'eur', 'den', 'cny'];

    private function assertAdminOnly(): void
    {
        if (!in_array(auth()->user()->type, ['admin'], true)) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Compute a client's running balance per currency from clients_transactions.
     * Mirrors clientsController::calc_balance() but returns all four currencies
     * in a single pass over the rows.
     */
    private function computeClientBalances(int $clientId): array
    {
        $balances = ['usd' => 0.0, 'eur' => 0.0, 'den' => 0.0, 'cny' => 0.0];

        $rows = DB::table('clients_transactions')
            ->where('status', 'approved')
            ->where('client_id', $clientId)
            ->get();

        foreach ($rows as $r) {
            if ($r->type === 'transfer') {
                if (isset($balances[$r->currency])) {
                    $balances[$r->currency] -= floatval($r->value);
                }
                if (isset($balances[$r->to_currency])) {
                    $balances[$r->to_currency] += floatval($r->transfer_value);
                }
                continue;
            }

            if (!isset($balances[$r->currency])) continue;

            if ($r->plus_minus === 'plus') {
                $balances[$r->currency] += floatval($r->value);
            } elseif ($r->plus_minus === 'minus') {
                $balances[$r->currency] -= floatval($r->value);
            }
        }

        return $balances;
    }

    /**
     * Compute a branch's treasury balance per currency. Mirrors the (much
     * heavier) logic in branchesController::update_balance, but read-only.
     */
    private function computeBranchBalances(int $branchId): array
    {
        $balances = ['usd' => 0.0, 'eur' => 0.0, 'den' => 0.0, 'cny' => 0.0];

        foreach (self::CURRENCIES as $cur) {
            $plus = 0.0;
            $minus = 0.0;

            $minus += floatval(DB::table('suppliers_transactions')
                ->where('branch', $branchId)
                ->where('plus_minus', 'plus')
                ->where('from_currency', $cur)
                ->sum('from_value'));

            $minus += floatval(DB::table('customs_brokers_transactions')
                ->where('branch', $branchId)
                ->where('plus_minus', 'plus')
                ->where('from_currency', $cur)
                ->sum('value'));

            $clientRows = DB::table('clients_transactions')
                ->where('status', 'approved')
                ->whereNull('calc')
                ->whereNotIn('type', ['transfer', 'exp_custom_withdraw', 'exp_withdraw'])
                ->where('branch', $branchId)
                ->where('currency', $cur)
                ->get();

            foreach ($clientRows as $r) {
                if ($r->plus_minus === 'plus') {
                    $plus += floatval($r->value) + floatval($r->commission);
                } elseif ($r->plus_minus === 'minus') {
                    if ($r->type === 'withdraw_commission') {
                        $plus += floatval($r->value);
                    } else {
                        $minus += floatval($r->value);
                    }
                }
            }

            $branchRows = DB::table('branches_transactions')
                ->where('branch', $branchId)
                ->where(function ($q) use ($cur) {
                    $q->where('currency', $cur)->orWhere('to_currency', $cur);
                })
                ->get();

            foreach ($branchRows as $r) {
                if ($r->type !== 'transfer_branch') {
                    if ($r->plus_minus === 'plus') {
                        $plus += floatval($r->value);
                    } elseif ($r->plus_minus === 'minus') {
                        $minus += floatval($r->value);
                    }
                    continue;
                }
                if ($r->currency === $cur) {
                    $minus += floatval($r->value);
                }
                if ($r->to_currency === $cur) {
                    $plus += floatval($r->transfer_value);
                }
            }

            $balances[$cur] = $plus - $minus;
        }

        return $balances;
    }

    private function discrepancies(array $stored, array $computed): array
    {
        $out = [];
        foreach (self::CURRENCIES as $c) {
            $s = floatval($stored['balance_' . $c] ?? 0);
            $x = floatval($computed[$c] ?? 0);
            $diff = $s - $x;
            if (abs($diff) > self::EPSILON) {
                $out[$c] = [
                    'stored'   => $s,
                    'computed' => $x,
                    'diff'     => $diff,
                ];
            }
        }
        return $out;
    }

    public function index()
    {
        $this->assertAdminOnly();
        return view('pages.reconciliation.index', [
            'section' => 'reconciliation',
            'page'    => 'reconciliation',
        ]);
    }

    public function clients(Request $request)
    {
        $this->assertAdminOnly();

        try {
            $onlyDiff = $request->only_diff !== 'false'; // default ON
            $perPage  = (int) env('PAGEVIEW', 50);

            $clients = DB::table('clients')
                ->where('deleted', 'false')
                ->where('not_active', 'false')
                ->orderBy('id', 'DESC')
                ->paginate($perPage);

            $rows = [];
            $totalDrift = ['usd' => 0.0, 'eur' => 0.0, 'den' => 0.0, 'cny' => 0.0];

            foreach ($clients as $c) {
                $computed = $this->computeClientBalances((int) $c->id);
                $diffs    = $this->discrepancies((array) $c, $computed);

                foreach ($diffs as $cur => $d) {
                    $totalDrift[$cur] += $d['diff'];
                }

                if ($onlyDiff && empty($diffs)) {
                    continue;
                }

                $rows[] = [
                    'id'       => $c->id,
                    'code'     => $c->code,
                    'name'     => $c->name,
                    'branch'   => $c->branch,
                    'stored'   => [
                        'usd' => $c->balance_usd,
                        'eur' => $c->balance_eur,
                        'den' => $c->balance_den,
                        'cny' => $c->balance_cny,
                    ],
                    'computed' => $computed,
                    'diffs'    => $diffs,
                ];
            }

            return view('pages.reconciliation.clients', compact('rows', 'clients', 'totalDrift', 'onlyDiff'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), ['exception' => $th]);
            return response()->json(['type' => 'error'], 500);
        }
    }

    public function branches(Request $request)
    {
        $this->assertAdminOnly();

        try {
            $onlyDiff = $request->only_diff !== 'false';

            $branches = DB::table('branches')
                ->where('deleted', 'false')
                ->orderBy('id', 'ASC')
                ->get();

            $rows = [];
            $totalDrift = ['usd' => 0.0, 'eur' => 0.0, 'den' => 0.0, 'cny' => 0.0];

            foreach ($branches as $b) {
                $computed = $this->computeBranchBalances((int) $b->id);
                $diffs    = $this->discrepancies((array) $b, $computed);

                foreach ($diffs as $cur => $d) {
                    $totalDrift[$cur] += $d['diff'];
                }

                if ($onlyDiff && empty($diffs)) {
                    continue;
                }

                $rows[] = [
                    'id'       => $b->id,
                    'name'     => $b->name,
                    'name_en'  => $b->name_en ?? null,
                    'stored'   => [
                        'usd' => $b->balance_usd,
                        'eur' => $b->balance_eur,
                        'den' => $b->balance_den,
                        'cny' => $b->balance_cny,
                    ],
                    'computed' => $computed,
                    'diffs'    => $diffs,
                ];
            }

            return view('pages.reconciliation.branches', compact('rows', 'totalDrift', 'onlyDiff'));
        } catch (\Throwable $th) {
            Log::error($th->getMessage(), ['exception' => $th]);
            return response()->json(['type' => 'error'], 500);
        }
    }
}
