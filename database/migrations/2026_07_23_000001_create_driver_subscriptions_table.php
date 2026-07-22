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
        Schema::create('driver_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained('driver_profiles')->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->onDelete('cascade');
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->decimal('amount_eur', 10, 2);
            $table->string('currency', 10)->default('eur');
            $table->integer('credits_allocated');
            $table->integer('credits_used')->default(0);
            $table->integer('credits_remaining');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 30)->default('pending'); // pending, active, expired, cancelled, payment_failed
            $table->timestamps();

            $table->index('driver_profile_id');
            $table->index('subscription_plan_id');
            $table->index('status');
            $table->index('expires_at');
            $table->index('stripe_checkout_session_id');
            $table->index('stripe_payment_intent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_subscriptions');
    }
};
