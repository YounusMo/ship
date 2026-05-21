<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Tracking\Enums\BranchRole;
use App\Modules\Tracking\Enums\BranchStaffRole;
use App\Modules\Tracking\Enums\InternalEventType;
use App\Modules\Tracking\Enums\TrackingEventKind;
use App\Modules\Tracking\Models\Branch;
use App\Modules\Tracking\Models\BranchStaff;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Services\InternalTrackingService;
use App\Modules\Tracking\Services\Stickers\StickerService;
use App\Modules\Tracking\Services\UnifiedTimelineService;
use App\Modules\Tracking\Enums\ShipmentMode;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 *   php artisan tracking:e2e-walk [--cleanup]
 *
 * Drives one synthetic shipment Yiwu (Shanghai loading) → Misrata port →
 * Tripoli hub → Sirte spoke → customer pickup through every event type
 * in the system. Proves the whole stack works together — no UI, just
 * the backend.
 *
 * Steps walked (11 total):
 *   1.  Seed: 1 client, 1 hub branch, 1 spoke branch, 1 user (assigned
 *       to hub), 1 sticker batch with 1 sticker, 1 container in
 *       containers_sea, 1 store_out_sea row linked to the container,
 *       1 shipment_pieces row.
 *   2.  Insert 6 INTERNATIONAL events (GATE_IN → LOADED → DEPARTED →
 *       IN_TRANSIT → ARRIVED → DISCHARGED) under containers_sea.
 *   3.  Assign sticker → piece, record RECEIVED_AT_HUB scan.
 *   4.  Record IN_TRANSIT_INTERNAL (hub → spoke).
 *   5.  Record RECEIVED_AT_BRANCH (now at spoke).
 *   6.  Record READY_FOR_PICKUP.
 *   7.  Record DELIVERED_TO_CUSTOMER.
 *   8.  Print the final UnifiedTimelineService output for the customer
 *       view of this shipment.
 *
 * --cleanup wipes the synthetic rows when done so the demo can be
 * re-run. Without it, the rows persist for the operator to inspect.
 */
class TrackingE2EWalkCommand extends Command
{
    protected $signature = 'tracking:e2e-walk
                            {--cleanup : Delete all rows created by the walk after printing the timeline}';

    protected $description = 'Seed one shipment and walk it through all 11 tracking events end-to-end.';

    /** @var array<string, mixed> */
    private array $seeded = [];

    public function handle(
        InternalTrackingService $tracking,
        StickerService $stickers,
        UnifiedTimelineService $timeline,
    ): int {
        $this->info('Seeding...');
        $this->seed();
        $this->table(
            ['key', 'id'],
            collect($this->seeded)->map(fn ($v, $k) => [$k, (string) $v])->values()->all(),
        );

        $this->info('Writing 6 ShipsGo-style INTERNATIONAL events on the container...');
        $this->seedInternationalEvents();

        $this->info('Assigning sticker → piece...');
        $stickers->assignToPiece($this->seeded['sticker_id'], $this->seeded['piece_id']);

        $hubId    = (int) $this->seeded['hub_branch_id'];
        $spokeId  = (int) $this->seeded['spoke_branch_id'];
        $userId   = (int) $this->seeded['user_id'];
        $sourceTb = 'store_out_sea';
        $sourceId = (int) $this->seeded['shipment_id'];
        $pieceId  = (int) $this->seeded['piece_id'];

        $scans = [
            [InternalEventType::RECEIVED_AT_HUB,        $hubId,   null,      'Received at Tripoli hub'],
            [InternalEventType::IN_TRANSIT_INTERNAL,    $hubId,   $spokeId,  'Dispatched to Sirte spoke'],
            [InternalEventType::RECEIVED_AT_BRANCH,     $spokeId, null,      'Arrived at Sirte spoke'],
            [InternalEventType::READY_FOR_PICKUP,       $spokeId, null,      'Awaiting customer pickup'],
            [InternalEventType::DELIVERED_TO_CUSTOMER,  $spokeId, null,      'Handed to customer'],
        ];

        foreach ($scans as [$type, $branch, $toBranch, $note]) {
            $this->line("  scan: <fg=cyan>{$type->value}</> @ branch {$branch}");
            $tracking->recordScan([
                'shipment_source_table' => $sourceTb,
                'shipment_source_id'    => $sourceId,
                'shipment_piece_id'     => $pieceId,
                'event_type'            => $type,
                'branch_id'             => $branch,
                'to_branch_id'          => $toBranch,
                'recorded_by_user_id'   => $userId,
                'notes'                 => $note,
            ]);
        }

        $this->newLine();
        $this->info('Final unified timeline (customer view):');
        $payload = $timeline->for(
            ShipmentMode::SEA,
            $sourceId,
            (int) $this->seeded['container_id'],
        );

        $this->line("  status = <fg=green>{$payload['status']}</>");
        $this->line("  international events: {$payload['counts']['international']}   internal events: {$payload['counts']['internal']}");
        $this->newLine();
        $this->table(
            ['when', 'kind', 'event', 'where'],
            collect($payload['timeline'])->map(fn ($e) => [
                $e['occurred_at'] ?? '',
                $e['kind'],
                $e['event_type'],
                trim(($e['city'] ?? '') . ' ' . ($e['country'] ?? '')),
            ])->all(),
        );

        if ($this->option('cleanup')) {
            $this->newLine();
            $this->info('Cleaning up...');
            $this->cleanup();
            $this->line('  done.');
        } else {
            $this->newLine();
            $this->comment('Rows left in place. Re-run with --cleanup to remove them.');
        }

        return self::SUCCESS;
    }

