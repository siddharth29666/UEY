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
        Schema::create('favorite_places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type', 30); // home, work, saved
            $table->string('label')->nullable();
            $table->string('nickname')->nullable();
            $table->string('google_place_id')->nullable();
            $table->string('address');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->boolean('is_default')->default(false);
            $table->string('type_unique', 30)->nullable()->virtualAs("CASE WHEN type IN ('home', 'work') THEN type ELSE NULL END");
            $table->timestamps();

            $table->index('user_id');
            $table->index('type');
            $table->unique(['user_id', 'type_unique'], 'uq_user_home_work');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorite_places');
    }
};
