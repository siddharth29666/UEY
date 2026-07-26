<?php

namespace App\Services;

use App\Enums\OtpType;
use App\Models\OtpVerification;
use Carbon\Carbon;

class OtpService
{
    public function __construct(
        protected TwilioOtpService $twilioOtpService
    ) {}

    /**
     * Send an OTP code to a user's phone via Twilio Verify.
     * Never returns OTP in string or API response.
     */
    public function sendOtp(string $phone, OtpType $type): ?string
    {
        // 1. Cooldown check (60s)
        $recentOtp = OtpVerification::where('phone', $phone)
            ->where('type', $type)
            ->where('created_at', '>', Carbon::now()->subMinute())
            ->first();

        if ($recentOtp) {
            throw new \Exception('Please wait at least 60 seconds before requesting a new OTP.');
        }

        // 2. Send verification code via Twilio Verify
        $this->twilioOtpService->sendVerificationCode($phone);

        // 3. Save internal verification tracking record (without storing raw OTP plaintext)
        OtpVerification::create([
            'phone' => $phone,
            'code' => null,
            'type' => $type,
            'expires_at' => Carbon::now()->addMinutes(10),
            'verified_at' => null,
            'created_at' => Carbon::now(),
        ]);

        // Never return OTP code in response or logs
        return null;
    }

    /**
     * Verify the OTP code for a phone number via Twilio Verify API.
     */
    public function verifyOtp(string $phone, string $code, OtpType $type): bool
    {
        $approved = $this->twilioOtpService->verifyCode($phone, $code);

        if (! $approved) {
            return false;
        }

        // Find or create the verification record to mark as verified
        $otpRecord = OtpVerification::where('phone', $phone)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->orderBy('id', 'desc')
            ->first();

        if ($otpRecord) {
            $otpRecord->update([
                'verified_at' => Carbon::now(),
            ]);
        } else {
            OtpVerification::create([
                'phone' => $phone,
                'code' => strlen($code) <= 6 ? $code : null,
                'type' => $type,
                'expires_at' => Carbon::now()->addMinutes(15),
                'verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
            ]);
        }

        return true;
    }

    /**
     * Check if a phone number has been verified for registration/login within last 15 minutes.
     */
    public function isVerified(string $phone, OtpType $type): bool
    {
        return OtpVerification::where('phone', $phone)
            ->where('type', $type)
            ->whereNotNull('verified_at')
            ->where(function ($query) {
                $query->where('expires_at', '>', Carbon::now()->subMinutes(15))
                    ->orWhere('verified_at', '>', Carbon::now()->subMinutes(15));
            })
            ->exists();
    }
}
