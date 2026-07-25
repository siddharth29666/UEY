<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioOtpService
{
    protected string $accountSid;
    protected string $authToken;
    protected string $verifyServiceSid;

    public function __construct()
    {
        $this->accountSid = (string) config('services.twilio.account_sid');
        $this->authToken = (string) config('services.twilio.auth_token');
        $this->verifyServiceSid = (string) config('services.twilio.verify_service_sid');
    }

    /**
     * Normalize phone number to E.164 format (e.g. +447911222222).
     */
    public function formatE164(string $phone): string
    {
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        if (! str_starts_with($cleaned, '+')) {
            return '+'.$cleaned;
        }

        return $cleaned;
    }

    /**
     * Send OTP verification code via Twilio Verify API.
     */
    public function sendVerificationCode(string $phone): void
    {
        $e164Phone = $this->formatE164($phone);

        // If credentials are mock/not set, log safe audit line and return
        if (empty($this->accountSid) || empty($this->authToken) || empty($this->verifyServiceSid) || $this->accountSid === 'your_twilio_account_sid') {
            Log::info('Twilio Verify: Mock send for phone', [
                'phone' => substr($e164Phone, 0, 4).'***'.substr($e164Phone, -2),
                'channel' => 'sms',
            ]);

            return;
        }

        try {
            $url = "https://verify.twilio.com/v2/Services/{$this->verifyServiceSid}/Verifications";
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->asForm()
                ->post($url, [
                    'To' => $e164Phone,
                    'Channel' => 'sms',
                ]);

            if (! $response->successful()) {
                Log::warning('Twilio Verify send failed', [
                    'phone' => substr($e164Phone, 0, 4).'***'.substr($e164Phone, -2),
                    'status' => $response->status(),
                ]);
                throw new \Exception('Failed to send verification code via SMS.');
            }
        } catch (\Exception $e) {
            Log::error('Twilio Verify Exception during send', [
                'message' => $e->getMessage(),
            ]);
            throw new \Exception('Unable to send OTP at this time. Please try again later.');
        }
    }

    /**
     * Verify OTP code via Twilio Verify API.
     */
    public function verifyCode(string $phone, string $code): bool
    {
        $e164Phone = $this->formatE164($phone);

        // Mock mode for tests or unconfigured env
        if (empty($this->accountSid) || empty($this->authToken) || empty($this->verifyServiceSid) || $this->accountSid === 'your_twilio_account_sid') {
            if (app()->environment('testing') || $code === '123456') {
                return true;
            }

            return false;
        }

        try {
            $url = "https://verify.twilio.com/v2/Services/{$this->verifyServiceSid}/VerificationCheck";
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->asForm()
                ->post($url, [
                    'To' => $e164Phone,
                    'Code' => $code,
                ]);

            if ($response->successful()) {
                $status = $response->json('status');

                return $status === 'approved';
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Twilio Verify Exception during check', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
