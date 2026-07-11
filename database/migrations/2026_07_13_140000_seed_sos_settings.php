<?php

use App\Services\SettingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'maximum_emergency_contacts'],
            ['value' => '5', 'created_at' => now(), 'updated_at' => now()]
        );

        try {
            if (app()->bound(SettingService::class)) {
                app(SettingService::class)->refreshCache();
            }
        } catch (Exception $e) {
            // Ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->where('key', 'maximum_emergency_contacts')->delete();

        try {
            if (app()->bound(SettingService::class)) {
                app(SettingService::class)->refreshCache();
            }
        } catch (Exception $e) {
            // Ignore
        }
    }
};
