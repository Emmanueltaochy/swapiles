<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (!Schema::hasColumn('users', 'stripe_account_id')) {
                $table->string('stripe_account_id')->nullable();
            }

            if (!Schema::hasColumn('users', 'stripe_onboarding_complete')) {
                $table->boolean('stripe_onboarding_complete')->default(false);
            }

        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_account_id',
                'stripe_onboarding_complete',
            ]);
        });
    }
};
