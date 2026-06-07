<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Modules\Tracking\Enums\InternalEventType;
use App\Modules\Tracking\Enums\TrackingEventKind;
use App\Modules\Tracking\Exceptions\InvalidScanTransitionException;
use App\Modules\Tracking\Models\Sticker;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Services\InternalStateMachine;
use App\Modules\Tracking\Services\InternalTrackingService;
use App\Modules\Tracking\Services\RoleEventPolicy;
use App\Modules\Tracking\Services\Stickers\StickerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Scan endpoints for the employee mobile app.
 *
 *   POST /api/v1/employee/scan/resolve
 *     Body: { qr_payload?, sticker_id? }
 *     Returns sticker + (if assigned) the shipment context + allowed
 *     next event types so the UI can render only the valid buttons.
 *
 *   POST /api/v1/employee/scan/submit
 *     Body: { sticker_id, event_type, branch_id, notes?, photos?,
 *             to_branch_id?, client_event_id? }
 *     Headers: Idempotency-Key (recommended, dedup at DB layer)
 *     Records via InternalTrackingService. If the sticker is still
 *     unassigned and the event is RECEIVED_AT_HUB, the scan ALSO
 *     assigns the sticker to the piece in the same transaction.
 */
class ScanController extends Controller
{
    public function __construct(
        private readonly InternalTrackingService $tracking,
        private readonly InternalStateMachine $stateMachine,
        private readonly StickerService $stickers,
        private readonly RoleEventPolicy $rolePolicy,
    ) {
    }

    public function resolve(Request $request)
    {
        $request->validate([
            'qr_payload' => 'nullable|string|max:500',
            'sticker_id' => 'nullable|string|size:26',
            'branch_id'  => 'nullable|integer|min:1',
        ]);

        $stickerId = $this->extractStickerId(
            (string) ($request->input('qr_payload') ?? ''),
            (string) ($request->input('sticker_id') ?? ''),
        );
        if ($stickerId === null) {
            return response()->json([
                'type'    => 'invalid_qr',
                'message' => 'Could not parse a sticker id from the input.',
            ], 422);
        }

        $sticker = Sticker::query()->find($stickerId);
        if ($sticker === null) {
            return response()->json([
                'type' => 'unknown_sticker',
                'sticker_id' => $stickerId,
            ], 404);
        }

        if ($sticker->revoked_at !== null) {
            return response()->json([
                'type' => 'revoked_sticker',
                'sticker' => $sticker,
            ], 409);
        }

        $branchIdHint = $request->input('branch_id') !== null
            ? (int) $request->input('branch_id')
            : null;
        $userId = (int) $request->user()->id;

        if ($sticker->shipment_piece_id === null) {
            $candidates = [InternalEventType::RECEIVED_AT_HUB];
            $allowed = $branchIdHint !== null
                ? $this->rolePolicy->filterForUserOnBranch($candidates, $userId, $branchIdHint)
                : $candidates;

            return response()->json([
                'type'    => 'unassigned',
                'sticker' => $sticker,
                'allowed_event_types' => array_map(fn (InternalEventType $t) => $t->value, $allowed),
                'message' => 'Sticker is fresh. Submit RECEIVED_AT_HUB to associate it with a shipment.',
            ]);
        }

        $piece = DB::table('shipment_pieces')->where('id', $sticker->shipment_piece_id)->first();
        $sourceTable = $piece->source_table ?? null;
        $sourceId    = $piece->source_id    ?? null;

        $currentEventType = $sourceTable && $sourceId
            ? TrackingEvent::query()
                ->forShipment($sourceTable, (int) $sourceId)
                ->where('kind', TrackingEventKind::INTERNAL)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->value('event_type')
            : null;

        $allowed = $this->stateMachine->allowedNext($currentEventType);

        if ($branchIdHint !== null) {
            $allowed = $this->rolePolicy->filterForUserOnBranch($allowed, $userId, $branchIdHint);
        }

        return response()->json([
            'type'    => 'assigned',
            'sticker' => $sticker,
            'piece'   => $piece,
            'current_event_type'   => $currentEventType,
            'allowed_event_types'  => array_map(fn (InternalEventType $t) => $t->value, $allowed),
        ]);
    }

