<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Point de contrôle #1 : dump JSON stable de toutes les colonnes montant des
 * transactions (id + 6 colonnes), pour comparer AVANT / APRÈS la migration
 * DECIMAL(10,2) et prouver qu'aucune valeur n'a bougé.
 *
 * Sortie : JSON sur stdout, trié par id, montants normalisés en float. Un 7
 * (INTEGER) et un 7.00 (DECIMAL) donnent la même valeur -> une différence dans
 * le diff = une VRAIE altération de donnée.
 *
 *   php artisan transactions:snapshot-money > before.json   # avant migrate
 *   php artisan transactions:snapshot-money > after.json     # après migrate
 */
class SnapshotTransactionMoney extends Command
{
    protected $signature = 'transactions:snapshot-money';

    protected $description = 'Dump JSON des colonnes montant des transactions (contrôle d\'intégrité migration)';

    private const COLUMNS = [
        'amount', 'commission', 'platform_commission',
        'buyer_protection_fee', 'shipping_fee', 'seller_amount',
    ];

    public function handle(): int
    {
        $rows = DB::table('transactions')
            ->select(array_merge(['id'], self::COLUMNS))
            ->orderBy('id')
            ->get()
            ->map(function ($r) {
                $out = ['id' => (int) $r->id];
                foreach (self::COLUMNS as $c) {
                    // Normalisation numérique : 7 et 7.00 -> 7.0 (comparables).
                    $out[$c] = $r->$c === null ? null : round((float) $r->$c, 2);
                }
                return $out;
            })
            ->values();

        $this->output->writeln(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
