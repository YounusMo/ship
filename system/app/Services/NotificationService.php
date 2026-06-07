<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Client;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifier;

/**
 * Single entry point for sending notifications to clients.
 *
 * What this gives us:
 *   1. `clients.notify_*` preferences are consulted before dispatch.
 *      The legacy dispatch path ignored them; now muting a category
 *      actually works.
 *   2. Uniform logging of outcome (sent / muted / failed). Easier to
 *      build "did we send X?" diagnostics on top.
 *   3. Single place to test-fake. NotificationFacade::fake() still
 *      works because we go through Notification::send under the hood.
 *
 * Notification kinds (must match a `clients.notify_<kind>` column):
 *   - 'transactions' — deposits, withdrawals, transfers, commissions.
 *   - 'shipments'    — shipment received, in transit, ready for pickup,
 *                      delivered.
 *   - 'receipts'     — printed receipt issued.
 *
 * @see docs/GAPS.md gap #5
 */
class NotificationService
{
    /** Recognized notification kinds. */
    public const KIND_TRANSACTIONS = 'transactions';
    public const KIND_SHIPMENTS    = 'shipments';
    public const KIND_RECEIPTS     = 'receipts';

    private const ALL_KINDS = [
        self::KIND_TRANSACTIONS,
        self::KIND_SHIPMENTS,
        self::KIND_RECEIPTS,
    ];

    /**
     * Send a notification to a single client, respecting their
     * preferences. Returns true on dispatch, false if muted or on
     * delivery failure (failures are logged but never thrown — see the
     * existing dispatch policy in DispatchShipmentEventNotificationJob).
     */
    public function notifyClient(Client $client, string $kind, Notification $notification): bool
    {
        if (! $this->isEnabledFor($client, $kind)) {
            Log::info('[notify] muted', [
                'client_id' => $client->id,
                'kind'      => $kind,
                'class'     => $notification::class,
            ]);
            return false;
        }

        try {
            Notifier::send($client, $notification);
            return true;
        } catch (\Throwable $e) {
            Log::warning('[notify] dispatch failed', [
                'client_id' => $client->id,
                'kind'      => $kind,
                'class'     => $notification::class,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send to multiple clients in one shot. Each client is filtered
     * independently against their own preferences. Returns the number
     * of recipients the dispatch was attempted for.
     *
     * @param  iterable<Client>  $clients
     */
    public function notifyClients(iterable $clients, string $kind, Notification $notification): int
    {
        $eligible = [];
        foreach ($clients as $client) {
            if ($this->isEnabledFor($client, $kind)) {
                $eligible[] = $client;
            }
        }

        if ($eligible === []) {
            Log::info('[notify] fan-out: all recipients muted', [
                'kind'  => $kind,
                'class' => $notification::class,
            ]);
            return 0;
        }

        try {
            Notifier::send($eligible, $notification);
            return count($eligible);
        } catch (\Throwable $e) {
            Log::warning('[notify] fan-out partial failure', [
                'kind'  => $kind,
                'class' => $notification::class,
                'count' => count($eligible),
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Whether a notification of the given kind is enabled for the
     * client. Unrecognized kinds default to allowed — preferences are
     * opt-out, not opt-in.
     */
    public function isEnabledFor(Client $client, string $kind): bool
    {
        if (! in_array($kind, self::ALL_KINDS, true)) {
            return true;
        }

        $column = 'notify_' . $kind;
        $value = $client->{$column} ?? true;

        // Cast tolerant of legacy string values ("1"/"0"/"true"/"false")
        // and modern boolean column casts.
        if (is_bool($value)) {
            return $value;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
