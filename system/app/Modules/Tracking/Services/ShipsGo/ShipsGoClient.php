<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services\ShipsGo;

use App\Modules\Tracking\Services\ShipsGo\Exceptions\ShipsGoApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin typed wrapper around the ShipsGo REST API. Every HTTP call goes
 * through here so retry/timeout/header policy lives in one place.
 *
 * The Http facade is used (vs Guzzle directly) so Http::fake() works in
 * tests without rewiring anything.
 *
 * Returns are intentionally low-fi (plain arrays) at this layer — DTO
 * normalization is the caller's job (ProcessShipsGoWebhook job parses
 * the same shape both for sync reads and webhook deliveries).
 */
class ShipsGoClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeoutSeconds = 15,
        private readonly int $retryAttempts = 3,
        private readonly int $retryBaseMs = 500,
    ) {
    }

    /** @return array<string, mixed> */
    public function getOceanShipment(string $referenceId): array
    {
        return $this->request('GET', "/ocean/shipments/{$referenceId}");
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOceanShipment(array $payload): array
    {
        return $this->request('POST', '/ocean/shipments', $payload);
    }

    /** @return array<string, mixed> */
    public function getAirShipment(string $referenceId): array
    {
        return $this->request('GET', "/air/shipments/{$referenceId}");
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createAirShipment(array $payload): array
    {
        return $this->request('POST', '/air/shipments', $payload);
    }

    /**
     * Credits visibility: ShipsGo v2 doesn't expose a credits-balance
     * endpoint. The "you're out of credits" signal comes back as HTTP 402
     * NOT_ENOUGH_CREDITS on POST /ocean/shipments and POST /air/shipments.
     * Surface that to ops by inspecting WebhookDelivery / job errors;
     * there is no standalone credit-balance probe.
     */
    public function getCredits(): ?int
    {
        return null;
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $http = $this->pendingRequest();

        try {
            $response = match (strtoupper($method)) {
                'GET'    => $http->get($path),
                'POST'   => $http->post($path, $body ?? []),
                'PUT'    => $http->put($path, $body ?? []),
                'DELETE' => $http->delete($path),
                default  => throw ShipsGoApiException::invalidPayload("unsupported method {$method}"),
            };
        } catch (Throwable $e) {
            // Connection-level failures (DNS, TLS, timeout) surface here.
            throw ShipsGoApiException::transport($e->getMessage(), $e);
        }

        if ($response->failed()) {
            throw ShipsGoApiException::http($response->status(), $response->body());
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw ShipsGoApiException::invalidPayload('expected JSON object, got: ' . gettype($json));
        }

        return $json;
    }

    private function pendingRequest(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withHeaders([
                // ShipsGo v2 auth header — NOT 'X-API-Key', that returns 401.
                // See https://api.shipsgo.com/docs/v2 — confirmed by probe
                // returning 200 with X-Shipsgo-User-Token vs 401 with X-API-Key.
                'X-Shipsgo-User-Token' => $this->apiKey,
                'Accept'               => 'application/json',
                'Content-Type'         => 'application/json',
            ])
            ->timeout($this->timeoutSeconds)
            ->connectTimeout(min(5, $this->timeoutSeconds))
            // Exponential backoff with jitter. Retry only on 5xx + 429.
            ->retry(
                $this->retryAttempts,
                $this->retryBaseMs,
                function (Throwable $e, PendingRequest $req) {
                    if ($e instanceof RequestException) {
                        $status = $e->response->status();
                        return $status >= 500 || $status === 429;
                    }
                    // Network-level errors: retry.
                    return true;
                },
                throw: false,
            );
    }
}
