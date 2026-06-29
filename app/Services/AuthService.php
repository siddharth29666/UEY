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
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Notifications\PasswordResetNotification;
use App\Services\DriverLocationService;

class AuthService
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * Register a new Rider.
     *
     * @param RegisterRiderDTO $dto
     * @return User
     * @throws ValidationException
     */
    public function registerRider(RegisterRiderDTO $dto): User
    {
        // 1. Guard check: Verify OTP was successfully verified for this phone
        if (!$this->otpService->isVerified($dto->phone, OtpType::REGISTER)) {
            throw ValidationException::withMessages([
                'phone' => ['Phone number has not been verified via OTP.'],
            ]);
        }

        return DB::transaction(function () use ($dto) {
            // 2. Create Rider account
            $user = User::create([
                'name' => $dto->name,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'password' => Hash::make($dto->password),
                'role' => UserRole::RIDER,
                'status' => UserStatus::ACTIVE,
            ]);

            // 3. Initialize Wallet
            Wallet::create([
                'user_id' => $user->id,
                'balance' => 0.00,
            ]);

            return $user;
        });
    }

    /**
     * Register a new Driver.
     *
     * @param RegisterDriverDTO $dto
     * @return User
     * @throws ValidationException
     */
    public function registerDriver(RegisterDriverDTO $dto): User
    {
        // 1. Guard check: Verify OTP was successfully verified for this phone
        if (!$this->otpService->isVerified($dto->phone, OtpType::REGISTER)) {
            throw ValidationException::withMessages([
                'phone' => ['Phone number has not been verified via OTP.'],
            ]);
        }

        return DB::transaction(function () use ($dto) {
            // 2. Create Driver account (status defaults to pending approval)
            $user = User::create([
                'name' => $dto->name,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'password' => Hash::make($dto->password),
                'role' => UserRole::DRIVER,
                'status' => UserStatus::PENDING_APPROVAL,
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

            return $user;
        });
    }

    /**
     * Authenticate a user and generate standard Sanctum tokens.
     *
     * @param LoginDTO $dto
     * @return array Contains 'user', 'token', and 'abilities'
     * @throws ValidationException
     */
    public function login(LoginDTO $dto): array
    {
        $user = User::with('driverProfile')->where('phone', $dto->phone)->first();

        if (!$user || !Hash::check($dto->password, $user->password)) {
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
        $ability = 'role:' . $user->role->value;
        $token = $user->createToken('uey-auth-token', [$ability])->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'abilities' => [$ability],
        ];
    }

    /**
     * Update user profile settings.
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function updateProfile(User $user, array $data): User
    {
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
        }

        return $user->load('driverProfile');
    }

    /**
     * Send password reset OTP via email.
     *
     * @param string $email
     * @return string Returns generated OTP
     * @throws ValidationException
     */
    public function sendPasswordResetOtp(string $email): string
    {
        $user = User::where('email', $email)->first();
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User with this email does not exist.'],
            ]);
        }

        // Generate 6-digit OTP
        $otp = (string) random_int(100000, 999999);

        // Store OTP in database table (hashed for security)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($otp),
                'created_at' => now(),
            ]
        );

        // Send OTP notification
        $user->notify(new PasswordResetNotification($otp));

        return $otp;
    }

    /**
     * Verify OTP and reset password.
     *
     * @param string $email
     * @param string $otp
     * @param string $password
     * @return void
     * @throws ValidationException
     */
    public function resetPassword(string $email, string $otp, string $password): void
    {
        $user = User::where('email', $email)->first();
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User with this email does not exist.'],
            ]);
        }

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (!$record) {
            throw ValidationException::withMessages([
                'otp' => ['No active password reset request found for this email.'],
            ]);
        }

        // Check expiry (10 minutes)
        if (\Carbon\Carbon::parse($record->created_at)->addMinutes(10)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw ValidationException::withMessages([
                'otp' => ['Password reset OTP has expired.'],
            ]);
        }

        // Verify OTP code
        if (!Hash::check($otp, $record->token)) {
            throw ValidationException::withMessages([
                'otp' => ['The provided OTP is invalid.'],
            ]);
        }

        // Update password securely
        $user->update([
            'password' => Hash::make($password),
        ]);

        // Invalidate OTP after successful use
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Revoke all existing Sanctum tokens for security
        $user->tokens()->delete();
    }

    /**
     * Permanently/soft delete user account.
     *
     * @param User $user
     * @param string $password
     * @return void
     * @throws ValidationException
     */
    public function deleteAccount(User $user, string $password): void
    {
        // Require password confirmation before deletion
        if (!Hash::check($password, $user->password)) {
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
        if (\Illuminate\Support\Facades\Schema::hasTable('saved_addresses')) {
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
