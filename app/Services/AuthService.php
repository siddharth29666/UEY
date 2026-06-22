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
}
