<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Expand the chart of accounts to match the Libyan-shipping-company
     * spec from the 2026-05-25 owner meeting (granular revenue by service,
     * granular expense categories, employee custody, goods-in-transit,
     * carrier AP, accrued expenses, partner equity, FX gain as revenue).
     *
     * Idempotent: re-running only inserts rows whose `code` is missing.
     * Existing system accounts (codes 1000, 1010, 1100, 1200, 1300, 2000,
     * 2100, 2200, 3000, 3100, 4000, 4100, 5000, 5100, 5200) are NOT
     * touched — every controller that posts journal entries hardcodes
     * those codes, so renaming or renumbering would break live postings.
     *
     * parent_id is wired in a second pass after all rows exist.
     */
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        // [code, name, name_en, name_zh, type, normal_balance, derivation_key, parent_code]
        // parent_code is resolved to parent_id below — null for top-level accounts.
        $rows = [
            // ---- Assets (new) ----
            ['1400', 'Employee custody',         'Employee custody',         '员工备用金',   'asset',     'debit',  null, null],
            ['1500', 'Goods in transit (clients)','Goods in transit (clients)','客户在途货物','asset',     'debit',  null, null],
            ['1600', 'Prepaid expenses',         'Prepaid expenses',         '预付费用',     'asset',     'debit',  null, null],

            // ---- Liabilities (new) ----
            ['2300', 'Accounts payable - carriers','Accounts payable - carriers','承运商应付款','liability','credit', null, null],
            ['2400', 'Accrued expenses',         'Accrued expenses',         '应计费用',     'liability', 'credit', null, null],

            // ---- Equity (new) ----
            ['3200', 'Partners\' current accounts','Partners\' current accounts','合伙人往来','equity',   'credit', null, null],
            ['3300', 'Partners\' drawings',      'Partners\' drawings',      '合伙人提款',   'equity',    'debit',  null, null],

            // ---- Revenue (new) ----
            //  Commission sub-accounts (parent: 4000)
            ['4010', 'Purchase commission revenue','Purchase commission revenue','采购佣金收入','revenue','credit', null, '4000'],
            ['4020', 'Sourcing commission revenue','Sourcing commission revenue','寻货佣金收入','revenue','credit', null, '4000'],
            //  Shipping sub-accounts (parent: 4100)
            ['4110', 'Air freight revenue',      'Air freight revenue',      '空运收入',     'revenue',   'credit', null, '4100'],
            ['4120', 'LCL sea freight revenue',  'LCL sea freight revenue',  '海运拼箱收入', 'revenue',   'credit', null, '4100'],
            ['4130', 'FCL container revenue',    'FCL container revenue',    '海运整箱收入', 'revenue',   'credit', null, '4100'],
            //  Other services
            ['4200', 'Inspection revenue',       'Inspection revenue',       '验货收入',     'revenue',   'credit', null, null],
            ['4300', 'Packaging revenue',        'Packaging revenue',        '包装收入',     'revenue',   'credit', null, null],
            ['4400', 'FX gain',                  'FX gain',                  '汇兑收益',     'revenue',   'credit', null, null],

            // ---- Expenses (new) — granular categories from the meeting ----
            ['5300', 'Salaries',                 'Salaries',                 '工资',         'expense',   'debit',  null, null],
            ['5310', 'Rent',                     'Rent',                     '租金',         'expense',   'debit',  null, null],
            ['5320', 'Domestic freight',         'Domestic freight',         '国内运费',     'expense',   'debit',  null, null],
            ['5330', 'Customs clearance expense','Customs clearance expense','报关费',       'expense',   'debit',  null, null],
            ['5340', 'Loading and unloading',    'Loading and unloading',    '装卸费',       'expense',   'debit',  null, null],
            ['5350', 'Inter-city transport',     'Inter-city transport',     '城际运输',     'expense',   'debit',  null, null],
            ['5360', 'Port fees',                'Port fees',                '港口费',       'expense',   'debit',  null, null],
            ['5370', 'Warehouse fees',           'Warehouse fees',           '仓储费',       'expense',   'debit',  null, null],
            ['5380', 'Marketing and advertising','Marketing and advertising','市场营销',     'expense',   'debit',  null, null],
            ['5390', 'Wire transfer fees',       'Wire transfer fees',       '汇款手续费',   'expense',   'debit',  null, null],
            ['5400', 'Administrative expenses',  'Administrative expenses',  '管理费用',     'expense',   'debit',  null, null],
        ];

        $existingCodes = DB::table('chart_of_accounts')->pluck('code')->all();
        $existingSet   = array_flip($existingCodes);

        $payload = [];
        foreach ($rows as $r) {
            if (isset($existingSet[$r[0]])) {
                continue;
            }
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

        // Resolve parent_code -> parent_id and update every row that has one.
        $codeToId = DB::table('chart_of_accounts')->pluck('id', 'code')->all();
        foreach ($rows as $r) {
            $childCode  = $r[0];
            $parentCode = $r[7];
            if ($parentCode === null) continue;
            if (!isset($codeToId[$childCode]) || !isset($codeToId[$parentCode])) continue;

            DB::table('chart_of_accounts')
                ->where('code', $childCode)
                ->update([
                    'parent_id'  => $codeToId[$parentCode],
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // Codes 1400, 1500, 4200, 5400 are NOT listed here — those belong
        // to the Purchases module (warehouse_inventory, goods_in_shipment,
        // fx_gain, cogs_delivered) and pre-existed in the live DB. The
        // skip-if-exists guard in up() means we never owned them; down()
        // must mirror that.
        $codes = [
            '1600',
            '2300','2400',
            '3200','3300',
            '4010','4020','4110','4120','4130','4300','4400',
            '5300','5310','5320','5330','5340','5350','5360','5370','5380','5390',
        ];
        DB::table('chart_of_accounts')->whereIn('code', $codes)->delete();
    }
};
