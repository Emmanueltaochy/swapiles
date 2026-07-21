<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Restaure les prix corrects depuis le JSON Sharetribe.
 *
 * Stocke les prix DIRECTEMENT EN EUROS (pas en centimes) dans la base,
 * en cohérence avec le choix Option A (affichage en euros).
 *
 * Le JSON Sharetribe contient les prix en euros avec décimales (ex: 15.00).
 * On stocke 15 dans la colonne price (INT), arrondi à l'unité.
 *
 * Idempotent : peut être relancé sans risque.
 */
class RepairPricesFromJson extends Command
{
    protected $signature = 'sharetribe:repair-prices
                            {file : Chemin vers le fichier JSON Sharetribe}
                            {--dry-run : Simule sans modifier la base}';

    protected $description = 'Restaure les prix des listings et transactions depuis le JSON Sharetribe original';

    private array $stats = [
        'listings_processed' => 0,
        'listings_updated'   => 0,
        'listings_skipped'   => 0,
        'transactions_processed' => 0,
        'transactions_updated'   => 0,
        'transactions_skipped'   => 0,
    ];

    public function handle(): int
    {
        $file = $this->argument('file');
        $dryRun = $this->option('dry-run');

        if (! file_exists($file)) {
            $this->error("Fichier introuvable : {$file}");
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('━━━ MODE DRY-RUN : aucune modification en base ━━━');
        }

        $this->info("Lecture du JSON : {$file}");
        $raw = file_get_contents($file);
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            $this->error('JSON invalide.');
            return self::FAILURE;
        }

        // Construire des index par UUID pour lookup rapide
        $listingPrices = [];     // [uuid => priceEur]
        $listingShippings = [];  // [uuid => shippingEur]
        $transactionData = [];   // [uuid => [amountEur, commissionEur]]

        foreach ($data as $item) {
            $type = $item['type'] ?? null;
            $uuid = $item['id'] ?? null;
            $attrs = $item['attributes'] ?? [];

            if (! $type || ! $uuid) continue;

            if ($type === 'listing') {
                $priceEur = $attrs['price']['amount'] ?? 0;
                // Le prix Sharetribe est déjà en EUROS (ex: 15.00)
                // On l'arrondit à l'entier le plus proche pour stocker en INT
                $listingPrices[$uuid] = (int) round($priceEur);

                $publicData = $attrs['publicData'] ?? [];
                $shippingCents = $publicData['shippingPriceInSubunitsOneItem'] ?? 0;
                // shippingPriceInSubunitsOneItem est en centimes (subunits), on convertit en euros
                $listingShippings[$uuid] = (int) round($shippingCents / 100);
            }

            if ($type === 'transaction') {
                $amountEur = isset($attrs['payinTotal']['amount']) ? (int) round($attrs['payinTotal']['amount']) : 0;
                $commission = $this->extractCommission($attrs['lineItems'] ?? []);
                $transactionData[$uuid] = [
                    'amount' => $amountEur,
                    'commission' => $commission,
                ];
            }
        }

        $this->info('Prix listings dans le JSON : '.count($listingPrices));
        $this->info('Données transactions dans le JSON : '.count($transactionData));
        $this->newLine();

        // ═══════════════════════════════════════════════════════════════════
        // RÉPARATION LISTINGS
        // ═══════════════════════════════════════════════════════════════════
        $this->info('━━━ Réparation LISTINGS ━━━');
        $listings = Listing::whereNotNull('sharetribe_id')->get();
        $bar = $this->output->createProgressBar($listings->count());
        $bar->start();

        foreach ($listings as $listing) {
            $this->stats['listings_processed']++;

            if (! isset($listingPrices[$listing->sharetribe_id])) {
                $this->stats['listings_skipped']++;
                $bar->advance();
                continue;
            }

            $newPrice = $listingPrices[$listing->sharetribe_id];
            $newShipping = $listingShippings[$listing->sharetribe_id] ?? 0;

            if (! $dryRun) {
                $listing->update([
                    'price' => $newPrice,
                    'shipping_price' => $newShipping,
                ]);
            }

            $this->stats['listings_updated']++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // ═══════════════════════════════════════════════════════════════════
        // RÉPARATION TRANSACTIONS
        // ═══════════════════════════════════════════════════════════════════
        $this->info('━━━ Réparation TRANSACTIONS ━━━');
        $transactions = Transaction::whereNotNull('sharetribe_id')->get();

        if ($transactions->count() > 0) {
            $bar = $this->output->createProgressBar($transactions->count());
            $bar->start();

            foreach ($transactions as $tx) {
                $this->stats['transactions_processed']++;

                if (! isset($transactionData[$tx->sharetribe_id])) {
                    $this->stats['transactions_skipped']++;
                    $bar->advance();
                    continue;
                }

                $newData = $transactionData[$tx->sharetribe_id];

                if (! $dryRun) {
                    $tx->update([
                        'amount' => $newData['amount'],
                        'commission' => $newData['commission'],
                    ]);
                }

                $this->stats['transactions_updated']++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
        } else {
            $this->warn('Aucune transaction à traiter.');
            $this->newLine();
        }

        $this->printReport();

        return self::SUCCESS;
    }

    private function extractCommission(array $lineItems): int
    {
        foreach ($lineItems as $item) {
            if (($item['code'] ?? '') === 'line-item/provider-commission') {
                $amount = $item['lineTotal']['amount'] ?? 0;
                return (int) round(abs($amount));
            }
        }
        return 0;
    }

    private function printReport(): void
    {
        $this->info('═══════════════════════════════════════════════════');
        $this->info('  RAPPORT DE RÉPARATION');
        $this->info('═══════════════════════════════════════════════════');

        $this->table(
            ['Type', 'Traités', 'Mis à jour', 'Skip (pas dans JSON)'],
            [
                ['Listings',     $this->stats['listings_processed'],     $this->stats['listings_updated'],     $this->stats['listings_skipped']],
                ['Transactions', $this->stats['transactions_processed'], $this->stats['transactions_updated'], $this->stats['transactions_skipped']],
            ]
        );

        if (! $this->option('dry-run')) {
            $this->newLine();
            $this->info('Vérification post-réparation :');
            $this->line('  Prix moyen listings : '.round(Listing::avg('price'), 2).' €');
            $this->line('  Prix min listings : '.Listing::min('price').' €');
            $this->line('  Prix max listings : '.Listing::max('price').' €');
            if (Transaction::count() > 0) {
                $this->line('  Amount max transactions : '.Transaction::max('amount').' €');
            }
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('━━━ DRY-RUN : aucune donnée n\'a été écrite. ━━━');
        }
    }
}