    public function submit(Request $request)
    {
        $request->validate([
            'sticker_id'      => 'required|string|size:26',
            'event_type'      => 'required|string',
            'branch_id'       => 'required|integer|min:1',
            'to_branch_id'    => 'nullable|integer|min:1',
            'notes'           => 'nullable|string|max:1000',
            'photos'          => 'nullable|array',
            'photos.*'        => 'string|max:500',
            'client_event_id' => 'nullable|string|max:191',
        ]);

        $eventType = InternalEventType::tryFrom((string) $request->input('event_type'));
        if ($eventType === null) {
            return response()->json([
                'type'    => 'invalid_event_type',
                'allowed' => array_map(fn ($c) => $c->value, InternalEventType::cases()),
            ], 422);
        }

        // Role gate. The branch scope ability is already enforced by
        // EnforceBranchScope middleware (the token must carry branch:N).
        // Here we additionally check the *role* on that branch is allowed
        // to submit this specific event type. See docs/MANUAL.md §21.2.
        $userId   = (int) $request->user()->id;
        $branchId = (int) $request->input('branch_id');
        $role     = $this->rolePolicy->roleOnBranch($userId, $branchId);
        if ($role === null) {
            return response()->json([
                'type'      => 'no_active_role',
                'message'   => 'You have no active assignment on this branch.',
                'branch_id' => $branchId,
            ], 403);
        }
        if (! $this->rolePolicy->allows($role, $eventType)) {
            return response()->json([
                'type'       => 'role_action_denied',
                'message'    => 'Your role does not permit this event type.',
                'role'       => $role->value,
                'event_type' => $eventType->value,
            ], 403);
        }

        $stickerId = (string) $request->input('sticker_id');
        $sticker = Sticker::query()->find($stickerId);
        if ($sticker === null) {
            return response()->json(['type' => 'unknown_sticker'], 404);
        }
        if ($sticker->revoked_at !== null) {
            return response()->json(['type' => 'revoked_sticker'], 409);
        }

        // Unassigned + first scan must be RECEIVED_AT_HUB and must carry
        // the piece id explicitly so we know what to bind to. (The
        // operator app picks the piece from a queue view in this flow.)
        if ($sticker->shipment_piece_id === null) {
            if ($eventType !== InternalEventType::RECEIVED_AT_HUB) {
                return response()->json([
                    'type'    => 'unassigned_first_scan',
                    'message' => 'Unassigned sticker — first scan must be RECEIVED_AT_HUB.',
                ], 422);
            }
            $request->validate(['shipment_piece_id' => 'required|integer|min:1']);
            $this->stickers->assignToPiece(
                $stickerId,
                (int) $request->input('shipment_piece_id'),
            );
            $sticker->refresh();
        }

        $piece = DB::table('shipment_pieces')->where('id', $sticker->shipment_piece_id)->first();
        if ($piece === null) {
            return response()->json(['type' => 'orphan_sticker'], 409);
        }

        try {
            $event = $this->tracking->recordScan([
                'shipment_source_table' => (string) $piece->source_table,
                'shipment_source_id'    => (int) $piece->source_id,
                'shipment_piece_id'     => (int) $piece->id,
                'event_type'            => $eventType,
                'branch_id'             => (int) $request->input('branch_id'),
                'to_branch_id'          => $request->input('to_branch_id') !== null
                    ? (int) $request->input('to_branch_id')
                    : null,
                'recorded_by_user_id'   => (int) $request->user()->id,
                'notes'                 => $request->input('notes'),
                'photos'                => $request->input('photos'),
                'client_event_id'       => $request->input('client_event_id'),
                'ip_address'            => $request->ip(),
                'user_agent'            => (string) $request->userAgent(),
            ]);
        } catch (InvalidScanTransitionException $e) {
            return response()->json([
                'type'    => 'invalid_transition',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'type'    => 'ok',
            'event'   => $event,
            'sticker' => $sticker,
        ], 201);
    }

    private function extractStickerId(string $qrPayload, string $stickerIdInput): ?string
    {
        if ($stickerIdInput !== '') {
            return $stickerIdInput;
        }
        if ($qrPayload === '') {
            return null;
        }
        $scheme = (string) config('tracking.stickers.qr_uri_scheme', 'shipflow://qr/');
        if (str_starts_with($qrPayload, $scheme)) {
            $candidate = substr($qrPayload, strlen($scheme));
            return $candidate !== '' ? $candidate : null;
        }
        // Fall back to "the input IS the id" for flexibility.
        return $qrPayload;
    }
}
