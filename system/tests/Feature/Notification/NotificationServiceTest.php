<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Models\Client;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as Notifier;
use Tests\TestCase;

/**
 * Covers gap #5: notifications go through a single service that
 * respects clients.notify_* preferences.
 */
class NotificationServiceTest extends TestCase
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

    public function test_notification_sent_when_preference_enabled(): void
    {
        Notifier::fake();
        $client = $this->makeClient(['notify_shipments' => true]);

        $service = app(NotificationService::class);
        $sent = $service->notifyClient(
            $client,
            NotificationService::KIND_SHIPMENTS,
            new FakeShipmentNotification(),
        );

        $this->assertTrue($sent);
        Notifier::assertSentTo($client, FakeShipmentNotification::class);
    }

    public function test_notification_muted_when_preference_disabled(): void
    {
        Notifier::fake();
        $client = $this->makeClient(['notify_shipments' => false]);

        $service = app(NotificationService::class);
        $sent = $service->notifyClient(
            $client,
            NotificationService::KIND_SHIPMENTS,
            new FakeShipmentNotification(),
        );

        $this->assertFalse($sent);
        Notifier::assertNothingSent();
    }

    public function test_unknown_kind_is_allowed_through(): void
    {
        Notifier::fake();
        $client = $this->makeClient();

        $service = app(NotificationService::class);
        $sent = $service->notifyClient(
            $client,
            'unknown_kind',
            new FakeShipmentNotification(),
        );

        $this->assertTrue($sent);
        Notifier::assertSentTo($client, FakeShipmentNotification::class);
    }

    public function test_fan_out_filters_per_recipient(): void
    {
        Notifier::fake();
        $enabled = $this->makeClient(['notify_shipments' => true]);
        $disabled = $this->makeClient(['notify_shipments' => false]);
        $alsoEnabled = $this->makeClient(['notify_shipments' => true]);

        $service = app(NotificationService::class);
        $count = $service->notifyClients(
            [$enabled, $disabled, $alsoEnabled],
            NotificationService::KIND_SHIPMENTS,
            new FakeShipmentNotification(),
        );

        $this->assertSame(2, $count);
        Notifier::assertSentTo($enabled, FakeShipmentNotification::class);
        Notifier::assertSentTo($alsoEnabled, FakeShipmentNotification::class);
        Notifier::assertNotSentTo($disabled, FakeShipmentNotification::class);
    }

    private function makeClient(array $overrides = []): Client
    {
        $uniq = uniqid();
        // Legacy clients table — split date/time, no created_at/updated_at.
        $row = array_merge([
            'code'         => 'NS-' . $uniq,
            'name'         => 'Notify Test',
            'email'        => "ns-{$uniq}@example.test",
            'phone'        => '',
            'country'      => '',
            'branch'       => '0',
            'branch_txt'   => '',
            'type'         => 'Person',
            'balance_usd'  => 0,
            'balance_eur'  => 0,
            'balance_den'  => 0,
            'balance_cny'  => 0,
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
            'created_by'   => '1',
            'password'     => bcrypt('xxxx'),
            'deleted'      => 'false',
            'not_active'   => 'false',
            'lang'         => 'en',
            'notify_transactions' => true,
            'notify_shipments'    => true,
            'notify_receipts'     => true,
        ], $overrides);

        // The legacy clients table doesn't have an `updated_at` column,
        // so Eloquent's save() fails on its automatic timestamp write.
        // Drop straight into the table and re-fetch via the model.
        $id = DB::table('clients')->insertGetId($row);
        return Client::query()->findOrFail($id);
    }
}

/**
 * Tiny test-only notification class — the service doesn't care which
 * class it dispatches, only that it dispatches.
 */
class FakeShipmentNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toArray(mixed $notifiable): array
    {
        return ['fake' => true];
    }
}
