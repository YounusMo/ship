<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Models\Client;
use App\Modules\Tracking\Enums\TrackingEventKind;
use App\Modules\Tracking\Jobs\DispatchShipmentEventNotificationJob;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Notifications\ShipmentTrackingEventReceived;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DispatchShipmentEventNotificationJobTest extends TestCase
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

    public function test_fans_out_to_unique_clients_with_shipments_in_container(): void
    {
        // Seed: one container, three store_out_sea rows for two distinct
        // clients (one client has two shipments in the container — the
        // job should still notify them only once).
        $containerId = (int) DB::table('containers_sea')->insertGetId([
            'number' => 'TEST-FANOUT-' . uniqid(),
            'name'   => 'fanout test',
        ]);

        [$clientA, $clientB] = $this->seedClients(2);

        DB::table('store_out_sea')->insert([
            ['client_id' => $clientA->id, 'container_id' => $containerId, 'canceled' => null],
            ['client_id' => $clientA->id, 'container_id' => $containerId, 'canceled' => null], // duplicate same client
            ['client_id' => $clientB->id, 'container_id' => $containerId, 'canceled' => null],
        ]);

        $event = TrackingEvent::create([
            'shipment_source_table' => 'containers_sea',
            'shipment_source_id'    => $containerId,
            'kind'                  => TrackingEventKind::INTERNATIONAL,
            'event_type'            => 'GATE_IN',
            'occurred_at'           => now(),
            'city'                  => 'Shanghai',
            'country'               => 'CN',
            'raw_payload'           => ['location' => ['city' => 'Shanghai']],
            'translation_key'       => 'tracking::events.GATE_IN',
            'translation_params'    => ['city' => 'Shanghai'],
            'client_event_id'       => 'test:' . uniqid(),
            'is_customer_visible'   => true,
        ]);

        Notification::fake();
        (new DispatchShipmentEventNotificationJob($event->id))->handle();

        Notification::assertSentTo($clientA, ShipmentTrackingEventReceived::class);
        Notification::assertSentTo($clientB, ShipmentTrackingEventReceived::class);
        Notification::assertSentTimes(ShipmentTrackingEventReceived::class, 2);
    }

    public function test_skips_when_event_is_not_customer_visible(): void
    {
        $containerId = (int) DB::table('containers_sea')->insertGetId([
            'number' => 'TEST-INVISIBLE-' . uniqid(),
            'name'   => 'invisible test',
        ]);
        [$client] = $this->seedClients(1);
        DB::table('store_out_sea')->insert([
            ['client_id' => $client->id, 'container_id' => $containerId, 'canceled' => null],
        ]);

        $event = TrackingEvent::create([
            'shipment_source_table' => 'containers_sea',
            'shipment_source_id'    => $containerId,
            'kind'                  => TrackingEventKind::INTERNATIONAL,
            'event_type'            => 'SHIPMENT_DELETED',
            'occurred_at'           => now(),
            'raw_payload'           => [],
            'client_event_id'       => 'test:' . uniqid(),
            'is_customer_visible'   => false,
        ]);

        Notification::fake();
        (new DispatchShipmentEventNotificationJob($event->id))->handle();

        Notification::assertNothingSent();
    }

    public function test_skips_when_container_has_no_store_out_rows(): void
    {
        $containerId = (int) DB::table('containers_sea')->insertGetId([
            'number' => 'TEST-ORPHAN-' . uniqid(),
            'name'   => 'orphan container',
        ]);

        $event = TrackingEvent::create([
            'shipment_source_table' => 'containers_sea',
            'shipment_source_id'    => $containerId,
            'kind'                  => TrackingEventKind::INTERNATIONAL,
            'event_type'            => 'GATE_IN',
            'occurred_at'           => now(),
            'raw_payload'           => [],
            'client_event_id'       => 'test:' . uniqid(),
            'is_customer_visible'   => true,
        ]);

        Notification::fake();
        (new DispatchShipmentEventNotificationJob($event->id))->handle();

        Notification::assertNothingSent();
    }

    public function test_skips_when_event_was_deleted_between_dispatch_and_handle(): void
    {
        Notification::fake();
        // Pass an id that doesn't exist — job should noop silently rather
        // than throwing, so the queue driver doesn't keep retrying.
        (new DispatchShipmentEventNotificationJob(99_999_999))->handle();
        Notification::assertNothingSent();
    }

    /**
     * @return array<int, Client>
     */
    private function seedClients(int $count): array
    {
        $clients = [];
        for ($i = 0; $i < $count; $i++) {
            $id = DB::table('clients')->insertGetId([
                'code'            => 'TEST' . uniqid(),
                'name'            => 'Test Client ' . $i,
                'email'           => 'fan' . uniqid() . '@test.local',
                'phone'           => '0000',
                'country'         => 'LY',
                'branch'          => 1,
                'branch_txt'      => 'Test',
                'type'            => 'individual',
                'created_date'    => now()->toDateString(),
                'created_time'    => now()->toTimeString(),
                'created_by'      => 1,
                'password'        => bcrypt('test'),
                'lang'            => 'en',
                'notify_shipments'=> 1,
            ]);
            $clients[] = Client::query()->findOrFail($id);
        }
        return $clients;
    }
}
