<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            ['key' => 'referral_bonus_referrer', 'value' => '10.00', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'referral_bonus_referred', 'value' => '5.00', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ride_timeout_minutes', 'value' => '10', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'otp_expiry_minutes', 'value' => '5', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'promo_cleanup_days', 'value' => '30', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'driver_offline_minutes', 'value' => '15', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'wallet_settlement_time', 'value' => '00:00', 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'created_at' => $setting['created_at'], 'updated_at' => $setting['updated_at']]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'referral_bonus_referrer',
            'referral_bonus_referred',
            'ride_timeout_minutes',
            'otp_expiry_minutes',
            'promo_cleanup_days',
            'driver_offline_minutes',
            'wallet_settlement_time',
        ])->delete();
    }
};
