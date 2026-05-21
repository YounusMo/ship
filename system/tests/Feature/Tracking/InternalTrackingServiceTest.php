<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Modules\Tracking\Enums\BranchRole;
use App\Modules\Tracking\Enums\InternalEventType;
use App\Modules\Tracking\Enums\ShipmentMode;
use App\Modules\Tracking\Enums\TrackingEventKind;
use App\Modules\Tracking\Exceptions\InvalidScanTransitionException;
use App\Modules\Tracking\Models\Branch;
use App\Modules\Tracking\Models\CustodyEvent;
use App\Modules\Tracking\Models\EmployeeActionLog;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Services\InternalTrackingService;
use App\Modules\Tracking\Services\StatusComputer;
use App\Modules\Tracking\Services\UnifiedTimelineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Runs against the real MySQL dev DB (DatabaseTransactions rolls back each
 * test) because the project's legacy migrations reference CodeIgniter-era
 * tables that aren't recreatable in the SQLite in-memory test fixture.
 *
 * Skip-on-no-mysql: in CI without a MySQL server, the test is skipped
 * rather than failed.
 */
class InternalTrackingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private InternalTrackingService $service;
    private int $hubBranchId;
    private int $spokeBranchId;
    private int $userId;
    private string $sourceTable = 'store_out_sea';
    private int $sourceId;

    /**
     * Tell DatabaseTransactions to wrap the mysql connection (the
     * phpunit.xml default is sqlite in-memory, which can't carry the
     * project's legacy migrations).
     */
    protected function connectionsToTransact(): array
    {
        return ['mysql'];
    }

    /**
     * Override the test app's database config BEFORE Laravel boots, so
     * the mysql connection has its real database name instead of the
     * `:memory:` value forced by phpunit.xml for the sqlite fixture.
     */
    protected function refreshApplication(): void
    {
        $envDb = trim((string) shell_exec("grep '^DB_DATABASE=' .env | cut -d= -f2")) ?: 'ship_system';
        putenv("DB_DATABASE={$envDb}");
        putenv('DB_CONNECTION=mysql');
        $_ENV['DB_DATABASE']     = $envDb;
        $_ENV['DB_CONNECTION']   = 'mysql';
        $_SERVER['DB_DATABASE']  = $envDb;
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

        $this->service = app(InternalTrackingService::class);

        // Synthetic user (FK target for recorded_by_user_id). Bypass
        // mass-assignment guards since we just need an id that exists.
        $this->userId = (int) DB::table('users')->insertGetId([
            'name'       => 'Scan Test User',
            'email'      => 'scan-test+' . uniqid() . '@example.com',
            'password'   => bcrypt('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hub = Branch::create([
            'code'    => 'TST-HUB-' . uniqid(),
            'name'    => 'Test Hub',
            'role'    => BranchRole::HUB,
            'country' => 'LY',
            'city'    => 'Tripoli',
        ]);
        $spoke = Branch::create([
            'code'    => 'TST-SPK-' . uniqid(),
            'name'    => 'Test Spoke',
            'role'    => BranchRole::SPOKE,
            'country' => 'LY',
            'city'    => 'Sirte',
        ]);
        $this->hubBranchId   = $hub->id;
        $this->spokeBranchId = $spoke->id;

        // Synthetic shipment id — we don't actually have a row in
        // store_out_sea since the FK is polymorphic-by-convention (no FK
        // constraint on tracking_events.shipment_source_id), and these
        // tests focus on the event stream, not the legacy shipment table.
        $this->sourceId = random_int(900_000_000, 999_999_999);
    }

    public function test_first_scan_must_be_received_at_hub(): void
    {
        $this->expectException(InvalidScanTransitionException::class);

        $this->service->recordScan([
            'shipment_source_table' => $this->sourceTable,
            'shipment_source_id'    => $this->sourceId,
            'event_type'            => InternalEventType::RECEIVED_AT_BRANCH,
            'branch_id'             => $this->spokeBranchId,
            'recorded_by_user_id'   => $this->userId,
        ]);
    }

    public function test_happy_path_hub_to_delivered(): void
    {
        $this->recordScan(InternalEventType::RECEIVED_AT_HUB, $this->hubBranchId);
        $this->recordScan(InternalEventType::IN_TRANSIT_INTERNAL, $this->hubBranchId, ['to_branch_id' => $this->spokeBranchId]);
        $this->recordScan(InternalEventType::RECEIVED_AT_BRANCH, $this->spokeBranchId);
        $this->recordScan(InternalEventType::READY_FOR_PICKUP, $this->spokeBranchId);
        $this->recordScan(InternalEventType::DELIVERED_TO_CUSTOMER, $this->spokeBranchId);

        // tracking_events stream
        $events = TrackingEvent::query()
            ->forShipment($this->sourceTable, $this->sourceId)
            ->orderBy('id')
            ->get();
        $this->assertCount(5, $events);
        $this->assertEquals('DELIVERED_TO_CUSTOMER', $events->last()->event_type);
        $this->assertTrue($events->every(fn ($e) => $e->kind === TrackingEventKind::INTERNAL));

        // custody_events stream (one per scan since all are hand-offs)
        $custody = CustodyEvent::query()
            ->where('shipment_source_table', $this->sourceTable)
            ->where('shipment_source_id', $this->sourceId)
            ->orderBy('id')
            ->get();
        $this->assertCount(5, $custody);
        $this->assertEquals('DELIVERED_TO_CUSTOMER', $custody->last()->event_type->value);

        // employee_action_logs — one per scan, with the tracking_event_id linked
        $logs = EmployeeActionLog::query()
            ->where('entity_type', $this->sourceTable)
            ->where('entity_id', (string) $this->sourceId)
            ->orderBy('id')
            ->get();
        $this->assertCount(5, $logs);
        $this->assertEquals('scan.delivered_to_customer', $logs->last()->action);

        // Status derived correctly
        $status = app(StatusComputer::class)->derive($events);
        $this->assertEquals('DELIVERED', $status);
    }

    public function test_branch_scope_blocks_wrong_branch(): void
    {
        $this->recordScan(InternalEventType::RECEIVED_AT_HUB, $this->hubBranchId);
        $this->recordScan(InternalEventType::IN_TRANSIT_INTERNAL, $this->hubBranchId, ['to_branch_id' => $this->spokeBranchId]);

        // Shipment is now en route to spoke — receiving it at the hub is a
        // branch scope violation (correct event type, wrong branch).
        $this->expectException(InvalidScanTransitionException::class);

        $this->service->recordScan([
            'shipment_source_table' => $this->sourceTable,
            'shipment_source_id'    => $this->sourceId,
            'event_type'            => InternalEventType::RECEIVED_AT_BRANCH,
            'branch_id'             => $this->hubBranchId,
            'recorded_by_user_id'   => $this->userId,
        ]);
    }

    public function test_unified_timeline_merges_intl_and_internal_in_order(): void
    {
        // Two ShipsGo events (older), then two employee scans (newer)
        TrackingEvent::create([
            'shipment_source_table' => $this->sourceTable,
            'shipment_source_id'    => $this->sourceId,
            'kind'                  => TrackingEventKind::INTERNATIONAL,
            'event_type'            => 'LOADED',
            'occurred_at'           => Carbon::now()->subDays(5),
            'city'                  => 'Shanghai',
            'country'               => 'CN',
            'is_customer_visible'   => true,
            'translation_key'       => null,
        ]);
        TrackingEvent::create([
            'shipment_source_table' => $this->sourceTable,
            'shipment_source_id'    => $this->sourceId,
            'kind'                  => TrackingEventKind::INTERNATIONAL,
            'event_type'            => 'ARRIVED',
            'occurred_at'           => Carbon::now()->subDays(2),
            'city'                  => 'Misrata',
            'country'               => 'LY',
            'is_customer_visible'   => true,
            'translation_key'       => null,
        ]);
        $this->recordScan(InternalEventType::RECEIVED_AT_HUB, $this->hubBranchId);
        $this->recordScan(InternalEventType::IN_TRANSIT_INTERNAL, $this->hubBranchId, ['to_branch_id' => $this->spokeBranchId]);

        $payload = app(UnifiedTimelineService::class)->for(ShipmentMode::SEA, $this->sourceId);

        $this->assertEquals('IN_TRANSIT_INTERNAL', $payload['status']);
        $this->assertEquals(2, $payload['counts']['international']);
        $this->assertEquals(2, $payload['counts']['internal']);

        $types = array_column($payload['timeline'], 'event_type');
        $this->assertEquals(
            ['LOADED', 'ARRIVED', 'RECEIVED_AT_HUB', 'IN_TRANSIT_INTERNAL'],
            $types,
        );

        // White-label check — raw_payload should NOT leak into serialized output
        $first = $payload['timeline'][0];
        $this->assertArrayNotHasKey('raw_payload', $first);
        $this->assertArrayNotHasKey('recorded_by_user_id', $first);
    }

    private function recordScan(InternalEventType $type, int $branchId, array $extra = []): TrackingEvent
    {
        return $this->service->recordScan(array_merge([
            'shipment_source_table' => $this->sourceTable,
            'shipment_source_id'    => $this->sourceId,
            'event_type'            => $type,
            'branch_id'             => $branchId,
            'recorded_by_user_id'   => $this->userId,
        ], $extra));
    }
}
