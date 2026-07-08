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
        // 1. user_devices table
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('device_type');
            $table->string('device_name');
            $table->string('device_token')->unique();
            $table->string('platform');
            $table->string('os_version')->nullable();
            $table->string('app_version')->nullable();
            $table->string('language')->nullable();
            $table->string('timezone')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        // 2. notification_logs table
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('body');
            $table->string('type', 30);
            $table->string('category', 30);
            $table->string('priority', 20);
            $table->json('payload')->nullable();
            $table->string('status', 30)->default('pending'); // pending, sent, failed, read
            $table->string('firebase_message_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // 3. notification_preferences table
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->boolean('ride_notifications')->default(true);
            $table->boolean('wallet_notifications')->default(true);
            $table->boolean('payment_notifications')->default(true);
            $table->boolean('promotion_notifications')->default(true);
            $table->boolean('system_notifications')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('user_devices');
    }
};
