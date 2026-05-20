<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ClientBalanceService;
use Illuminate\Http\Request;

/**
 * Returns the authenticated client's per-currency balance computed from
 * journal_lines (post-audit canonical source). See ClientBalanceService.
 */
class BalanceController extends Controller
{
    public function __invoke(Request $request, ClientBalanceService $balances)
    {
        return response()->json($balances->forClient((int) $request->user()->id));
    }
}
