<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Support\SellerWallet;
use Illuminate\Console\Command;

/**
 * Contrôle du bug « solde fictif » : recense les ventes conclues (paid/completed)
 * SANS PaymentIntent Stripe (espèces, dons, échanges). Avec la nouvelle règle
 * (SellerWallet), ces lignes ne comptent JAMAIS dans le solde retirable — cette
 * commande le prouve chiffres à l'appui. Le solde étant calculé à la volée, il
 * n'y a rien à « purger » en base : le filtre exclut ces lignes partout.
 *
 *   php artisan wallet:audit
 */
class AuditWalletBalances extends Command
{
    protected $signature = 'wallet:audit';

    protected $description = 'Vérifie qu\'aucune vente sans PaymentIntent Stripe n\'entre dans le solde vendeur';

    public function handle(): int
    {
        $sales = Transaction::whereIn('status', ['paid', 'completed'])->get();

        $secured = SellerWallet::securedOnly($sales);
        $unsecured = $sales->reject(fn (Transaction $t) => SellerWallet::isSecured($t));

        $securedTotal = round($secured->sum(fn (Transaction $t) => SellerWallet::net($t)), 2);
        $unsecuredTotal = round($unsecured->sum(fn (Transaction $t) => SellerWallet::net($t)), 2);

        $this->info('Ventes conclues : ' . $sales->count());
        $this->line('  Sécurisées (Stripe, comptent au solde) : ' . $secured->count() . " ({$securedTotal} €)");
        $this->line('  Hors plateforme (JAMAIS au solde)       : ' . $unsecured->count() . " ({$unsecuredTotal} €)");

        // Détail par vendeur des lignes qui, avant le correctif, gonflaient le solde.
        $byMode = SellerWallet::totalsByMode($sales);
        $this->newLine();
        $this->table(
            ['Mode', 'Montant total'],
            collect($byMode)
                ->filter(fn ($v, $k) => $v > 0 || in_array($k, ['online', 'cash']))
                ->map(fn ($v, $k) => [SellerWallet::modeLabel($k), number_format($v, 2, ',', ' ') . ' €'])
                ->values()
                ->all()
        );

        $offenders = $unsecured->filter(fn (Transaction $t) => SellerWallet::net($t) > 0)
            ->groupBy('seller_id');

        if ($offenders->isNotEmpty()) {
            $this->warn($offenders->count() . ' vendeur(s) ont des ventes hors plateforme (espèces) — désormais correctement exclues du solde :');
            foreach ($offenders as $sellerId => $lines) {
                $sum = round($lines->sum(fn (Transaction $t) => SellerWallet::net($t)), 2);
                $this->line("  Vendeur #{$sellerId} : {$lines->count()} vente(s), {$sum} € (hors solde)");
            }
        } else {
            $this->info('Aucune vente hors plateforme : rien à exclure.');
        }

        return self::SUCCESS;
    }
}
