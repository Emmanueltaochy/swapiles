<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Support\ComptePro;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Espace des vendeurs professionnels : leurs ventes (montant TTC, commission,
 * net reçu), les retraits en boutique à valider, et le bon de commande de
 * chaque vente (base de la facture que le pro remet à son client).
 */
class ProController extends Controller
{
    public function ventes(Request $request)
    {
        $pro = $request->user();
        abort_unless(ComptePro::estPro($pro), 404);

        $mois = $request->filled('mois') && preg_match('/^\d{4}-\d{2}$/', (string) $request->input('mois'))
            ? Carbon::createFromFormat('Y-m-d', $request->input('mois') . '-01')->startOfMonth()
            : now()->startOfMonth();

        $ventesDuMois = Transaction::query()
            ->with(['listing', 'buyer'])
            ->where('seller_id', $pro->id)
            ->where('vendeur_pro', true)
            ->whereIn('status', ['paid', 'completed'])
            ->whereBetween('created_at', [$mois, $mois->copy()->endOfMonth()])
            ->latest()
            ->get();

        $aRetirer = Transaction::query()
            ->with(['listing', 'buyer'])
            ->where('seller_id', $pro->id)
            ->where('vendeur_pro', true)
            ->where('status', 'paid')
            ->whereNotNull('code_retrait')
            ->latest()
            ->get();

        $aExpedier = Transaction::query()
            ->with(['listing', 'buyer'])
            ->where('seller_id', $pro->id)
            ->where('vendeur_pro', true)
            ->where('status', 'paid')
            ->where('delivery_method', 'livraison_pro')
            ->whereNull('shipped_at')
            ->latest()
            ->get();

        $articles = fn (Transaction $t) => (float) $t->seller_amount + (float) $t->commission
            - ($t->delivery_method === 'livraison_pro' ? (float) $t->shipping_fee : 0.0);

        return view('account.pro.ventes', [
            'pro' => $pro,
            'mois' => $mois,
            'ventes' => $ventesDuMois,
            'aRetirer' => $aRetirer,
            'aExpedier' => $aExpedier,
            'totaux' => [
                'nombre' => $ventesDuMois->count(),
                'chiffre' => round($ventesDuMois->sum($articles), 2),
                'commission' => round((float) $ventesDuMois->sum('commission'), 2),
                'net' => round((float) $ventesDuMois->sum('seller_amount'), 2),
                'verse' => round((float) $ventesDuMois->whereNotNull('released_at')->sum('seller_amount'), 2),
            ],
            'taux' => ComptePro::taux($pro),
        ]);
    }

    /** Bon de commande imprimable, pour l'acheteur et le pro. */
    public function bonDeCommande(Request $request, Transaction $transaction)
    {
        $moi = $request->user()->id;
        abort_unless($transaction->buyer_id === $moi || $transaction->seller_id === $moi || $request->user()->isAdmin(), 403);
        abort_unless($transaction->vendeur_pro, 404);

        return view('account.pro.bon-de-commande', [
            't' => $transaction->load(['listing', 'buyer', 'seller']),
            'estVendeur' => $transaction->seller_id === $moi,
        ]);
    }
}
