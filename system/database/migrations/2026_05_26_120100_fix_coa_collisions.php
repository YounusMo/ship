<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * The first CoA expansion (2026_05_26_120000_expand_chart_of_accounts)
     * was written from the seed migration without knowing the Purchases
     * module had already taken codes 1400, 1500, 4200, and 5400 for its
     * own derivation_keys (warehouse_inventory, goods_in_shipment, fx_gain,
     * cogs_delivered). The skip-if-exists guard in that migration meant
     * those three accounts never landed:
     *   - Employee custody   (intended 1400, collided with warehouse_inventory)
     *   - Inspection revenue (intended 4200, collided with fx_gain)
     *   - Administrative exp (intended 5400, collided with cogs_delivered)
     *
     * Goods-in-transit (intended 1500) is dropped — code 1500 already
     * represents the same concept under "goods_in_shipment". FX gain
     * (intended 4400) is also dropped — 4200 already covers it.
     *
     * This migration relocates the three missing accounts and removes
     * the duplicate 4400. Safe to re-run.
     */
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        $rows = [
            ['1410', 'Employee custody',         'Employee custody',         '员工备用金', 'asset',   'debit',  null],
            ['4210', 'Inspection revenue',       'Inspection revenue',       '验货收入',   'revenue', 'credit', null],
            ['5410', 'Administrative expenses',  'Administrative expenses',  '管理费用',   'expense', 'debit',  null],
        ];

        $existing = DB::table('chart_of_accounts')->pluck('code')->all();
        $set      = array_flip($existing);

        $payload = [];
        foreach ($rows as $r) {
            if (isset($set[$r[0]])) continue;
            $payload[] = [
                'code'           => $r[0],
                'name'           => $r[1],
                'name_en'        => $r[2],
                'name_zh'        => $r[3],
                'type'           => $r[4],
                'normal_balance' => $r[5],
                'derivation_key' => $r[6],
                'is_system'      => true,
                'is_active'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }
        if (!empty($payload)) {
            DB::table('chart_of_accounts')->insert($payload);
        }

        // Drop the duplicate FX gain I added at 4400 — code 4200 already
        // exists with derivation_key='fx_gain' and is canonical. Safety: only
        // remove if it carries no journal postings yet.
        $dup4400 = DB::table('chart_of_accounts')->where('code', '4400')->first();
        if ($dup4400) {
            $postings = DB::table('journal_lines')->where('account_code', '4400')->count();
            if ($postings === 0) {
                DB::table('chart_of_accounts')->where('code', '4400')->delete();
            }
        }
    }

    public function down(): void
    {
        DB::table('chart_of_accounts')
            ->whereIn('code', ['1410', '4210', '5410'])
            ->delete();
        // Not re-creating 4400 — it was a mistake to add it in the first place.
    }
};
