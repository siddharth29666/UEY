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
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('license_number', 100)->unique();
            $table->date('license_expiry');
            $table->boolean('is_online')->default(false);
            $table->decimal('rating', 3, 2)->default(5.00);
            $table->decimal('experience_years', 3, 1)->default(0.0);
            $table->decimal('acceptance_rate', 5, 2)->default(100.00);
            $table->decimal('ontime_rate', 5, 2)->default(100.00);
            $table->integer('total_online_hours')->unsigned()->default(0);
            
            // Preferences
            $table->string('default_navigation', 50)->default('google_maps');
            $table->boolean('auto_accept')->default(false);
            
            // Location
            $table->decimal('current_lat', 10, 8)->nullable();
            $table->decimal('current_lng', 11, 8)->nullable();
            $table->decimal('bearing', 5, 2)->nullable();
            $table->timestamp('last_located_at')->nullable();
            
            $table->timestamps();

            $table->index('is_online', 'idx_driver_online');
            $table->index(['current_lat', 'current_lng'], 'idx_driver_coordinates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_profiles');
    }
};
