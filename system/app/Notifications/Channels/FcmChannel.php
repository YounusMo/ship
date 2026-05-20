<?php

namespace App\Notifications\Channels;

use App\Notifications\Messages\FcmMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends a Notification via Firebase Cloud Messaging HTTP v1.
 *
 * Wiring:
 *   1. Set FCM_PROJECT_ID and FCM_CREDENTIALS_PATH in .env. The credentials
 *      file is a service-account JSON downloaded from Firebase Console
 *      (Project Settings → Service Accounts → Generate new private key).
 *   2. The credentials file must NOT be committed to git (.gitignore it).
 *   3. Notification classes return ['fcm'] from via() and implement toFcm($notifiable).
 *
 * Behavior when credentials are missing (typical for local dev):
 *   - Logs the payload to laravel.log at info level and returns.
 *   - No exception is thrown, so notifications fired during local testing
 *     still write to the in-app feed via the database channel.
 *
 * Token hygiene:
 *   - On UNREGISTERED / INVALID_ARGUMENT responses from FCM, marks the
 *     offending client_devices row as revoked so future fan-outs skip it.
 *   - The OAuth access token is cached for 50 minutes (FCM hands them out
 *     for an hour) to avoid signing a fresh JWT on every push.
 */
class FcmChannel
{
    /** Where the cached OAuth access token lives. */
    private const TOKEN_CACHE_KEY = 'fcm:access_token';

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toFcm')) {
            return;
        }
        /** @var FcmMessage $message */
        $message = $notification->toFcm($notifiable);
        if (!$message instanceof FcmMessage) {
            return;
        }

        $tokens = $this->tokensFor($notifiable);
        if (empty($tokens)) {
            return;
        }

        $projectId  = (string) config('services.fcm.project_id');
        $credsPath  = (string) config('services.fcm.credentials_path');

        if ($projectId === '' || $credsPath === '' || !is_readable($credsPath)) {
            // Local-dev / unconfigured path: log and bail. The database
            // channel still wrote the in-app feed entry, so the user just
            // doesn't get a push — exactly what you want without creds.
            Log::info('[fcm] credentials missing — push skipped', [
                'notification' => get_class($notification),
                'recipients'   => count($tokens),
                'title'        => $message->title,
            ]);
            return;
        }

        $accessToken = $this->accessToken($credsPath);
        if ($accessToken === null) {
            return; // accessToken() already logged the failure.
        }

        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        foreach ($tokens as $row) {
            $body = [
                'message' => [
                    'token'        => $row->token,
                    'notification' => [
                        'title' => $message->title,
                        'body'  => $message->body,
                    ],
                    'data'  => $message->data,
                ],
            ];

            try {
                $resp = Http::withToken($accessToken)
                    ->acceptJson()
                    ->asJson()
                    ->timeout(10)
                    ->post($endpoint, $body);

                if ($resp->status() === 404 || $resp->status() === 400) {
                    // UNREGISTERED or INVALID_ARGUMENT — token is dead.
                    DB::table('client_devices')
                        ->where('id', $row->id)
                        ->update(['revoked_at' => now()]);
                    continue;
                }
                if (!$resp->successful()) {
                    Log::warning('[fcm] non-2xx response', [
                        'status' => $resp->status(),
                        'body'   => $resp->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('[fcm] send threw: ' . $e->getMessage());
            }
        }
    }

    /** Active device tokens for the notifiable Client. */
    private function tokensFor(mixed $notifiable): array
    {
        if (!is_object($notifiable) || !isset($notifiable->id)) {
            return [];
        }
        return DB::table('client_devices')
            ->where('client_id', $notifiable->id)
            ->whereNull('revoked_at')
            ->select(['id', 'token'])
            ->get()
            ->all();
    }

    /**
     * Mint (and cache) an OAuth2 access token from the service-account JSON.
     * The JWT is signed locally with the service-account private key, then
     * exchanged at Google's token endpoint for an hour-long access token.
     */
    private function accessToken(string $credsPath): ?string
    {
        $cached = cache(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $creds = json_decode((string) file_get_contents($credsPath), true);
        if (!is_array($creds) || empty($creds['client_email']) || empty($creds['private_key'])) {
            Log::warning('[fcm] credentials JSON is malformed');
            return null;
        }

        $now    = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss'   => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $b64 = fn($v) => rtrim(strtr(base64_encode((string) $v), '+/', '-_'), '=');
        $signingInput = $b64(json_encode($header)) . '.' . $b64(json_encode($claims));
        $signature    = '';
        $pkey = openssl_pkey_get_private($creds['private_key']);
        if (!$pkey || !openssl_sign($signingInput, $signature, $pkey, 'sha256WithRSAEncryption')) {
            Log::warning('[fcm] JWT signing failed');
            return null;
        }
        $assertion = $signingInput . '.' . $b64($signature);

        try {
            $resp = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $assertion,
            ]);
            if (!$resp->successful() || empty($resp->json('access_token'))) {
                Log::warning('[fcm] token exchange failed', ['status' => $resp->status(), 'body' => $resp->body()]);
                return null;
            }
            $token = (string) $resp->json('access_token');
            // FCM tokens are valid for 1h; cache 50m to give a buffer for clock skew.
            cache([self::TOKEN_CACHE_KEY => $token], now()->addMinutes(50));
            return $token;
        } catch (\Throwable $e) {
            Log::warning('[fcm] token exchange threw: ' . $e->getMessage());
            return null;
        }
    }
}
