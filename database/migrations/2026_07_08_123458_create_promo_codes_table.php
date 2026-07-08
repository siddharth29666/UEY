<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('discount_type'); // percentage, flat
            $table->decimal('discount_value', 10, 2);
            $table->dateTime('expires_at');
            $table->integer('usage_limit')->nullable();
            $table->integer('used_count')->default(0);
            $table->integer('per_user_limit')->default(1);
            $table->decimal('min_fare', 10, 2)->default(0.00);
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('first_ride_only')->default(false);
            $table->boolean('referral_coupon')->default(false);
            $table->json('ride_eligibility')->nullable(); // JSON list of eligible vehicle_type_ids
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
