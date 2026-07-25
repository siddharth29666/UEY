<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAuthService
{
    /**
     * Generate Google OAuth 2.0 redirect authorization URL.
     */
    public function getRedirectUrl(?string $role = 'rider'): string
    {
        $clientId = config('services.google.client_id');
        $redirectUri = config('services.google.redirect_uri');
        $state = urlencode(json_encode(['role' => $role, 'nonce' => Str::random(16)]));

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($params);
    }

    /**
     * Handle Google OAuth callback code exchange & login/register user.
     */
    public function handleCallback(string $code, ?string $role = 'rider'): array
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = config('services.google.redirect_uri');

        // Mock mode for tests or unconfigured credentials
        if (empty($clientId) || empty($clientSecret) || $clientId === 'your_google_client_id') {
            $googleId = 'google_mock_'.substr(md5($code), 0, 12);
            $googleEmail = 'google_user_'.substr(md5($code), 0, 6).'@example.com';
            $googleName = 'Google User';
            $googlePicture = 'https://lh3.googleusercontent.com/a/default-user';
            $emailVerified = true;
        } else {
            try {
                $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                ]);

                if (! $tokenResponse->successful()) {
                    Log::warning('Google OAuth token exchange failed', ['status' => $tokenResponse->status()]);
                    throw new \Exception('Failed to exchange authorization code with Google.');
                }

                $accessToken = $tokenResponse->json('access_token');

                $userResponse = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');
                if (! $userResponse->successful()) {
                    Log::warning('Google UserInfo fetch failed', ['status' => $userResponse->status()]);
                    throw new \Exception('Failed to fetch user profile from Google.');
                }

                $userData = $userResponse->json();
                $googleId = $userData['sub'] ?? null;
                $googleEmail = $userData['email'] ?? null;
                $googleName = $userData['name'] ?? 'Google User';
                $googlePicture = $userData['picture'] ?? null;
                $emailVerified = (bool) ($userData['email_verified'] ?? false);
            } catch (\Exception $e) {
                Log::error('Google OAuth Exception', ['message' => $e->getMessage()]);
                throw new \Exception($e->getMessage());
            }
        }

        if (! $emailVerified) {
            throw new \Exception('Google account email is not verified.');
        }

        if (! $googleId || ! $googleEmail) {
            throw new \Exception('Invalid Google account information returned.');
        }

        // Find or create user
        $user = User::where('google_id', $googleId)->first();

        if (! $user) {
            // Match by email
            $user = User::where('email', $googleEmail)->first();

            if ($user) {
                // Link Google account to existing user
                $user->update([
                    'google_id' => $googleId,
                    'auth_provider' => 'google',
                    'avatar_url' => $user->avatar_url ?: $googlePicture,
                ]);
            } else {
                // Register new user
                $userRole = in_array($role, ['rider', 'driver']) ? $role : 'rider';
                $user = User::create([
                    'name' => $googleName,
                    'email' => $googleEmail,
                    'google_id' => $googleId,
                    'auth_provider' => 'google',
                    'role' => $userRole,
                    'status' => 'active',
                    'password' => Hash::make(Str::random(32)),
                    'avatar_url' => $googlePicture,
                    'email_verified_at' => now(),
                ]);

                // Create user wallet
                Wallet::firstOrCreate(
                    ['user_id' => $user->id],
                    ['balance' => 0.00, 'currency' => 'EUR', 'status' => 'active']
                );

                // Create driver profile if driver role
                if ($userRole === 'driver') {
                    DriverProfile::firstOrCreate([
                        'user_id' => $user->id,
                    ], [
                        'is_online' => false,
                        'is_available' => false,
                    ]);
                }
            }
        }

        $userRoleEnum = is_object($user->role) ? $user->role->value : $user->role;
        $tokenName = $userRoleEnum === 'driver' ? 'driver_token' : 'rider_token';
        $token = $user->createToken($tokenName, ["role:{$userRoleEnum}"])->plainTextToken;

        return [
            'user' => $user->load('driverProfile'),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ];
    }
}
