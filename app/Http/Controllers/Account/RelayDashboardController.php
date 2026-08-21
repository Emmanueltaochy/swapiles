<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Support\SellerPayout;
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
        $recentCollected = (clone $base)->where('relay_status', 'collected')->limit(20)->get();

        $balance = (float) Transaction::whereIn('relay_point_id', $pointIds)
            ->where('relay_status', 'collected')
            ->sum('relay_merchant_fee');
        $collectedCount = (int) Transaction::whereIn('relay_point_id', $pointIds)
            ->where('relay_status', 'collected')
            ->count();

        return view('account.relay.dashboard', compact(
            'points', 'toReceive', 'toHandOver', 'recentCollected', 'balance', 'collectedCount'
        ));
    }

    /** Le commerçant confirme avoir RÉCEPTIONNÉ le colis déposé par le vendeur. */
    public function confirmDeposit(Transaction $transaction)
    {
        $this->authorizeManager($transaction);
        abort_unless($transaction->relay_status === 'awaiting_deposit', 403);

        $transaction->update([
            'relay_status' => 'deposited',
            'relay_deposited_at' => now(),
            'shipping_status' => 'shipped',
            'shipped_at' => $transaction->shipped_at ?: now(),
        ]);

        try {
            \App\Jobs\SendTransactionStatusEmails::dispatch($transaction->id, 'relay_deposited');
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('status', 'Colis réceptionné. L’acheteur est prévenu qu’il peut venir le retirer.');
    }

    /** Le commerçant confirme la REMISE : il vérifie le code de retrait de l'acheteur. */
    public function confirmPickup(Request $request, Transaction $transaction)
    {
        $this->authorizeManager($transaction);
        abort_unless($transaction->relay_status === 'deposited', 403);

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

        $transaction->update([
            'relay_status' => 'collected',
            'relay_collected_at' => now(),
            'shipping_status' => 'received',
            'received_at' => now(),
            'delivered_at' => now(),
            'status' => 'completed',
            'completed_at' => now(),
            'wallet_status' => 'processing',
        ]);

        try {
            \App\Jobs\SendTransactionStatusEmails::dispatch($transaction->id, 'received');
        } catch (\Throwable $e) {
            report($e);
        }

        // Retrait confirmé -> versement au vendeur (idempotent).
        SellerPayout::release($transaction);

        return back()->with('status', 'Retrait confirmé. Le vendeur va être payé, et 1 € est crédité sur ton solde.');
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
