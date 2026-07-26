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
        // 1. Update wallets table
        Schema::table('wallets', function (Blueprint $table) {
            if (!Schema::hasColumn('wallets', 'currency')) {
                $table->string('currency', 10)->default('USD')->after('balance');
            }
            if (!Schema::hasColumn('wallets', 'status')) {
                $table->string('status', 30)->default('active')->after('currency');
            }
        });

        // 2. Drop and Recreate wallet_transactions for SQLite compatibility
        Schema::dropIfExists('wallet_transactions');

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->string('type', 30); // credit, debit
            $table->string('transaction_type', 30); // top_up, ride_payment, ride_earning, withdrawal, refund, referral_bonus, admin_credit, admin_debit
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('status', 30)->default('completed'); // pending, processing, completed, failed
            $table->string('payment_gateway', 30)->nullable();
            $table->string('gateway_reference')->nullable();
            $table->string('reference')->nullable();
            $table->string('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 3. Create wallet_topups table
        Schema::create('wallet_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('stripe_payment_intent')->nullable()->unique();
            $table->string('payment_status', 30)->default('pending'); // pending, completed, failed
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // 4. Create withdrawal_requests table
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('status', 30)->default('pending'); // pending, approved, rejected, completed
            $table->integer('bank_account_id')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        // 5. Create processed_stripe_events table for webhook idempotency
        Schema::create('processed_stripe_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_stripe_events');
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('wallet_topups');

        // Restore original wallet_transactions
        Schema::dropIfExists('wallet_transactions');
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->string('type', 30);
            $table->decimal('amount', 10, 2);
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn(['currency', 'status']);
        });
    }
};
