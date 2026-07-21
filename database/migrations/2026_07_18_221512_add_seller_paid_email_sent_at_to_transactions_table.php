<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transactions', 'seller_paid_email_sent_at')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->timestamp('seller_paid_email_sent_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'seller_paid_email_sent_at')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('seller_paid_email_sent_at');
            });
        }
    }
};
