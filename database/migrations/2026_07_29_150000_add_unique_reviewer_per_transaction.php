<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un même auteur ne peut laisser qu'UN avis par transaction (anti-doublon).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            try {
                $table->unique(['transaction_id', 'reviewer_id'], 'reviews_tx_reviewer_unique');
            } catch (\Throwable $e) {
                // index déjà présent : no-op idempotent.
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            try {
                $table->dropUnique('reviews_tx_reviewer_unique');
            } catch (\Throwable $e) {
                //
            }
        });
    }
};
