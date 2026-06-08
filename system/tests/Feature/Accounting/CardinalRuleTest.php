<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Http\Controllers\journalController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the cardinal rule: every revenue (4xxx) and expense (5xxx)
 * line written through journalController::record() must declare a
 * source — counterparty OR cost_object OR branch.
 *
 * See user requirement: "كل إيراد أو مصروف يجب أن يكون مربوطًا
 * بمصدره" — and MANUAL.md §11.
 */
class CardinalRuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function connectionsToTransact(): array
    {
        return ['mysql'];
    }

    protected function refreshApplication(): void
    {
        $envDb = trim((string) shell_exec("grep '^DB_DATABASE=' .env | cut -d= -f2")) ?: 'ship_system';
        putenv("DB_DATABASE={$envDb}");
        putenv('DB_CONNECTION=mysql');
        $_ENV['DB_DATABASE']      = $envDb;
        $_ENV['DB_CONNECTION']    = 'mysql';
        $_SERVER['DB_DATABASE']   = $envDb;
        $_SERVER['DB_CONNECTION'] = 'mysql';

        parent::refreshApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();
        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL not available: ' . $e->getMessage());
        }
    }

    public function test_revenue_line_without_source_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing source');

        (new journalController())->record([
            'entry_date' => date('Y-m-d'),
            'kind'       => 'test_cardinal',
            'lines'      => [
                ['account_code' => '4110', 'dr' => 0,   'cr' => 100, 'currency' => 'usd'],  // revenue, no source
                ['account_code' => '1000', 'dr' => 100, 'cr' => 0,   'currency' => 'usd'],  // cash
            ],
        ], enforcePeriod: false);
    }

    public function test_expense_line_without_source_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing source');

        (new journalController())->record([
            'entry_date' => date('Y-m-d'),
            'kind'       => 'test_cardinal',
            'lines'      => [
                ['account_code' => '5300', 'dr' => 100, 'cr' => 0,   'currency' => 'usd'],  // salary expense, no source
                ['account_code' => '1000', 'dr' => 0,   'cr' => 100, 'currency' => 'usd'],
            ],
        ], enforcePeriod: false);
    }

    public function test_revenue_with_counterparty_is_accepted(): void
    {
        $id = (new journalController())->record([
            'entry_date' => date('Y-m-d'),
            'kind'       => 'test_cardinal',
            'lines'      => [
                ['account_code' => '4110', 'dr' => 0,   'cr' => 100, 'currency' => 'usd',
                 'counterparty_type' => 'client', 'counterparty_id' => 999999],
                ['account_code' => '1000', 'dr' => 100, 'cr' => 0,   'currency' => 'usd'],
            ],
        ], enforcePeriod: false);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function test_expense_with_branch_is_accepted(): void
    {
        $id = (new journalController())->record([
            'entry_date' => date('Y-m-d'),
            'kind'       => 'test_cardinal',
            'lines'      => [
                ['account_code' => '5300', 'dr' => 100, 'cr' => 0, 'currency' => 'usd',
                 'branch_id' => 1],
                ['account_code' => '1000', 'dr' => 0,   'cr' => 100, 'currency' => 'usd'],
            ],
        ], enforcePeriod: false);
        $this->assertIsInt($id);
    }

    public function test_entry_level_branch_default_satisfies_the_rule(): void
    {
        $id = (new journalController())->record([
            'entry_date' => date('Y-m-d'),
            'kind'       => 'test_cardinal',
            'branch_id'  => 1,  // applies to every line
            'lines'      => [
                ['account_code' => '4110', 'dr' => 0,   'cr' => 100, 'currency' => 'usd'],
                ['account_code' => '1000', 'dr' => 100, 'cr' => 0,   'currency' => 'usd'],
            ],
        ], enforcePeriod: false);
        $this->assertIsInt($id);
    }

    public function test_asset_and_liability_lines_are_exempt(): void
    {
        // Pure asset ↔ liability rebalance — no revenue/expense involved,
        // so the cardinal rule shouldn't fire even with no source.
        $id = (new journalController())->record([
            'entry_date' => date('Y-m-d'),
            'kind'       => 'test_cardinal',
            'lines'      => [
                ['account_code' => '1000', 'dr' => 100, 'cr' => 0, 'currency' => 'usd'],
                ['account_code' => '2000', 'dr' => 0,   'cr' => 100, 'currency' => 'usd'],
            ],
        ], enforcePeriod: false);
        $this->assertIsInt($id);
    }
}
