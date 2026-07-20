<?php

namespace App\Services;

use App\Models\UserDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    /**
     * Determine if real Firebase calls should be executed.
     */
    public function isEnabled(): bool
    {
        if (! config('services.firebase.enabled', true)) {
            return false;
        }

        $projectId = config('services.firebase.project_id');
        $clientEmail = config('services.firebase.client_email');
        $privateKey = config('services.firebase.private_key');

        $hasCredentials = ! empty($projectId) && ! empty($clientEmail) && ! empty($privateKey);

        if (! $hasCredentials) {
            return false;
        }

        if (app()->environment('local', 'testing')) {
            $forceEnable = (bool) config('services.firebase.force_enable', false) ||
                           (bool) config('services.firebase.testing_allow_real_calls', false);
            return $forceEnable;
        }

        return true;
    }

    /**
     * Generate OAuth2 Access Token using Google Service Account credentials.
     */
    public function getAccessToken(): string
    {
        $clientEmail = config('services.firebase.client_email');
        $privateKey = config('services.firebase.private_key');

        // Clean up escaped newlines in private key
        $privateKey = str_replace('\n', "\n", $privateKey);

        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $payload = json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = '';
        if (! openssl_sign($base64UrlHeader.'.'.$base64UrlPayload, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \Exception('Failed to sign OAuth2 JWT token. Verify FIREBASE_PRIVATE_KEY.');
        }
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        $jwt = $base64UrlHeader.'.'.$base64UrlPayload.'.'.$base64UrlSignature;

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            throw new \Exception('Failed to retrieve Firebase access token: '.$response->body());
        }

        return $response->json('access_token');
    }

    /**
     * Send push notification to a single device token.
     */
    public function send(string $token, string $title, string $body, array $data = []): array
    {
        if (! $this->isEnabled()) {
            Log::info("FCM MOCK [Single] -> Token: {$token}, Title: {$title}, Body: {$body}", $data);

            return [
                'success' => true,
                'message_id' => 'mock_msg_'.uniqid(),
            ];
        }

        try {
            $accessToken = $this->getAccessToken();
            $projectId = config('services.firebase.project_id');

            // Format extra data keys to be strings
            $stringData = [];
            foreach ($data as $k => $v) {
                $stringData[(string) $k] = is_array($v) ? json_encode($v) : (string) $v;
            }

            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $stringData ?: null,
                    'android' => [
                        'notification' => [
                            'sound' => 'default',
                        ],
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'badge' => isset($data['badge']) ? (int) $data['badge'] : 1,
                            ],
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            Log::info("FCM Response: Status={$response->status()} Body=".$response->body());

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->json('name'),
                ];
            }

            // Detect expired or invalid token and clean up
            $error = $response->json('error');
            $status = $error['status'] ?? '';
            $message = $error['message'] ?? '';

            if (in_array($response->status(), [404, 410]) || $status === 'UNREGISTERED' || str_contains($message, 'not registered')) {
                $this->deleteInvalidToken($token);
            }

            return [
                'success' => false,
                'error' => $message ?: 'FCM sending failed',
            ];
        } catch (\Exception $e) {
            Log::error('FCM Exception: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send push notification to multiple device tokens.
     */
    public function sendMultiple(array $tokens, string $title, string $body, array $data = []): array
    {
        $results = [];
        foreach ($tokens as $token) {
            $results[$token] = $this->send($token, $title, $body, $data);
        }

        return $results;
    }

    /**
     * Send push notification to an FCM Topic.
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        if (! $this->isEnabled()) {
            Log::info("FCM MOCK [Topic: {$topic}] -> Title: {$title}, Body: {$body}", $data);

            return [
                'success' => true,
                'message_id' => 'mock_topic_msg_'.uniqid(),
            ];
        }

        try {
            $accessToken = $this->getAccessToken();
            $projectId = config('services.firebase.project_id');

            $stringData = [];
            foreach ($data as $k => $v) {
                $stringData[(string) $k] = is_array($v) ? json_encode($v) : (string) $v;
            }

            $payload = [
                'message' => [
                    'topic' => $topic,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $stringData ?: null,
                ],
            ];

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            Log::info("FCM Topic Response: Status={$response->status()} Body=".$response->body());

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->json('name'),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('error.message') ?: 'FCM topic send failed',
            ];
        } catch (\Exception $e) {
            Log::error('FCM Topic Exception: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Subscribe tokens to a topic.
     */
    public function subscribeToTopic(string $topic, array $tokens): bool
    {
        if (empty($tokens)) {
            return true;
        }

        if (! $this->isEnabled()) {
            Log::info("FCM MOCK [Subscribe: {$topic}] -> Tokens: ".implode(', ', $tokens));

            return true;
        }

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->withHeaders(['access_token_auth' => 'true'])
                ->post('https://iid.googleapis.com/iid/v1:batchAdd', [
                    'to' => '/topics/'.$topic,
                    'registration_tokens' => $tokens,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('FCM Subscribe Exception: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Unsubscribe tokens from a topic.
     */
    public function unsubscribeFromTopic(string $topic, array $tokens): bool
    {
        if (empty($tokens)) {
            return true;
        }

        if (! $this->isEnabled()) {
            Log::info("FCM MOCK [Unsubscribe: {$topic}] -> Tokens: ".implode(', ', $tokens));

            return true;
        }

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->withHeaders(['access_token_auth' => 'true'])
                ->post('https://iid.googleapis.com/iid/v1:batchRemove', [
                    'to' => '/topics/'.$topic,
                    'registration_tokens' => $tokens,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('FCM Unsubscribe Exception: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Validate an FCM token using FCM validate_only dry-run.
     */
    public function validateToken(string $token): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        try {
            $accessToken = $this->getAccessToken();
            $projectId = config('services.firebase.project_id');

            $payload = [
                'message' => [
                    'token' => $token,
                ],
                'validate_only' => true,
            ];

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            if ($response->successful()) {
                return true;
            }

            $error = $response->json('error');
            $status = $error['status'] ?? '';

            if (in_array($response->status(), [404, 410]) || $status === 'UNREGISTERED') {
                return false;
            }

            // Other validation errors mean the token structure itself might be wrong or invalid
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete an invalid token.
     */
    public function deleteInvalidToken(string $token): void
    {
        UserDevice::where('device_token', $token)->delete();
        Log::info("FCM Service: Removed invalid/expired token: {$token}");
    }
}
