<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BACKFILL seller_amount (point de contrôle #2).
 *
 * La colonne seller_amount a été ajoutée le 2026-05-21 avec DEFAULT 0. Toute
 * transaction créée AVANT — et toute transaction dont le tunnel n'a pas
 * renseigné la colonne — porte donc seller_amount = 0 (et NON NULL). Or les
 * chaînes de payout lisent seller_amount pour verser le vendeur : une ligne
 * à 0 ferait un virement de 0 € (ReleaseMarketplaceFunds) ou basculerait sur
 * un fallback (ReleasePendingPayouts / TransactionWorkflowController).
 *
 * On fige donc la valeur historique : seller_amount = amount - protection -
 * livraison (jamais négatif), qui est exactement la part vendeur d'une
 * commission 0 %. On ne touche QUE les lignes à seller_amount <= 0 ; toute
 * ligne déjà renseignée est laissée telle quelle.
 *
 * Idempotent, et sans effet si aucune ligne n'est concernée.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Portable (MySQL + SQLite) : on calcule la part vendeur en PHP plutôt
        // que via GREATEST (absent de SQLite). Volumes concernés faibles.
        DB::table('transactions')
            ->select('id', 'amount', 'buyer_protection_fee', 'shipping_fee')
            ->where(function ($q) {
                $q->whereNull('seller_amount')->orWhere('seller_amount', '<=', 0);
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $seller = max(0.0, (float) $row->amount
                        - (float) $row->buyer_protection_fee
                        - (float) $row->shipping_fee);

                    DB::table('transactions')
                        ->where('id', $row->id)
                        ->update(['seller_amount' => $seller]);
                }
            });
    }

    public function down(): void
    {
        // Migration de données : pas de restauration automatique de l'ancienne
        // valeur (0 par défaut). Le down du schéma est géré par la migration
        // DECIMAL. No-op volontaire.
    }
};
