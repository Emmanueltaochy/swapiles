<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les colonnes montant étaient en INTEGER (euros sans centimes) : 7,42 € de
     * Colissimo ou 0,50 € de protection étaient tronqués à l'enregistrement,
     * alors que Stripe était débité du montant exact. On passe en DECIMAL(10,2)
     * pour conserver les centimes. Les valeurs entières existantes restent
     * valides (7 -> 7.00).
     */
    private const COLUMNS = [
        'amount',
        'commission',
        'platform_commission',
        'buyer_protection_fee',
        'shipping_fee',
        'seller_amount',
    ];

    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->decimal($column, 10, 2)->default(0)->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->integer($column)->default(0)->change();
            }
        });
    }
};
