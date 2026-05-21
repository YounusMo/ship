<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tracking\Jobs\ProcessShipsGoWebhook;
use App\Modules\Tracking\Models\WebhookDelivery;
use App\Modules\Tracking\Services\ShipsGo\ShipsGoWebhookVerifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound ShipsGo webhook receiver.
 *
 * Contract:
 *   1) Verify the X-ShipsGo-Signature header (HMAC-SHA256 over raw body).
 *   2) Insert one webhook_deliveries row, deduped at the DB layer by
 *      UNIQUE(provider, external_event_id). Synthesize a hash-based id
 *      when the provider doesn't supply one so the unique index applies
 *      uniformly.
 *   3) Dispatch ProcessShipsGoWebhook to the queue.
 *   4) Return 200 within ~100ms — heavy work belongs in the job, not here.
 *
 * On signature failure: 401. On a duplicate delivery: 200 (idempotent —
 * the upstream just retried something we already accepted).
 */
class ShipsGoWebhookController extends Controller
{
    public function __construct(
        private readonly ShipsGoWebhookVerifier $verifier,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-ShipsGo-Signature')
            ?? $request->header('X-Signature')
            ?? $request->header('Signature');

        $verified = $this->verifier->verify($rawBody, $signature);
        if (! $verified) {
            return response()->json(
                ['ok' => false, 'error' => 'invalid_signature'],
                401,
            );
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(
                ['ok' => false, 'error' => 'invalid_payload'],
                422,
            );
        }

        $externalId = (string) (
            $payload['event_id']
            ?? $payload['id']
            ?? hash('sha256', $rawBody)
        );

        try {
            $delivery = WebhookDelivery::create([
                'provider'           => 'shipsgo',
                'external_event_id'  => $externalId,
                'event_type'         => (string) ($payload['event'] ?? $payload['type'] ?? 'unknown'),
                'payload'            => $payload,
                'signature'          => $signature,
                'signature_verified' => true,
                'received_at'        => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Duplicate delivery — upstream retried something we already
            // accepted. Return 200 so the upstream stops retrying.
            return response()->json(['ok' => true, 'deduped' => true]);
        }

        ProcessShipsGoWebhook::dispatch($delivery->id);

        return response()->json(['ok' => true, 'delivery_id' => $delivery->id]);
    }
}
