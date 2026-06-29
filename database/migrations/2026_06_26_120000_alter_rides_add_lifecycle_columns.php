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
        Schema::table('rides', function (Blueprint $table) {
            $table->timestamp('otp_verified_at')->nullable()->after('otp');
            $table->foreignId('otp_verified_by')
                ->nullable()
                ->after('otp_verified_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->json('fare_breakdown')->nullable()->after('actual_fare');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropForeign(['otp_verified_by']);
            $table->dropColumn(['otp_verified_at', 'otp_verified_by', 'fare_breakdown']);
        });
    }
};
