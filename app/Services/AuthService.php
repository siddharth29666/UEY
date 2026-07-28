<?php

namespace App\Services;

use App\DTOs\LoginDTO;
use App\DTOs\RegisterDriverDTO;
use App\DTOs\RegisterRiderDTO;
use App\Enums\OtpType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VehicleStatus;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Mail\PasswordResetOtpMail;
use App\Models\Wallet;
use App\Notifications\PasswordResetNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * Register a new Rider.
     *
     * @throws ValidationException
     */
    public function registerRider(RegisterRiderDTO $dto, ?string $referralCode = null): User
    {
        // 1. Guard check: Verify OTP was successfully verified for this phone
        if (! $this->otpService->isVerified($dto->phone, OtpType::REGISTER)) {
            throw ValidationException::withMessages([
                'phone' => ['Phone number has not been verified via OTP.'],
            ]);
        }

        $referrer = null;
        if ($referralCode) {
            $code = strtoupper(trim($referralCode));
            $referrer = User::where('referral_code', $code)
                ->where('status', UserStatus::ACTIVE)
                ->first();

            if (!$referrer) {
                $validator = \Illuminate\Support\Facades\Validator::make([], []);
                $validator->errors()->add('referral_code', 'Invalid referral code.');
                throw new \Illuminate\Validation\ValidationException($validator, response()->json([
                    'success' => false,
                    'message' => 'Invalid referral code.',
                ], 422));
            }

            // Self-referral validation
            if ($referrer->phone === $dto->phone || ($dto->email && $referrer->email === $dto->email)) {
                $validator = \Illuminate\Support\Facades\Validator::make([], []);
                $validator->errors()->add('referral_code', 'Invalid referral code.');
                throw new \Illuminate\Validation\ValidationException($validator, response()->json([
                    'success' => false,
                    'message' => 'Invalid referral code.',
                ], 422));
            }
        }

        return DB::transaction(function () use ($dto, $referrer, $referralCode) {
            // 2. Create Rider account
            $user = User::create([
                'name' => $dto->name,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'password' => Hash::make($dto->password),
                'role' => UserRole::RIDER,
                'status' => UserStatus::ACTIVE,
                'referred_by' => $referrer ? $referrer->id : null,
            ]);

            // 3. Initialize Wallet
            Wallet::create([
                'user_id' => $user->id,
                'balance' => 0.00,
            ]);

            // 4. Create pending Referral record
            if ($referrer) {
                $settingService = app(SettingService::class);
                $referrerBonus = (float) $settingService->get('referral_bonus_referrer', 10.00);
                $referredBonus = (float) $settingService->get('referral_bonus_referred', 5.00);

                \App\Models\Referral::create([
                    'referrer_id' => $referrer->id,
                    'referred_user_id' => $user->id,
                    'referral_code' => strtoupper(trim($referralCode)),
                    'status' => 'pending',
                    'referrer_bonus' => $referrerBonus,
                    'referred_bonus' => $referredBonus,
                ]);

                // Fire notifications
                event(new \App\Events\ReferralApplied($user, \App\Enums\NotificationType::REFERRAL_APPLIED, null, null, [
                    'referrer_name' => $referrer->name,
                ]));

                event(new \App\Events\ReferralApplied($referrer, \App\Enums\NotificationType::REFERRAL_APPLIED, null, null, [
                    'invitee_name' => $user->name,
                ]));
            }

            return $user;
        });
    }

    /**
     * Register a new Driver.
     *
     * @throws ValidationException
     */
    public function registerDriver(RegisterDriverDTO $dto, ?string $referralCode = null): User
    {
        // 1. Guard check: Verify OTP was successfully verified for this phone
        if (! $this->otpService->isVerified($dto->phone, OtpType::REGISTER)) {
            throw ValidationException::withMessages([
                'phone' => ['Phone number has not been verified via OTP.'],
            ]);
        }

        $referrer = null;
        if ($referralCode) {
            $code = strtoupper(trim($referralCode));
            $referrer = User::where('referral_code', $code)
                ->where('status', UserStatus::ACTIVE)
                ->first();

            if (!$referrer) {
                $validator = \Illuminate\Support\Facades\Validator::make([], []);
                $validator->errors()->add('referral_code', 'Invalid referral code.');
                throw new \Illuminate\Validation\ValidationException($validator, response()->json([
                    'success' => false,
                    'message' => 'Invalid referral code.',
                ], 422));
            }

            // Self-referral validation
            if ($referrer->phone === $dto->phone || ($dto->email && $referrer->email === $dto->email)) {
                $validator = \Illuminate\Support\Facades\Validator::make([], []);
                $validator->errors()->add('referral_code', 'Invalid referral code.');
                throw new \Illuminate\Validation\ValidationException($validator, response()->json([
                    'success' => false,
                    'message' => 'Invalid referral code.',
                ], 422));
            }
        }

        return DB::transaction(function () use ($dto, $referrer, $referralCode) {
            // 2. Create Driver account (status defaults to pending approval)
            $user = User::create([
                'name' => $dto->name,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'password' => Hash::make($dto->password),
                'role' => UserRole::DRIVER,
                'status' => UserStatus::PENDING_APPROVAL,
                'referred_by' => $referrer ? $referrer->id : null,
            ]);

            // 3. Initialize Driver Profile
            $driverProfile = DriverProfile::create([
                'user_id' => $user->id,
                'license_number' => $dto->license_number,
                'license_expiry' => $dto->license_expiry,
                'is_online' => false,
                'rating' => 5.00,
            ]);

            // 4. Initialize Vehicle details
            Vehicle::create([
                'driver_profile_id' => $driverProfile->id,
                'vehicle_type_id' => $dto->vehicle_type_id,
                'make' => $dto->vehicle_make,
                'model' => $dto->vehicle_model,
                'year' => $dto->vehicle_year,
                'color' => $dto->vehicle_color,
                'plate_number' => $dto->vehicle_plate,
                'status' => VehicleStatus::PENDING,
            ]);

            // 5. Initialize Wallet
            Wallet::create([
                'user_id' => $user->id,
                'balance' => 0.00,
            ]);

            // 6. Create pending Referral record
            if ($referrer) {
                $settingService = app(SettingService::class);
                $referrerBonus = (float) $settingService->get('referral_bonus_referrer', 10.00);
                $referredBonus = (float) $settingService->get('referral_bonus_referred', 5.00);

                \App\Models\Referral::create([
                    'referrer_id' => $referrer->id,
                    'referred_user_id' => $user->id,
                    'referral_code' => strtoupper(trim($referralCode)),
                    'status' => 'pending',
                    'referrer_bonus' => $referrerBonus,
                    'referred_bonus' => $referredBonus,
                ]);

                // Fire notifications
                event(new \App\Events\ReferralApplied($user, \App\Enums\NotificationType::REFERRAL_APPLIED, null, null, [
                    'referrer_name' => $referrer->name,
                ]));

                event(new \App\Events\ReferralApplied($referrer, \App\Enums\NotificationType::REFERRAL_APPLIED, null, null, [
                    'invitee_name' => $user->name,
                ]));
            }

            return $user;
        });
    }

    /**
     * Authenticate a user and generate standard Sanctum tokens.
     *
     * @return array Contains 'user', 'token', and 'abilities'
     *
     * @throws ValidationException
     */
    public function login(LoginDTO $dto): array
    {
        $user = User::with('driverProfile')->where('phone', $dto->phone)->first();

        if (! $user || ! Hash::check($dto->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['Invalid phone number or password.'],
            ]);
        }

        if ($user->status === UserStatus::SUSPENDED) {
            throw ValidationException::withMessages([
                'phone' => ['Your account has been suspended. Please contact support.'],
            ]);
        }

        // Generate Sanctum token with role-specific ability
        $ability = 'role:'.$user->role->value;
        $token = $user->createToken('uey-auth-token', [$ability])->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'abilities' => [$ability],
        ];
    }

    /**
     * Update user profile settings.
     */
    public function updateProfile(User $user, array $data, $request = null): User
    {
        // 0. Handle profile image file upload if present
        $file = null;
        if ($request && $request->hasFile('avatar')) {
            $file = $request->file('avatar');
        } elseif ($request && $request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
        } elseif ($request && $request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
        } elseif ($request && $request->hasFile('image')) {
            $file = $request->file('image');
        }

        if ($file && $file->isValid()) {
            // Delete old avatar from public disk if managed locally
            if ($user->avatar_url) {
                $oldPath = \Illuminate\Support\Str::after($user->avatar_url, '/storage/');
                if ($oldPath && $oldPath !== $user->avatar_url && \Illuminate\Support\Facades\Storage::disk('public')->exists($oldPath)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
                }
            }

            $path = $file->store('avatars', 'public');
            $data['avatar_url'] = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        }

        // 1. Update core fields
        $user->update(array_intersect_key($data, array_flip([
            'name',
            'email',
            'avatar_url',
            'email_notifications',
            'sms_notifications',
            'push_notifications',
        ])));

        // 2. If user is driver, update driver profile specific settings
        if ($user->isDriver() && $user->driverProfile) {
            $user->driverProfile->update(array_intersect_key($data, array_flip([
                'default_navigation',
                'auto_accept',
            ])));

            if (isset($data['vehicle_type_id'])) {
                $vehicles = $user->driverProfile->vehicles;
                if ($vehicles->isNotEmpty()) {
                    foreach ($vehicles as $vehicle) {
                        $vehicle->update(['vehicle_type_id' => (int) $data['vehicle_type_id']]);
                    }
                } else {
                    Vehicle::create([
                        'driver_profile_id' => $user->driverProfile->id,
                        'vehicle_type_id' => (int) $data['vehicle_type_id'],
                        'make' => 'Default Make',
                        'model' => 'Default Model',
                        'year' => (int) date('Y'),
                        'color' => 'Default Color',
                        'plate_number' => 'PLATE-'.strtoupper(\Illuminate\Support\Str::random(6)),
                        'status' => VehicleStatus::APPROVED,
                    ]);
                }
            }

            return $user->load('driverProfile.vehicles.vehicleType');
        }

        return $user;
    }

    /**
     * Send password reset OTP via email.
     * Never returns or logs the OTP code.
     *
     * @throws ValidationException
     */
    public function sendPasswordResetOtp(string $email): void
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['User with this email does not exist.'],
            ]);
        }

        // Check 60s cooldown
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();
        if ($record && Carbon::parse($record->created_at)->addSeconds(60)->isFuture()) {
            throw ValidationException::withMessages([
                'email' => ['Please wait at least 60 seconds before requesting a new password reset OTP.'],
            ]);
        }

        // Generate 6-digit OTP
        $otp = sprintf('%06d', random_int(0, 999999));

        // Store OTP in database table (hashed for security)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($otp),
                'created_at' => now(),
            ]
        );

        // Send OTP via Mail Mailable
        Mail::to($user->email)->send(new PasswordResetOtpMail($otp));
    }

    /**
     * Verify forgot password OTP code.
     *
     * @throws ValidationException
     */
    public function verifyForgotPasswordOtp(string $email, string $otp): bool
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['User with this email does not exist.'],
            ]);
        }

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (! $record) {
            throw ValidationException::withMessages([
                'otp' => ['No active password reset request found for this email.'],
            ]);
        }

        // Check expiry (10 minutes)
        if (Carbon::parse($record->created_at)->addMinutes(10)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw ValidationException::withMessages([
                'otp' => ['Password reset OTP has expired.'],
            ]);
        }

        // Verify OTP code against stored hash
        if (! Hash::check($otp, $record->token)) {
            throw ValidationException::withMessages([
                'otp' => ['The provided OTP is invalid.'],
            ]);
        }

        return true;
    }

    /**
     * Verify OTP and reset password.
     *
     * @throws ValidationException
     */
    public function resetPassword(string $email, string $otp, string $password): void
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['User with this email does not exist.'],
            ]);
        }

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (! $record) {
            throw ValidationException::withMessages([
                'otp' => ['No active password reset request found for this email.'],
            ]);
        }

        // Check expiry (10 minutes)
        if (Carbon::parse($record->created_at)->addMinutes(10)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw ValidationException::withMessages([
                'otp' => ['Password reset OTP has expired.'],
            ]);
        }

        // Verify OTP code
        if (! Hash::check($otp, $record->token)) {
            throw ValidationException::withMessages([
                'otp' => ['The provided OTP is invalid.'],
            ]);
        }

        // Update password securely
        $user->update([
            'password' => Hash::make($password),
        ]);

        // Revoke active tokens
        $user->tokens()->delete();

        // Invalidate OTP after successful use
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Revoke all existing Sanctum tokens for security
        $user->tokens()->delete();
    }

    /**
     * Permanently/soft delete user account.
     *
     * @throws ValidationException
     */
    public function deleteAccount(User $user, string $password): void
    {
        // Require password confirmation before deletion
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Invalid password.'],
            ]);
        }

        // Revoke all active Sanctum tokens
        $user->tokens()->delete();

        // Cleanup related data
        if ($user->isDriver() && $user->driverProfile) {
            // Toggle offline in DriverLocationService to clean up Redis GEO index
            $locationService = app(DriverLocationService::class);
            $locationService->toggleOnlineStatus($user->driverProfile, false);

            // Delete sensitive details: documents, bank accounts, vehicles, and profile
            $user->driverProfile->documents()->delete();
            if ($user->driverProfile->bankAccount) {
                $user->driverProfile->bankAccount->delete();
            }
            $user->driverProfile->vehicles()->delete();
            $user->driverProfile->delete();
        }

        // Delete saved addresses
        if (Schema::hasTable('saved_addresses')) {
            $user->savedAddresses()->delete();
        }

        // Delete wallet
        if ($user->wallet) {
            $user->wallet->delete();
        }

        // Soft delete user record
        $user->delete();
    }
}
