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
        // 1. Driver Locations History
        Schema::create('driver_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('ride_id')->nullable()->constrained('rides')->onDelete('set null');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->float('heading')->nullable();
            $table->float('speed')->nullable();
            $table->float('accuracy')->nullable();
            $table->bigInteger('timestamp')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('driver_id');
            $table->index('ride_id');
            $table->index('created_at');
        });

        // 2. Conversation Threads (One conversation per ride)
        Schema::create('conversation_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_id')->constrained('rides')->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('rider_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Indexes
            $table->index('ride_id');
            $table->index('driver_id');
            $table->index('rider_id');
            $table->index('created_at');
        });

        // 3. Conversation Messages
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_thread_id')->constrained('conversation_threads')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->string('type')->default('text'); // text, image, location
            $table->string('status')->default('sent'); // sent, delivered, read
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('conversation_thread_id');
            $table->index('sender_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversation_threads');
        Schema::dropIfExists('driver_locations');
    }
};
