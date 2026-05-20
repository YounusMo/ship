<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Computes a client's per-currency balance from the journal — NOT from the
 * legacy clients.balance_* columns. Per the post-audit decision, the
 * journal is the canonical source of truth; entity balances are a
 * downstream cache that drift can land in.
 *
 * The shape returned mirrors what the mobile app's Dashboard expects:
 *   [
 *     'usd' => 1234.56,
 *     'eur' => 0.00,
 *     'den' => 0.00,
 *     'cny' => 0.00,
 *     'as_of' => 'YYYY-MM-DD HH:MM:SS',
 *   ]
 *
 * Sign convention: client balance is the company's liability to them
 * (account 2000, credit-normal). A positive balance means we owe them.
 * That maps to the natural way a customer thinks about "my balance".
 */
class ClientBalanceService
{
    private const CURRENCIES = ['usd', 'eur', 'den', 'cny'];

    /** Account 2000 = "Client deposits" liability per the chart of accounts. */
    private const CLIENT_LIABILITY_ACCOUNT = '2000';

    public function forClient(int $clientId): array
    {
        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.entry_id')
            ->where('journal_lines.counterparty_type', 'client')
            ->where('journal_lines.counterparty_id', $clientId)
            ->where('journal_lines.account_code', self::CLIENT_LIABILITY_ACCOUNT)
            ->select(
                'journal_lines.currency',
                DB::raw('SUM(journal_lines.cr) - SUM(journal_lines.dr) as natural_balance')
            )
            ->groupBy('journal_lines.currency')
            ->get();

        $out = array_fill_keys(self::CURRENCIES, 0.0);
        foreach ($rows as $r) {
            if (in_array($r->currency, self::CURRENCIES, true)) {
                $out[$r->currency] = round((float) $r->natural_balance, 2);
            }
        }
        $out['as_of'] = date('Y-m-d H:i:s');
        return $out;
    }
}
