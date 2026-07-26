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
        if (Schema::hasColumn('driver_profiles', 'current_lat')) {
            Schema::table('driver_profiles', function (Blueprint $table) {
                $table->renameColumn('current_lat', 'current_latitude');
            });
        }

        if (Schema::hasColumn('driver_profiles', 'current_lng')) {
            Schema::table('driver_profiles', function (Blueprint $table) {
                $table->renameColumn('current_lng', 'current_longitude');
            });
        }

        Schema::table('driver_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('driver_profiles', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->renameColumn('current_latitude', 'current_lat');
        });

        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->renameColumn('current_longitude', 'current_lng');
        });

        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
