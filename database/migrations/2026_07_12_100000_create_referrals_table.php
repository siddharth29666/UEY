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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('referred_user_id')->constrained('users')->onDelete('cascade');
            $table->string('referral_code', 20);
            $table->string('status', 30)->default('pending'); // pending, completed
            $table->timestamp('first_ride_completed_at')->nullable();
            $table->decimal('referrer_bonus', 10, 2);
            $table->decimal('referred_bonus', 10, 2);
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->index('referral_code');
            $table->index('referrer_id');
            $table->index('referred_user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
