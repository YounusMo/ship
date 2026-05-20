<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use App\Notifications\Messages\FcmMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Fired when a client's shipment changes lifecycle state. The current
 * statuses we surface:
 *
 *   received  — operator marked a parcel/box received at our warehouse
 *               (store_sea / store_sky insert)
 *   shipped   — parcel was packed into a container (store_out_sea /
 *               store_out_sky insert)
 *   canceled  — operator canceled the row (store_*.canceled = 'true')
 *
 * The app deep-links to the shipment detail screen via the `mode` + `id`
 * fields in the FCM data payload.
 */
class ShipmentStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $mode,           // 'sea' | 'sky'
        public string $status,         // 'received' | 'shipped' | 'canceled'
        public int    $sourceId,       // store_*.id (received/canceled) or store_out_*.id (shipped)
        public string $sourceTable,    // 'store_sea' | 'store_sky' | 'store_out_sea' | 'store_out_sky'
        public ?string $transactionNumber = null,
        public ?int    $containerId = null,
        public ?int    $pieces = null,
        public ?float  $kg = null,
        public ?float  $cbm = null,
    ) {}

    public function via(mixed $notifiable): array
    {
        if ($notifiable instanceof \App\Models\Client && !$notifiable->notify_shipments) {
            return [];
        }
        return ['database', FcmChannel::class];
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'category'           => 'shipment',
            'mode'               => $this->mode,
            'status'             => $this->status,
            'source_id'          => $this->sourceId,
            'source_table'       => $this->sourceTable,
            'transaction_number' => $this->transactionNumber,
            'container_id'       => $this->containerId,
            'pieces'             => $this->pieces,
            'kg'                 => $this->kg,
            'cbm'                => $this->cbm,
        ];
    }

    public function toFcm(mixed $notifiable): FcmMessage
    {
        $modeLabel = $this->mode === 'sea' ? 'Sea' : 'Air';
        $title = match ($this->status) {
            'received'  => $modeLabel . ' shipment received',
            'shipped'   => $modeLabel . ' shipment dispatched',
            'canceled'  => $modeLabel . ' shipment canceled',
            default     => $modeLabel . ' shipment update',
        };
        $bits = array_filter([
            $this->pieces !== null && $this->pieces > 0 ? $this->pieces . ' pcs' : null,
            $this->kg     !== null && $this->kg > 0     ? number_format($this->kg, 2) . ' kg' : null,
            $this->cbm    !== null && $this->cbm > 0    ? number_format($this->cbm, 3) . ' cbm' : null,
            $this->transactionNumber,
        ]);
        $body = empty($bits) ? '' : implode(' · ', $bits);

        return FcmMessage::make($title, $body)->withData([
            'category'           => 'shipment',
            'mode'               => $this->mode,
            'status'             => $this->status,
            'source_id'          => $this->sourceId,
            'source_table'       => $this->sourceTable,
            'transaction_number' => (string) $this->transactionNumber,
            'container_id'       => (string) ($this->containerId ?? ''),
        ]);
    }
}
