<?php

namespace App\Services;

use App\Enums\OtpType;
use App\Models\OtpVerification;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;

class OtpService
{
    /**
     * Generate and store an OTP for the given phone number.
     *
     * @param string $phone
     * @param OtpType $type
     * @return string|null Returns the OTP string if APP_ENV is local, otherwise null.
     * @throws \Exception If the resend cooldown has not expired.
     */
    public function sendOtp(string $phone, OtpType $type): ?string
    {
        // 1. Check for cooldown (resend support)
        // If there's an active OTP created in the last 60 seconds, block resend to prevent spamming
        $recentOtp = OtpVerification::where('phone', $phone)
            ->where('type', $type)
            ->where('created_at', '>', Carbon::now()->subMinute())
            ->first();

        if ($recentOtp) {
            throw new \Exception("Please wait at least 60 seconds before requesting a new OTP.");
        }

        // 2. Generate a 6-digit OTP
        $otp = (string) rand(100000, 999999);
        $expiryMinutes = 5;

        // 3. Save the OTP record
        OtpVerification::create([
            'phone' => $phone,
            'code' => $otp,
            'type' => $type,
            'expires_at' => Carbon::now()->addMinutes($expiryMinutes),
            'verified_at' => null,
            'created_at' => Carbon::now(),
        ]);

        // 4. Return OTP in response ONLY when local
        if (App::environment('local')) {
            return $otp;
        }

        return null;
    }

    /**
     * Verify the OTP code for a phone number.
     *
     * @param string $phone
     * @param string $code
     * @param OtpType $type
     * @return bool
     */
    public function verifyOtp(string $phone, string $code, OtpType $type): bool
    {
        // Find the latest active OTP for this phone and type
        $otpRecord = OtpVerification::where('phone', $phone)
            ->where('code', $code)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->where('expires_at', '>', Carbon::now())
            ->orderBy('id', 'desc')
            ->first();

        if (!$otpRecord) {
            return false;
        }

        // Mark it as verified
        $otpRecord->update([
            'verified_at' => Carbon::now(),
        ]);

        return true;
    }

    /**
     * Check if a phone number has been verified for registration/login.
     * Used as a guard check before allowing profile registration.
     *
     * @param string $phone
     * @param OtpType $type
     * @return bool
     */
    public function isVerified(string $phone, OtpType $type): bool
    {
        return OtpVerification::where('phone', $phone)
            ->where('type', $type)
            ->whereNotNull('verified_at')
            ->where('expires_at', '>', Carbon::now()->subMinutes(15)) // must have verified within last 15 mins
            ->exists();
    }
}
