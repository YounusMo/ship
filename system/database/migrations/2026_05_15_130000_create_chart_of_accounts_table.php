<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('code', 16)->unique();
            $t->string('name', 191);
            $t->string('name_en', 191)->nullable();
            $t->string('name_zh', 191)->nullable();
            $t->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $t->enum('normal_balance', ['debit', 'credit']);
            $t->unsignedBigInteger('parent_id')->nullable()->index();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_system')->default(false);
            $t->string('derivation_key', 64)->nullable()->index();
            $t->timestamps();
        });

        $now = date('Y-m-d H:i:s');
        $rows = [
            // Assets
            ['1000', 'Cash on hand', 'Cash on hand', '现金', 'asset',     'debit',  'cash_total',          1],
            ['1010', 'Bank',         'Bank',         '银行', 'asset',     'debit',  null,                  1],
            ['1100', 'Accounts receivable - clients', 'Accounts receivable - clients', '客户应收款', 'asset', 'debit', 'ar_clients', 1],
            ['1200', 'Prepaid to suppliers',     'Prepaid to suppliers',  '供应商预付款',  'asset',   'debit',  'ar_suppliers', 1],
            ['1300', 'Prepaid to customs brokers','Prepaid to customs brokers','报关行预付款','asset','debit','ar_brokers',  1],
            // Liabilities
            ['2000', 'Client deposits (unearned)','Client deposits',      '客户存款',     'liability','credit', 'client_deposits', 1],
            ['2100', 'Accounts payable - suppliers','Accounts payable - suppliers','供应商应付款','liability','credit','ap_suppliers',1],
            ['2200', 'Accounts payable - customs brokers','Accounts payable - customs brokers','报关行应付款','liability','credit','ap_brokers',1],
            // Equity
            ['3000', 'Owner\'s equity',          'Owner\'s equity',       '所有者权益',   'equity',   'credit', 'owners_equity', 1],
            ['3100', 'Owner\'s drawings',        'Owner\'s drawings',     '所有者提款',   'equity',   'debit',  'owner_drawings', 1],
            // Revenue
            ['4000', 'Commission revenue',       'Commission revenue',    '佣金收入',     'revenue',  'credit', 'commission_revenue', 1],
            ['4100', 'Shipping revenue',         'Shipping revenue',      '运输收入',     'revenue',  'credit', 'shipping_revenue', 1],
            // Expenses
            ['5000', 'Operating expenses',       'Operating expenses',    '运营费用',     'expense',  'debit',  'operating_expenses', 1],
            ['5100', 'Owner\'s salary',          'Owner\'s salary',       '所有者工资',   'expense',  'debit',  'owner_salary', 1],
            ['5200', 'FX gain/loss',             'FX gain/loss',          '汇兑损益',     'expense',  'debit',  null,                1],
        ];
        $payload = [];
        foreach ($rows as $r) {
            $payload[] = [
                'code'           => $r[0],
                'name'           => $r[1],
                'name_en'        => $r[2],
                'name_zh'        => $r[3],
                'type'           => $r[4],
                'normal_balance' => $r[5],
                'derivation_key' => $r[6],
                'is_system'      => (bool) $r[7],
                'is_active'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }
        DB::table('chart_of_accounts')->insert($payload);
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
