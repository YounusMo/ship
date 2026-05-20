<?php

namespace App\Notifications\Messages;

/**
 * Wire shape for one FCM push. Keep the message dumb — title/body for the
 * visible notification, data for the app's deep-link routing. The
 * "category" field is what the mobile app uses to pick which screen to
 * open on tap (e.g. "transaction" → Transactions tab, "shipment" →
 * Shipments → detail).
 */
class FcmMessage
{
    public string $title;
    public string $body;
    /** @var array<string,string> */
    public array $data = [];

    public static function make(string $title, string $body): self
    {
        $m = new self();
        $m->title = $title;
        $m->body  = $body;
        return $m;
    }

    /** All values are coerced to string — FCM data payload must be flat strings. */
    public function withData(array $data): self
    {
        foreach ($data as $k => $v) {
            $this->data[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }
        return $this;
    }
}
