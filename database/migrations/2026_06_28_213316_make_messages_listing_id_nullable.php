<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL (prod) : ALTER natif. SQLite (tests) : schema builder portable.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE messages MODIFY listing_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('messages', function ($table) {
                $table->unsignedBigInteger('listing_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE messages MODIFY listing_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
