<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Notifications;

use App\Models\Client;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Messages\FcmMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Customer-visible tracking event landed against a container the client
 * has a shipment in. Mirrors the ShipmentStatusChanged notification but
 * fires on the unified-tracking timeline (international + internal),
 * not the legacy received/shipped/canceled lifecycle.
 *
 * The notification deep-links to the shipment detail screen via the
 * `source_table` + `source_id` in the FCM data payload — the customer
 * app already knows how to render those.
 */
class ShipmentTrackingEventReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int     $trackingEventId,
        public string  $sourceTable,      // containers_sea | containers_sky
        public int     $sourceId,
        public string  $eventType,        // GATE_IN, DISCHARGED, ...
        public ?string $city,
        public ?string $translationKey,
        public ?array  $translationParams,
    ) {}

    public function via(mixed $notifiable): array
    {
        if ($notifiable instanceof Client && ! $notifiable->notify_shipments) {
            return [];
        }
        return ['database', FcmChannel::class];
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'category'        => 'tracking',
            'tracking_event_id' => $this->trackingEventId,
            'source_table'    => $this->sourceTable,
            'source_id'       => $this->sourceId,
            'event_type'      => $this->eventType,
            'city'            => $this->city,
        ];
    }

    public function toFcm(mixed $notifiable): FcmMessage
    {
        $locale = $notifiable instanceof Client && ! empty($notifiable->lang)
            ? (string) $notifiable->lang
            : null;

        $body = $this->localizedBody($locale);
        $title = $this->localizedTitle($locale);

        return FcmMessage::make($title, $body)->withData([
            'category'          => 'tracking',
            'tracking_event_id' => (string) $this->trackingEventId,
            'source_table'      => $this->sourceTable,
            'source_id'         => (string) $this->sourceId,
            'event_type'        => $this->eventType,
            'city'              => (string) ($this->city ?? ''),
        ]);
    }

    private function localizedTitle(?string $locale): string
    {
        // Use the tracking::events title key when present; otherwise fall
        // back to a humanized version of the event code (LOADED → "Loaded").
        if ($locale === 'ar') {
            return 'تحديث الشحنة';
        }
        return 'Shipment update';
    }

    private function localizedBody(?string $locale): string
    {
        if ($this->translationKey !== null) {
            $params = $this->translationParams ?? [];
            $translated = $locale !== null
                ? trans($this->translationKey, $params, $locale)
                : trans($this->translationKey, $params);
            if (is_string($translated) && $translated !== $this->translationKey) {
                return $translated;
            }
        }
        // Fallback when no translation is registered — humanize the code.
        $body = str_replace('_', ' ', strtolower($this->eventType));
        return ucfirst($body);
    }

    /**
     * Build from a TrackingEvent. Centralizes the field plumbing so call
     * sites don't have to pass eight constructor args explicitly.
     */
    public static function fromEvent(TrackingEvent $e): self
    {
        return new self(
            trackingEventId  : $e->id,
            sourceTable      : $e->shipment_source_table,
            sourceId         : $e->shipment_source_id,
            eventType        : $e->event_type,
            city             : $e->city,
            translationKey   : $e->translation_key,
            translationParams: $e->translation_params,
        );
    }
}
