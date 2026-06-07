<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Operator-facing reminder fired by `tracking:reconcile-stuck`. Bundles
 * the entire stuck-pieces batch into one notification (rather than one
 * per piece) so the daily cron doesn't flood the admin inbox.
 *
 * Recipients are admin / branch_admin users — see TrackingReconcileStuckCommand
 * for the audience query. Database-channel-only; FCM for ops is out of
 * scope (admins typically watch the dashboard, not their phone).
 */
class StuckShipmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $stuckPieces
     *         Each row: { custody_id, source, piece_id, state, branch_id, since }
     */
    public function __construct(
        public array  $stuckPieces,
        public int    $cutoffDays,
        public string $cutoffAt,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'category'      => 'ops_stuck_shipments',
            'count'         => count($this->stuckPieces),
            'cutoff_days'   => $this->cutoffDays,
            'cutoff_at'     => $this->cutoffAt,
            // Cap the embedded sample to avoid bloating the JSON column —
            // a count + first ~20 rows is enough for the dashboard to
            // render a "view all" link.
            'sample_pieces' => array_slice($this->stuckPieces, 0, 20),
        ];
    }
}
