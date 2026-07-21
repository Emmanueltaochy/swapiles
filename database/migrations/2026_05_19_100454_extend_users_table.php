<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sharetribe_id')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('avatar')->nullable();
            $table->string('stripe_account_id')->nullable();
            $table->string('territoire')->nullable()->default('la-reunion');
            $table->string('comment_connu')->nullable();
            $table->boolean('is_pro')->default(false);
            $table->boolean('is_banned')->default(false);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('transactions_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'sharetribe_id', 'phone', 'avatar',
                'stripe_account_id', 'territoire',
                'comment_connu', 'is_pro', 'is_banned',
                'rating', 'transactions_count'
            ]);
        });
    }
};
