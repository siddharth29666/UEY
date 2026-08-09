<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Rename subscription_plans.price_eur to price_gbp
        if (Schema::hasColumn('subscription_plans', 'price_eur') && ! Schema::hasColumn('subscription_plans', 'price_gbp')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->renameColumn('price_eur', 'price_gbp');
            });
        }

        // 2. Rename driver_subscriptions.amount_eur to amount_gbp
        if (Schema::hasColumn('driver_subscriptions', 'amount_eur') && ! Schema::hasColumn('driver_subscriptions', 'amount_gbp')) {
            Schema::table('driver_subscriptions', function (Blueprint $table) {
                $table->renameColumn('amount_eur', 'amount_gbp');
            });
        }

        // 3. Update driver_subscriptions.currency default to 'gbp'
        if (Schema::hasColumn('driver_subscriptions', 'currency')) {
            Schema::table('driver_subscriptions', function (Blueprint $table) {
                $table->string('currency', 10)->default('gbp')->change();
            });
        }

        // 4. Update wallets.currency default to 'GBP'
        if (Schema::hasColumn('wallets', 'currency')) {
            Schema::table('wallets', function (Blueprint $table) {
                $table->string('currency', 10)->default('GBP')->change();
            });

            // Update existing USD/EUR wallet currency records to GBP if appropriate
            DB::table('wallets')->whereIn('currency', ['USD', 'EUR', 'eur'])->update(['currency' => 'GBP']);
        }

        // 5. Update settings table currency key to GBP
        if (Schema::hasTable('settings')) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'currency'],
                ['value' => 'GBP']
            );

            if (app()->bound(\App\Services\SettingService::class)) {
                app(\App\Services\SettingService::class)->refreshCache();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('subscription_plans', 'price_gbp')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->renameColumn('price_gbp', 'price_eur');
            });
        }

        if (Schema::hasColumn('driver_subscriptions', 'amount_gbp')) {
            Schema::table('driver_subscriptions', function (Blueprint $table) {
                $table->renameColumn('amount_gbp', 'amount_eur');
            });
        }
    }
};
