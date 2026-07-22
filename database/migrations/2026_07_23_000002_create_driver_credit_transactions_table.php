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
        Schema::create('driver_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained('driver_profiles')->onDelete('cascade');
            $table->foreignId('driver_subscription_id')->nullable()->constrained('driver_subscriptions')->onDelete('set null');
            $table->foreignId('ride_id')->nullable()->constrained('rides')->onDelete('set null');
            $table->foreignId('ride_request_id')->nullable()->constrained('ride_requests')->onDelete('set null');
            $table->string('type', 40); // subscription_purchase, ride_accept, manual_adjustment, expiry, refund
            $table->integer('amount'); // +50 or -1
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->string('reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('driver_profile_id');
            $table->index('driver_subscription_id');
            $table->index('ride_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_credit_transactions');
    }
};
