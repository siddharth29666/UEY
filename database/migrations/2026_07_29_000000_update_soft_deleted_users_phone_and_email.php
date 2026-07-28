<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Mutate any existing soft-deleted users in database to free up their phone and email
        $softDeletedUsers = DB::table('users')->whereNotNull('deleted_at')->get();

        foreach ($softDeletedUsers as $user) {
            $updates = [];
            $suffix = '_deleted_' . time() . '_' . Str::random(4);

            if ($user->phone && ! str_contains($user->phone, '_deleted_')) {
                $updates['phone'] = substr($user->phone, 0, 180) . $suffix;
            }
            if ($user->email && ! str_contains($user->email, '_deleted_')) {
                $updates['email'] = substr($user->email, 0, 180) . $suffix;
            }

            if (! empty($updates)) {
                DB::table('users')->where('id', $user->id)->update($updates);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse mutation required for deleted record suffixes
    }
};
