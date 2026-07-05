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
        Schema::create('ride_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_id')->constrained('rides')->onDelete('cascade');
            $table->foreignId('reviewer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewee_id')->constrained('users')->onDelete('cascade');
            $table->unsignedTinyInteger('rating');
            $table->text('review')->nullable();
            $table->json('review_tags')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->timestamps();

            $table->unique(['ride_id', 'reviewer_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('rating', 3, 2)->default(5.00)->after('status');
            $table->integer('total_reviews')->default(0)->after('rating');
        });

        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->integer('total_reviews')->default(0)->after('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ride_reviews');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rating', 'total_reviews']);
        });

        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropColumn('total_reviews');
        });
    }
};
