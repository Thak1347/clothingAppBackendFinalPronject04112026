<?php
namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PushNotificationService
{
    public function sendToUser(int $userId, string $title, string $body): void
    {
        $tokens = DeviceToken::where('user_id', $userId)->get();
        if ($tokens->isEmpty()) {
            return;
        }

        try {
            $credentials = $this->credentials();
            $projectId = config('services.firebase.project_id') ?: ($credentials['project_id'] ?? null);
            if (!$projectId) {
                throw new RuntimeException('Firebase project id is not configured.');
            }
            $accessToken = $this->accessToken($credentials);

            foreach ($tokens as $deviceToken) {
                $response = Http::timeout(10)->withToken($accessToken)
                    ->acceptJson()
                    ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                        'message' => [
                            'token' => $deviceToken->token,
                            'notification' => ['title' => $title, 'body' => $body],
                            'data' => ['title' => $title, 'body' => $body],
                            'android' => [
                                'priority' => 'HIGH',
                                'notification' => ['channel_id' => 'store_updates'],
                            ],
                            'apns' => ['headers' => ['apns-priority' => '10']],
                        ],
                    ]);

                if ($response->failed()) {
                    Log::warning('FCM push delivery failed.', [
                        'user_id' => $userId,
                        'token_id' => $deviceToken->id,
                        'status' => $response->status(),
                        'response' => $response->json(),
                    ]);
                    $errorCode = data_get($response->json(), 'error.details.0.errorCode');
                    if ($errorCode === 'UNREGISTERED') {
                        $deviceToken->delete();
                    }
                }
            }
        } catch (\Throwable $exception) {
            Log::error('Unable to send Firebase push notification.', [
                'user_id' => $userId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function credentials(): array
    {
        $path = config('services.firebase.credentials');
        if (!$path) {
            throw new RuntimeException('FIREBASE_CREDENTIALS is not configured.');
        }

        $resolvedPath = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
        $contents = is_file($resolvedPath) ? file_get_contents($resolvedPath) : false;
        $credentials = json_decode($contents ?: '', true);
        if (!is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('Firebase service account JSON is missing or invalid.');
        }

        return $credentials;
    }

    /** @param array<string, mixed> $credentials */
    private function accessToken(array $credentials): string
    {
        $cacheKey = 'firebase_access_token_' . sha1((string) $credentials['client_email']);

        return Cache::remember($cacheKey, now()->addMinutes(55), function () use ($credentials) {
            $now = time();
            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $this->base64UrlEncode(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));
            $unsignedJwt = "{$header}.{$claims}";

            if (!openssl_sign($unsignedJwt, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Could not sign Firebase service account assertion.');
            }

            $response = Http::timeout(10)->asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsignedJwt . '.' . $this->base64UrlEncode($signature),
            ])->throw()->json();

            if (empty($response['access_token'])) {
                throw new RuntimeException('Firebase OAuth response did not contain an access token.');
            }

            return $response['access_token'];
        });
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
