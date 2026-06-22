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
        Schema::create('driver_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->unique()->constrained('driver_profiles')->onDelete('cascade');
            $table->string('bank_name', 100);
            $table->string('account_holder_name', 255);
            $table->string('account_number', 255);
            $table->string('routing_number', 50)->nullable();
            $table->string('swift_code', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_bank_accounts');
    }
};
