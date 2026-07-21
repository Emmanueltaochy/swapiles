<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('address_line1')->nullable()->after('phone');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('postal_code', 20)->nullable()->after('address_line2');
            $table->string('city')->nullable()->after('postal_code');
            $table->string('country_code', 2)->default('FR')->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'address_line1',
                'address_line2',
                'postal_code',
                'city',
                'country_code',
            ]);
        });
    }
};
