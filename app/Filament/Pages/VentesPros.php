<?php

namespace App\Filament\Pages;

use App\Models\Transaction;
use App\Models\User;
use App\Support\ComptePro;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * Ventes des vendeurs professionnels, par mois : chiffre d'affaires, commission
 * Swap'Îles à facturer à chaque pro, montant qui lui revient.
 */
class VentesPros extends Page
{
    protected static ?string $navigationLabel = 'Vendeurs pros';

    protected static ?string $title = 'Vendeurs professionnels';

    protected static ?string $slug = 'vendeurs-pros';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string | \UnitEnum | null $navigationGroup = 'Valeur & marché';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.ventes-pros';

    public string $mois = '';

    public function mount(): void
    {
        $this->mois = (string) request()->query('mois', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $this->mois)) {
            $this->mois = now()->format('Y-m');
        }
    }

    public function getSubheading(): ?string
    {
        return 'Commission à facturer à chaque pro (ventes payées par carte, hors annulations). Les pros se règlent depuis « Membres » → « Compte pro / lives ».';
    }

    public function getViewData(): array
    {
        $debut = Carbon::createFromFormat('Y-m-d', $this->mois . '-01')->startOfMonth();
        $fin = $debut->copy()->endOfMonth();

        $ventes = Transaction::query()
            ->where('vendeur_pro', true)
            ->whereIn('status', ['paid', 'completed'])
            ->whereBetween('created_at', [$debut, $fin])
            ->get(['seller_id', 'seller_amount', 'commission', 'shipping_fee', 'delivery_method', 'released_at']);

        $lignes = $ventes->groupBy('seller_id')->map(function ($v, $id) {
            $pro = User::find($id);

            return [
                'pro' => $pro,
                'nombre' => $v->count(),
                'chiffre' => round($v->sum(fn ($t) => (float) $t->seller_amount + (float) $t->commission - ($t->delivery_method === 'livraison_pro' ? (float) $t->shipping_fee : 0)), 2),
                'commission' => round((float) $v->sum('commission'), 2),
                'net' => round((float) $v->sum('seller_amount'), 2),
                'verse' => round((float) $v->whereNotNull('released_at')->sum('seller_amount'), 2),
            ];
        })->sortByDesc('chiffre')->values();

        return [
            'debut' => $debut,
            'lignes' => $lignes,
            'pros' => User::query()->whereNotNull('pro_valide_at')->orderBy('pro_raison_sociale')->get()->filter(fn ($u) => ComptePro::estPro($u)),
            'precedent' => $debut->copy()->subMonth()->format('Y-m'),
            'suivant' => $debut->copy()->addMonth()->lte(now()) ? $debut->copy()->addMonth()->format('Y-m') : null,
        ];
    }
}
