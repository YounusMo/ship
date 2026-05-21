<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Modules\Tracking\Jobs\ProcessShipsGoWebhook;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShipsGoWebhookTest extends TestCase
{
    use DatabaseTransactions;

    private string $secret = 'test-shipsgo-secret-' . __CLASS__;
    private string $shipmentRef = 'ACME-WEBHOOK-TEST-1';
    private int $shipmentRowId;

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

        // Bind the webhook secret for this test class only — the binding
        // is rebuilt per test because the singleton is also rebuilt.
        Config::set('tracking.shipsgo.webhook_secret', $this->secret);
        $this->app->forgetInstance(\App\Modules\Tracking\Services\ShipsGo\ShipsGoWebhookVerifier::class);

        // Seed one container row so the job's resolveShipment() lookup
        // finds it. Per the schema, ShipsGo references map to
        // containers_sea.number — there is no per-shipment tracking_number.
        $this->shipmentRowId = (int) DB::table('containers_sea')->insertGetId([
            'number' => $this->shipmentRef,
            'name'   => 'webhook test container',
        ]);
    }

    /** ShipsGo v2 envelope used in all signed-path tests below. */
    private function v2Envelope(string $name, array $shipmentOverrides = []): array
    {
        return [
            'event' => [
                'id'   => 'shipsgo-evt-' . uniqid(),
                'name' => $name,
                'triggered_by' => ['name' => 'test', 'email' => 'test@example.com'],
            ],
            'shipment' => array_merge([
                'id'               => random_int(1, 10_000_000),
                'reference'        => $this->shipmentRef,
                'container_number' => $this->shipmentRef,
                'booking_number'   => null,
            ], $shipmentOverrides),
        ];
    }

    public function test_rejects_invalid_signature(): void
    {
        $body = json_encode($this->v2Envelope('OCEAN.SHIPMENTS.CONTAINER_GATE_IN'));

        $resp = $this->postJson(
            '/api/v1/webhooks/shipsgo',
            json_decode($body, true),
            ['X-Shipsgo-Webhook-Signature' => 'not-the-right-signature'],
        );

        $resp->assertStatus(401);
        $this->assertSame(0, WebhookDelivery::query()->count());
    }

    public function test_accepts_valid_signature_and_dispatches_job(): void
    {
        Bus::fake([ProcessShipsGoWebhook::class]);

        $payload = $this->v2Envelope('OCEAN.SHIPMENTS.CONTAINER_GATE_IN');
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $this->secret);

        $resp = $this->call(
            'POST',
            '/api/v1/webhooks/shipsgo',
            [],
            [],
            [],
            [
                'HTTP_X-Shipsgo-Webhook-Signature' => $signature,
                'HTTP_X-Shipsgo-Webhook-Id'        => $payload['event']['id'],
                'HTTP_X-Shipsgo-Webhook-Name'      => $payload['event']['name'],
                'CONTENT_TYPE'                     => 'application/json',
            ],
            $body,
        );

        $resp->assertStatus(200);
        $this->assertSame(1, WebhookDelivery::query()->count());
        Bus::assertDispatched(ProcessShipsGoWebhook::class);
    }

    public function test_duplicate_delivery_returns_200_without_dispatching_again(): void
    {
        Bus::fake([ProcessShipsGoWebhook::class]);

        $payload = $this->v2Envelope('OCEAN.SHIPMENTS.CONTAINER_LOADED');
        $body      = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $this->secret);

        // First call lands a row.
        $first = $this->call('POST', '/api/v1/webhooks/shipsgo', [], [], [],
            ['HTTP_X-Shipsgo-Webhook-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $body,
        );
        $first->assertStatus(200);

        // Second call is a perfect replay — same body, same signature.
        $second = $this->call('POST', '/api/v1/webhooks/shipsgo', [], [], [],
            ['HTTP_X-Shipsgo-Webhook-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $body,
        );
        $second->assertStatus(200);
        $second->assertJsonFragment(['deduped' => true]);

        $this->assertSame(1, WebhookDelivery::query()->count());
        Bus::assertDispatchedTimes(ProcessShipsGoWebhook::class, 1);
    }

    public function test_job_creates_tracking_event(): void
    {
        $payload = $this->v2Envelope('OCEAN.SHIPMENTS.CONTAINER_ARRIVED');
        // v2 envelope is itself the event; the location lives on the
        // event-row when present. Add it here so the row carries city/country.
        $payload['location']  = ['city' => 'Misrata', 'country' => 'LY'];
        $payload['timestamp'] = '2026-05-21T10:30:00Z';

        $delivery = WebhookDelivery::create([
            'provider'           => 'shipsgo',
            'external_event_id'  => $payload['event']['id'],
            'event_type'         => $payload['event']['name'],
            'payload'            => $payload,
            'signature_verified' => true,
            'received_at'        => now(),
        ]);

        (new ProcessShipsGoWebhook($delivery->id))->handle();

        $delivery->refresh();
        $this->assertNotNull($delivery->processed_at);
        $this->assertNull($delivery->processing_error);

        $event = TrackingEvent::query()
            ->where('shipment_source_table', 'containers_sea')
            ->where('shipment_source_id', $this->shipmentRowId)
            ->where('event_type', 'OCEAN.SHIPMENTS.CONTAINER_ARRIVED')
            ->first();
        $this->assertNotNull($event);
        $this->assertEquals('Misrata', $event->city);
        $this->assertEquals('LY', $event->country);
        $this->assertEquals('INTERNATIONAL', $event->kind->value);
    }

    public function test_job_is_idempotent_on_replay(): void
    {
        $payload = $this->v2Envelope('OCEAN.SHIPMENTS.CONTAINER_DISCHARGED');
        $payload['timestamp'] = '2026-05-21T11:00:00Z';

        $delivery = WebhookDelivery::create([
            'provider'           => 'shipsgo',
            'external_event_id'  => $payload['event']['id'],
            'event_type'         => $payload['event']['name'],
            'payload'            => $payload,
            'signature_verified' => true,
            'received_at'        => now(),
        ]);

        (new ProcessShipsGoWebhook($delivery->id))->handle();
        // Reset processed_at to simulate a re-run path (in real life
        // multiple deliveries could try to insert the same client_event_id).
        $delivery->update(['processed_at' => null]);
        (new ProcessShipsGoWebhook($delivery->id))->handle();

        $count = TrackingEvent::query()
            ->where('shipment_source_table', 'containers_sea')
            ->where('shipment_source_id', $this->shipmentRowId)
            ->where('event_type', 'OCEAN.SHIPMENTS.CONTAINER_DISCHARGED')
            ->count();

        $this->assertSame(1, $count, 'Re-running the job must not create a duplicate event');
    }
}
