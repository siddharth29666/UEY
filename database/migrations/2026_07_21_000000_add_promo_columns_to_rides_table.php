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
            $table->decimal('discount_amount', 10, 2)->default(0.00)->after('estimated_fare');
            $table->decimal('final_estimated_fare', 10, 2)->default(0.00)->after('discount_amount');
            $table->decimal('actual_discount_amount', 10, 2)->default(0.00)->after('actual_fare');
            $table->decimal('final_actual_fare', 10, 2)->default(0.00)->after('actual_discount_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn([
                'discount_amount',
                'final_estimated_fare',
                'actual_discount_amount',
                'final_actual_fare',
            ]);
        });
    }
};
