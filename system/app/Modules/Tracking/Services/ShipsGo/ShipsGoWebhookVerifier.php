<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services\ShipsGo;

/**
 * Verifies an inbound ShipsGo webhook signature against the shared
 * secret. ShipsGo signs the raw request body with HMAC-SHA256; the
 * signature lands in the X-ShipsGo-Signature header (configurable via
 * SHIPSGO_SIGNATURE_HEADER, but defaults to the v2 convention).
 *
 * Both timingSafe equals and constant-time string comparison are used to
 * resist timing-side-channel signature guessing.
 */
class ShipsGoWebhookVerifier
{
    public function __construct(
        private readonly string $secret,
    ) {
    }

    public function verify(string $rawBody, ?string $signatureHeader): bool
    {
        if ($this->secret === '' || $signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        // ShipsGo may prefix with "sha256=" — strip if present so we
        // compare apples to apples.
        $candidate = preg_replace('/^sha256=/i', '', $signatureHeader) ?? $signatureHeader;

        $expected = hash_hmac('sha256', $rawBody, $this->secret);

        // Length-mismatch short-circuit also runs in constant time per
        // hash_equals' guarantees.
        return hash_equals($expected, $candidate);
    }
}
