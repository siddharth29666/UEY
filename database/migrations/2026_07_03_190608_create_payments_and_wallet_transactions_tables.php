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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_id')->constrained('rides')->onDelete('cascade');
            $table->foreignId('rider_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('driver_profile_id')->nullable()->constrained('driver_profiles')->onDelete('set null');
            $table->string('payment_method', 30);
            $table->string('payment_status', 30);
            $table->string('transaction_reference')->nullable()->unique();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0.00);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('platform_commission', 10, 2);
            $table->decimal('driver_earning', 10, 2);
            $table->decimal('total', 10, 2);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->string('type', 30); // credit, debit
            $table->decimal('amount', 10, 2);
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('payments');
    }
};
