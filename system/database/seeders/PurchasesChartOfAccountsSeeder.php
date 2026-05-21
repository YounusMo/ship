<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the chart_of_accounts rows that the Purchases module needs.
 *
 * Idempotent: each row is inserted only if its code is not already
 * present, so this can run on a chart that already has the base
 * codes from the original chart_of_accounts migration.
 *
 * See docs/ALIGNMENT_PATCH.md §2.4 for why these specific codes were
 * chosen (gap-fill in the existing chart, no semantic collisions).
 */
class PurchasesChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $rows = [
            // [code, name (ar), name_en, name_zh, type, normal_balance, derivation_key]
            ['1250', 'عُهدة المشتريات (USD)',    'Buyer custody (USD)',      '采购托管', 'asset',   'debit',  'buyer_custody'],
            ['1320', 'مشتريات تحت التسليم',     'Purchases in transit',     '在途采购', 'asset',   'debit',  'purchases_in_transit'],
            ['1400', 'بضاعة في المستودع',        'Warehouse inventory',      '仓库存货', 'asset',   'debit',  'warehouse_inventory'],
            ['1500', 'بضاعة في الشحن',           'Goods in shipment',        '在运货物', 'asset',   'debit',  'goods_in_shipment'],
            ['4200', 'أرباح فروقات الصرف',       'FX gain',                  '汇兑收益', 'revenue', 'credit', 'fx_gain'],
            ['5400', 'تكلفة البضاعة المسلَّمة',  'COGS — delivered',         '已交付商品成本', 'expense', 'debit', 'cogs_delivered'],
        ];

        $existing = DB::table('chart_of_accounts')
            ->whereIn('code', array_column($rows, 0))
            ->pluck('code')
            ->all();

        $toInsert = [];
        foreach ($rows as $r) {
            if (in_array($r[0], $existing, true)) {
                continue;
            }
            $toInsert[] = [
                'code'           => $r[0],
                'name'           => $r[1],
                'name_en'        => $r[2],
                'name_zh'        => $r[3],
                'type'           => $r[4],
                'normal_balance' => $r[5],
                'derivation_key' => $r[6],
                'is_active'      => true,
                'is_system'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        if ($toInsert !== []) {
            DB::table('chart_of_accounts')->insert($toInsert);
        }
    }
}
