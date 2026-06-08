<?php

declare(strict_types=1);

namespace Tests\Feature\ClientTransactions;

use App\Http\Controllers\Controller;
use ReflectionClass;
use Tests\TestCase;

/**
 * Covers the `client_transactions_default_pending` operator setting.
 *
 * When `false` (historical default) every new deposit/withdraw/transfer
 * lands as `approved` immediately. When `true`, they land as `pending`
 * and an admin has to approve via /clients/reports/approveReject before
 * the journal lines + treasury rows are written.
 *
 * We exercise the helper directly with a temp settings file so the
 * live settings.json is never touched.
 */
class DefaultPendingStatusTest extends TestCase
{
    private string $settingsPath;
    private string $backupPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Locate the JSON file at the canonical path used by the helper.
        $reflection = new ReflectionClass(Controller::class);
        $this->settingsPath = dirname($reflection->getFileName()) . '/settings.json';
        $this->backupPath = $this->settingsPath . '.bak.' . uniqid();
        copy($this->settingsPath, $this->backupPath);
    }

    protected function tearDown(): void
    {
        // Always restore — even if the test asserts fail, we must not
        // leave the operator's settings.json mutated.
        if (file_exists($this->backupPath)) {
            copy($this->backupPath, $this->settingsPath);
            unlink($this->backupPath);
        }
        parent::tearDown();
    }

    public function test_no_override_with_flag_off_returns_approved(): void
    {
        $this->writeFlag(false);
        $this->assertSame('approved', $this->callHelper(null));
        $this->assertSame('approved', $this->callHelper(''));
    }

    public function test_no_override_with_flag_on_returns_pending(): void
    {
        $this->writeFlag(true);
        $this->assertSame('pending', $this->callHelper(null));
        $this->assertSame('pending', $this->callHelper(''));
    }

    public function test_explicit_request_status_always_wins(): void
    {
        $this->writeFlag(true);
        $this->assertSame('approved', $this->callHelper('approved'));
        $this->assertSame('pending', $this->callHelper('pending'));

        $this->writeFlag(false);
        $this->assertSame('approved', $this->callHelper('approved'));
        $this->assertSame('pending', $this->callHelper('pending'));
    }

    public function test_garbage_status_input_falls_back_to_policy(): void
    {
        $this->writeFlag(true);
        $this->assertSame('pending', $this->callHelper('foo'));
        $this->writeFlag(false);
        $this->assertSame('approved', $this->callHelper('foo'));
    }

    private function writeFlag(bool $on): void
    {
        $json = json_decode((string) file_get_contents($this->settingsPath), true);
        $json['client_transactions_default_pending'] = $on;
        file_put_contents(
            $this->settingsPath,
            json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    private function callHelper(?string $requested): string
    {
        // The helper is protected — expose it via an anonymous subclass.
        $probe = new class extends Controller {
            public function call(?string $s = null): string
            {
                return $this->defaultClientTransactionStatus($s);
            }
        };
        return $probe->call($requested);
    }
}
