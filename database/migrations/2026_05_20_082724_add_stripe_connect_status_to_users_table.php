<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'stripe_charges_enabled')) {
                $table->boolean('stripe_charges_enabled')->default(false)->after('stripe_account_id');
            }

            if (!Schema::hasColumn('users', 'stripe_payouts_enabled')) {
                $table->boolean('stripe_payouts_enabled')->default(false)->after('stripe_charges_enabled');
            }

            if (!Schema::hasColumn('users', 'stripe_details_submitted')) {
                $table->boolean('stripe_details_submitted')->default(false)->after('stripe_payouts_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_charges_enabled',
                'stripe_payouts_enabled',
                'stripe_details_submitted',
            ]);
        });
    }
};
