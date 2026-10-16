<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lots : plusieurs articles du même dressing achetés en une fois.
 *
 * - panier_articles : le lot en préparation (par acheteur, un vendeur à la fois
 *   s'en déduit via l'annonce) ;
 * - lots : la commande groupée ; chaque article garde SA transaction (versement,
 *   remboursement article par article), toutes rattachées au lot et payées par
 *   un seul PaymentIntent ;
 * - users.lot_remise_* : la remise proposée par le vendeur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panier_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['buyer_id', 'listing_id']);
        });

        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('nb_articles');
            $table->unsignedTinyInteger('remise_pct')->default(0);
            $table->decimal('remise_montant', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('lot_id')->nullable()->after('listing_offer_id')->constrained('lots')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('lot_remise_pct')->default(0);
            $table->unsignedTinyInteger('lot_remise_min')->default(2);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lot_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['lot_remise_pct', 'lot_remise_min']);
        });
        Schema::dropIfExists('lots');
        Schema::dropIfExists('panier_articles');
    }
};
