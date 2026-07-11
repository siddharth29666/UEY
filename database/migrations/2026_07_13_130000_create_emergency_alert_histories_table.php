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
        Schema::create('emergency_alert_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emergency_alert_id')->constrained('emergency_alerts')->onDelete('cascade');
            $table->string('status', 30);
            $table->text('message');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index('emergency_alert_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergency_alert_histories');
    }
};
