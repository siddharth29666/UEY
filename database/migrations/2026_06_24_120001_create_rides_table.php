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
        Schema::create('rides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('driver_profile_id')->nullable()->constrained('driver_profiles')->onDelete('set null');
            $table->foreignId('vehicle_type_id')->constrained('vehicle_types')->onDelete('cascade');
            $table->string('pickup_address');
            $table->decimal('pickup_latitude', 10, 8);
            $table->decimal('pickup_longitude', 11, 8);
            $table->string('destination_address');
            $table->decimal('destination_latitude', 10, 8);
            $table->decimal('destination_longitude', 11, 8);
            $table->string('status', 30)->default('pending');
            $table->string('otp', 6);
            $table->decimal('estimated_distance', 8, 2);
            $table->integer('estimated_duration'); // in minutes
            $table->decimal('estimated_fare', 10, 2);
            $table->decimal('actual_distance', 8, 2)->nullable();
            $table->integer('actual_duration')->nullable();
            $table->decimal('actual_fare', 10, 2)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 30)->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
