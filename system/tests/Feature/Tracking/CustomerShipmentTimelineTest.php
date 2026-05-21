<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Modules\Tracking\Enums\TrackingEventKind;
use App\Modules\Tracking\Models\TrackingEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Exercises the customer-facing GET /api/shipments/{mode}/{id} endpoint
 * with the Phase 5 unified-timeline extension. Covers:
 *  - tracking payload is included only for the 'shipped' bucket
 *  - container-level INTERNATIONAL events join with shipment-level
 *    INTERNAL events in chronological order
 *  - white-label sanitization middleware THROWS in testing env when a
 *    forbidden pattern leaks (defense-in-depth proof)
 */
class CustomerShipmentTimelineTest extends TestCase
{
    use DatabaseTransactions;

    private int $clientId;
    private int $containerId;
    private int $shipmentId;
    private string $mode = 'sea';

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

        // Synthetic client (legacy clients table uses signed INT id).
        $this->clientId = (int) DB::table('clients')->insertGetId([
            'name'     => 'Timeline Test Client',
            'phone'    => '+218000000000',
            'deleted'  => '0',
        ]);

        // Synthetic container.
        $this->containerId = (int) DB::table('containers_sea')->insertGetId([
            'number' => 'TLINE-' . uniqid(),
            'name'   => 'timeline test container',
        ]);

        // Synthetic shipped row for the client, linked to the container.
        $this->shipmentId = (int) DB::table('store_out_sea')->insertGetId([
            'client_id'    => $this->clientId,
            'container_id' => $this->containerId,
        ]);
    }

    public function test_shipped_row_returns_tracking_payload_with_merged_events(): void
    {
        // Two container-level INTERNATIONAL events (older).
        TrackingEvent::create([
            'shipment_source_table' => 'containers_sea',
            'shipment_source_id'    => $this->containerId,
            'kind'                  => TrackingEventKind::INTERNATIONAL,
            'event_type'            => 'LOADED',
            'occurred_at'           => Carbon::now()->subDays(7),
            'city'                  => 'Shanghai',
            'country'               => 'CN',
            'is_customer_visible'   => true,
        ]);
        TrackingEvent::create([
            'shipment_source_table' => 'containers_sea',
            'shipment_source_id'    => $this->containerId,
            'kind'                  => TrackingEventKind::INTERNATIONAL,
            'event_type'            => 'DISCHARGED',
            'occurred_at'           => Carbon::now()->subDays(2),
            'city'                  => 'Misrata',
            'country'               => 'LY',
            'is_customer_visible'   => true,
        ]);
        // One shipment-level INTERNAL event (newer).
        TrackingEvent::create([
            'shipment_source_table' => 'store_out_sea',
            'shipment_source_id'    => $this->shipmentId,
            'kind'                  => TrackingEventKind::INTERNAL,
            'event_type'            => 'RECEIVED_AT_HUB',
            'occurred_at'           => Carbon::now()->subHour(),
            'is_customer_visible'   => true,
        ]);

        $clientModel = \App\Models\Client::query()->find($this->clientId);
        Sanctum::actingAs($clientModel);

        $resp = $this->getJson("/api/shipments/{$this->mode}/{$this->shipmentId}");
        $resp->assertStatus(200);
        $resp->assertJsonPath('bucket', 'shipped');
        $resp->assertJsonPath('tracking.counts.international', 2);
        $resp->assertJsonPath('tracking.counts.internal', 1);
        $resp->assertJsonPath('tracking.status', 'AT_HUB');

        $types = array_column($resp->json('tracking.timeline'), 'event_type');
        $this->assertEquals(['LOADED', 'DISCHARGED', 'RECEIVED_AT_HUB'], $types);

        // White-label proof: raw payload + recorded_by id never leak into
        // the timeline serialization.
        $first = $resp->json('tracking.timeline.0');
        $this->assertArrayNotHasKey('raw_payload', $first);
        $this->assertArrayNotHasKey('recorded_by_user_id', $first);
    }

    public function test_sanitization_middleware_throws_in_testing_env_on_forbidden_pattern(): void
    {
        // Force-leak a pattern by writing it into the row's free-text fields.
        // shipsgo is the canonical forbidden pattern per config.
        DB::table('store_out_sea')->where('id', $this->shipmentId)->update([
            // notes column doesn't exist on store_out_sea — pick a real text col.
            'number' => 'leak-shipsgo-' . uniqid(),
        ]);

        // The middleware throws on match in testing env — the
        // exception is wrapped by the framework into a 500 response.
        $clientModel = \App\Models\Client::query()->find($this->clientId);
        Sanctum::actingAs($clientModel);

        Config::set('app.debug', false);
        $resp = $this->getJson("/api/shipments/{$this->mode}/{$this->shipmentId}");
        // Framework catches the RuntimeException → 500.
        $this->assertEquals(500, $resp->status());
    }
}
