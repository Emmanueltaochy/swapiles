<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les transactions "hors ligne" (paiement en espèces, don, échange remis en
 * main propre) n'ont pas d'acheteur avec compte enregistré côté plateforme.
 * On rend donc buyer_id nullable pour permettre au vendeur de clôturer ce type
 * de vente sans erreur (contrainte NOT NULL qui provoquait une erreur 500).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transactions', 'buyer_id')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('buyer_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // On ne repasse pas en NOT NULL : des lignes avec buyer_id null peuvent
        // désormais exister (ventes en espèces / dons / échanges).
    }
};
