<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\RelayPoint;
use App\Models\Transaction;
use App\Support\AdminEvent;
use App\Support\SellerPayout;
use App\Support\VersementsRelais;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Espace commerçant (gérant de point relais) : réception des colis, remise à
 * l'acheteur avec vérification du code de retrait, et suivi du solde gagné
 * (1 € par colis remis).
 */
class RelayDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        abort_unless($user->managesAnyRelay(), 403);

        $points = $user->managedRelayPoints()->get();
        $pointIds = $points->pluck('id');

        $base = Transaction::query()
            ->whereIn('relay_point_id', $pointIds)
            ->where('delivery_method', 'relay')
            ->with(['listing', 'buyer', 'seller', 'relayPoint'])
            ->latest('id');

        $toReceive = (clone $base)->where('relay_status', 'awaiting_deposit')->get();
        $toHandOver = (clone $base)->where('relay_status', 'deposited')->get();
        $toReturn = (clone $base)->where('relay_status', 'expired')->get();
        $recentCollected = (clone $base)->whereIn('relay_status', RelayPoint::STATUTS_REMUNERES)->limit(20)->get();

        $soldes = VersementsRelais::soldes($pointIds);
        $balance = $soldes['gagne'];
        $collectedCount = (int) Transaction::whereIn('relay_point_id', $pointIds)
            ->whereIn('relay_status', RelayPoint::STATUTS_REMUNERES)
            ->count();

        $charteAJour = $points->every(fn (RelayPoint $p) => $p->charteAJour());
        $releves = $points->flatMap(fn (RelayPoint $p) => collect(VersementsRelais::moisAvecColis($p))
            ->map(fn ($mois) => ['point' => $p, 'mois' => $mois] + VersementsRelais::releve($p, $mois)))
            ->take(12);

        return view('account.relay.dashboard', compact(
            'points', 'toReceive', 'toHandOver', 'toReturn', 'recentCollected', 'balance', 'collectedCount',
            'soldes', 'charteAJour', 'releves'
        ));
    }

    /** Le commerçant accepte la charte (et le mandat d'autofacturation) pour tous ses points. */
    public function accepterCharte(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->managesAnyRelay(), 403);

        $data = $request->validate([
            'accepte' => ['accepted'],
            'raison_sociale' => ['nullable', 'string', 'max:160'],
            'siret' => ['nullable', 'string', 'regex:/^[0-9 ]{14,17}$/'],
            'assujetti_tva' => ['nullable', 'boolean'],
        ], [
            'accepte.accepted' => 'Coche la case pour accepter la charte.',
            'siret.regex' => 'Le SIRET compte 14 chiffres.',
        ]);

        foreach ($user->managedRelayPoints()->get() as $point) {
            $point->forceFill(array_filter([
                'raison_sociale' => $data['raison_sociale'] ?? null,
                'siret' => isset($data['siret']) ? preg_replace('/\s+/', '', $data['siret']) : null,
            ], fn ($v) => filled($v)) + ['assujetti_tva' => (bool) ($data['assujetti_tva'] ?? false)])->save();
            $point->accepterCharte($user);
        }

        return back()->with('status', 'Merci ! La charte est acceptée : tu peux gérer tes colis.');
    }

    /** Relevé mensuel (ou autofacture) d'un point relais, en PDF. */
    public function releve(Request $request, RelayPoint $point, string $mois)
    {
        abort_unless(Auth::user()->managedRelayPoints()->whereKey($point->id)->exists(), 403);
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $mois) === 1, 404);
        $debut = Carbon::createFromFormat('Y-m-d', $mois . '-01')->startOfMonth();

        // Dans l'appli, un PDF ouvert remplace l'écran sans bouton retour :
        // on montre un aperçu dans une page Swap'Îles (pdf-appli.js).
        if ($request->query('vue') === 'html') {
            return response(VersementsRelais::html($point, $debut));
        }
        if ($request->query('vue') === 'apercu') {
            return view('account.relay.releve-apercu', [
                'titre' => 'Relevé ' . ucfirst($debut->locale('fr')->translatedFormat('F Y')),
                'contenu' => route('account.relay.releve', [$point, $mois, 'vue' => 'html']),
                'pdf' => route('account.relay.releve', [$point, $mois]),
                'fichier' => VersementsRelais::nomFichier($point, $debut),
            ]);
        }

        return response()->streamDownload(
            fn () => print(VersementsRelais::pdf($point, $debut)),
            VersementsRelais::nomFichier($point, $debut),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /** Délai de garde dépassé : le commerçant a rendu le colis au vendeur. */
    public function confirmReturn(Transaction $transaction)
    {
        $this->authorizeManager($transaction);
        if ($refus = $this->charteRequise($transaction)) {
            return $refus;
        }
        abort_unless($transaction->relay_status === 'expired', 403);

        $ventes = $transaction->transactionsDuLot()->where('relay_status', 'expired');
        foreach ($ventes as $vente) {
            $vente->update(['relay_status' => 'returned', 'relay_returned_at' => now()]);
        }

        $titre = $transaction->listing?->title ?? 'ton article';
        if ($transaction->seller_id) {
            Notification::create([
                'user_id' => $transaction->seller_id,
                'type' => 'relay_returned',
                'title' => '↩️ Colis récupéré',
                'message' => "Tu as récupéré « {$titre} » au point relais. Notre équipe s'occupe de la suite de la vente.",
                'url' => route('account.transactions.show', $transaction, absolute: false),
            ]);
        }

        AdminEvent::notify(
            'Colis rendu au vendeur (point relais)',
            'La vente #' . $transaction->id . ' (' . $titre . ') a été rendue au vendeur après le délai de garde. Remboursement de l\'acheteur à traiter.',
            route('account.transactions.show', $transaction),
            'refund_review'
        );

        return back()->with('status', 'Retour confirmé. Merci ! ' . number_format((float) config('pricing.relay_merchant_fee', 1), 0, ',', ' ') . ' € est crédité sur ton solde.');
    }

    /** Le commerçant confirme avoir RÉCEPTIONNÉ le colis déposé par le vendeur. */
    public function confirmDeposit(Transaction $transaction)
    {
        $this->authorizeManager($transaction);
        if ($refus = $this->charteRequise($transaction)) {
            return $refus;
        }
        abort_unless($transaction->relay_status === 'awaiting_deposit', 403);

        $ventes = $transaction->transactionsDuLot();
        foreach ($ventes as $vente) {
            $vente->update([
                'relay_status' => 'deposited',
                'relay_deposited_at' => now(),
                'shipping_status' => 'shipped',
                'shipped_at' => $vente->shipped_at ?: now(),
            ]);
        }

        try {
            \App\Jobs\SendTransactionStatusEmails::dispatch($ventes->first()->id, 'relay_deposited', 'commercant');
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('status', 'Colis réceptionné. L’acheteur est prévenu qu’il peut venir le retirer.');
    }

    /** Le commerçant confirme la REMISE : il vérifie le code de retrait de l'acheteur. */
    public function confirmPickup(Request $request, Transaction $transaction)
    {
        $this->authorizeManager($transaction);
        if ($refus = $this->charteRequise($transaction)) {
            return $refus;
        }
        // Après le délai de garde, l'acheteur peut encore retirer tant que le vendeur n'a pas repris le colis.
        abort_unless(in_array($transaction->relay_status, ['deposited', 'expired'], true), 403);

        $data = $request->validate([
            'pickup_code' => ['required', 'string', 'max:12'],
        ], [
            'pickup_code.required' => 'Demande à l’acheteur son code de retrait.',
        ]);

        $given = strtoupper(preg_replace('/\s+/', '', $data['pickup_code']));
        $expected = strtoupper((string) $transaction->relay_pickup_code);

        if ($expected === '' || $given !== $expected) {
            return back()->withErrors([
                'pickup_code' => 'Code de retrait incorrect. Vérifie le code présenté par l’acheteur.',
            ]);
        }

        // Un lot = un seul colis, un seul code de retrait.
        $ventes = $transaction->transactionsDuLot()->reject(fn ($v) => in_array($v->status, ['refunded', 'cancelled'], true));
        foreach ($ventes as $vente) {
            $vente->update([
                'relay_status' => 'collected',
                'auto_review_flagged_at' => $vente->relay_status === 'expired' ? null : $vente->auto_review_flagged_at,
                'relay_collected_at' => now(),
                'shipping_status' => 'received',
                'received_at' => now(),
                'delivered_at' => now(),
                'status' => 'completed',
                'completed_at' => now(),
                'wallet_status' => 'processing',
            ]);
        }

        try {
            \App\Jobs\SendTransactionStatusEmails::dispatch($transaction->transactionsDuLot()->first()->id, 'received', 'commercant');
        } catch (\Throwable $e) {
            report($e);
        }

        // Retrait confirmé -> versement au vendeur (idempotent), article par article.
        foreach ($ventes as $vente) {
            SellerPayout::release($vente);
        }

        return back()->with('status', 'Retrait confirmé. Le vendeur va être payé, et 1 € est crédité sur ton solde.');
    }

    /** Pas d'action sur un colis tant que la charte n'est pas acceptée. */
    private function charteRequise(Transaction $transaction)
    {
        $point = RelayPoint::find($transaction->relay_point_id);

        return $point && ! $point->charteAJour()
            ? back()->withErrors(['charte' => 'Accepte d’abord la charte des points relais (en haut de la page).'])
            : null;
    }

    /** L'utilisateur courant gère-t-il le point relais de cette transaction ? */
    private function authorizeManager(Transaction $transaction): void
    {
        abort_unless(
            $transaction->relay_point_id
                && Auth::user()->managedRelayPoints()->whereKey($transaction->relay_point_id)->exists(),
            403
        );
    }
}