    private function seed(): void
    {
        $uniq = uniqid();
        $now = now();

        $userId = (int) DB::table('users')->insertGetId([
            'name'       => "e2e walk {$uniq}",
            'email'      => "e2e-{$uniq}@example.local",
            'password'   => bcrypt('x'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $hub = Branch::create([
            'code' => "E2E-HUB-{$uniq}", 'name' => 'Tripoli Hub', 'name_en' => 'Tripoli Hub',
            'role' => BranchRole::HUB,   'country' => 'LY', 'city' => 'Tripoli',
        ]);
        $spoke = Branch::create([
            'code' => "E2E-SPK-{$uniq}", 'name' => 'Sirte Spoke', 'name_en' => 'Sirte Spoke',
            'role' => BranchRole::SPOKE, 'country' => 'LY', 'city' => 'Sirte',
        ]);
        BranchStaff::create([
            'branch_id' => $hub->id,
            'user_id'   => $userId,
            'role'      => BranchStaffRole::RECEIVER,
        ]);
        BranchStaff::create([
            'branch_id' => $spoke->id,
            'user_id'   => $userId,
            'role'      => BranchStaffRole::RECEIVER,
        ]);

        $clientId = (int) DB::table('clients')->insertGetId([
            'name'    => "E2E Client {$uniq}",
            'phone'   => '+218000000000',
            'deleted' => '0',
        ]);

        $containerId = (int) DB::table('containers_sea')->insertGetId([
            'number' => "E2E-CONT-{$uniq}",
            'name'   => 'e2e walk container',
        ]);
        $shipmentId = (int) DB::table('store_out_sea')->insertGetId([
            'client_id'    => $clientId,
            'container_id' => $containerId,
        ]);
        $pieceId = (int) DB::table('shipment_pieces')->insertGetId([
            'tracking_code' => "E2E-PIECE-{$uniq}",
            'source_table'  => 'store_out_sea',
            'source_id'     => $shipmentId,
            'client_id'     => $clientId,
            'piece_index'   => 1,
            'piece_total'   => 1,
            'status'        => 'active',
            'created_by'    => $userId,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $batch = app(StickerService::class)->issueBatch(1, $userId, "e2e {$uniq}");
        $stickerId = (string) DB::table('stickers')->where('batch_id', $batch->id)->value('id');

        $this->seeded = [
            'user_id'         => $userId,
            'client_id'       => $clientId,
            'hub_branch_id'   => $hub->id,
            'spoke_branch_id' => $spoke->id,
            'container_id'    => $containerId,
            'shipment_id'     => $shipmentId,
            'piece_id'        => $pieceId,
            'batch_id'        => $batch->id,
            'sticker_id'      => $stickerId,
        ];
    }

    private function seedInternationalEvents(): void
    {
        $containerId = (int) $this->seeded['container_id'];
        $base = Carbon::now()->subDays(10);

        $events = [
            ['GATE_IN',    0,   'Shanghai', 'CN'],
            ['LOADED',     6,   'Shanghai', 'CN'],
            ['DEPARTED',   12,  'Shanghai', 'CN'],
            ['IN_TRANSIT', 96,  null,       null],
            ['ARRIVED',    216, 'Misrata',  'LY'],
            ['DISCHARGED', 230, 'Misrata',  'LY'],
        ];
        foreach ($events as $i => [$type, $hours, $city, $country]) {
            TrackingEvent::create([
                'shipment_source_table' => 'containers_sea',
                'shipment_source_id'    => $containerId,
                'kind'                  => TrackingEventKind::INTERNATIONAL,
                'event_type'            => $type,
                'occurred_at'           => $base->copy()->addHours($hours),
                'city'                  => $city,
                'country'               => $country,
                'is_customer_visible'   => true,
                'client_event_id'       => "e2e:intl:{$containerId}:{$type}:{$i}",
            ]);
        }
    }

    private function cleanup(): void
    {
        $s = $this->seeded;
        DB::table('employee_action_logs')->where('user_id', $s['user_id'])->delete();
        DB::table('custody_events')
            ->where('shipment_source_table', 'store_out_sea')
            ->where('shipment_source_id', $s['shipment_id'])
            ->delete();
        DB::table('tracking_events')
            ->where(function ($q) use ($s) {
                $q->where(function ($qq) use ($s) {
                    $qq->where('shipment_source_table', 'store_out_sea')
                       ->where('shipment_source_id', $s['shipment_id']);
                })->orWhere(function ($qq) use ($s) {
                    $qq->where('shipment_source_table', 'containers_sea')
                       ->where('shipment_source_id', $s['container_id']);
                });
            })->delete();
        DB::table('shipment_pieces')->where('id', $s['piece_id'])->delete();
        DB::table('store_out_sea')->where('id', $s['shipment_id'])->delete();
        DB::table('containers_sea')->where('id', $s['container_id'])->delete();
        DB::table('stickers')->where('batch_id', $s['batch_id'])->delete();
        DB::table('sticker_batches')->where('id', $s['batch_id'])->delete();
        DB::table('branch_staff')->where('user_id', $s['user_id'])->delete();
        DB::table('tracking_branches')->whereIn('id', [$s['hub_branch_id'], $s['spoke_branch_id']])->delete();
        DB::table('clients')->where('id', $s['client_id'])->delete();
        DB::table('personal_access_tokens')->where('tokenable_id', $s['user_id'])->where('tokenable_type', 'App\\Models\\User')->delete();
        DB::table('users')->where('id', $s['user_id'])->delete();
    }
}
