<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\ColissimoLabelService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ColissimoLabelController extends Controller
{
    public function generate(Transaction $transaction, ColissimoLabelService $service)
    {
        $transaction->loadMissing(['listing', 'buyer', 'seller']);

        $sellerId = $transaction->seller_id ?: $transaction->listing?->user_id;

        abort_unless((int) Auth::id() === (int) $sellerId, 403);

        if (! in_array($transaction->status, ['paid', 'completed'], true)) {
            return back()->withErrors([
                'colissimo' => "Le bordereau Colissimo ne peut être généré que lorsque le paiement est confirmé.",
            ]);
        }

        if (! $this->isColissimoTransaction($transaction)) {
            return back()->withErrors([
                'colissimo' => "Cette transaction n’est pas une livraison Colissimo.",
            ]);
        }

        if ($transaction->colissimo_label_path && Storage::disk('local')->exists($transaction->colissimo_label_path)) {
            return back()->with('status', '✅ Bordereau Colissimo déjà généré. Vous pouvez le télécharger.');
        }

        $this->normalizeColissimoTransaction($transaction);

        try {
            $result = $service->generateForTransaction($transaction);

            $fresh = $transaction->fresh();

            return back()->with('status', '✅ Bordereau Colissimo généré avec succès. Vous pouvez maintenant télécharger l’étiquette.');
        } catch (\Throwable $e) {
            Log::error('Erreur génération bordereau Colissimo', [
                'transaction_id' => $transaction->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return back()->withErrors([
                'colissimo' => "Impossible de générer le bordereau Colissimo : " . $e->getMessage(),
            ]);
        }
    }

    public function download(Transaction $transaction)
    {
        $transaction->loadMissing(['listing']);

        $sellerId = $transaction->seller_id ?: $transaction->listing?->user_id;

        abort_unless(
            (int) Auth::id() === (int) $sellerId || (int) Auth::id() === (int) $transaction->buyer_id,
            403
        );

        if (! $transaction->colissimo_label_path || ! Storage::disk('local')->exists($transaction->colissimo_label_path)) {
            return back()->withErrors([
                'colissimo' => "Le bordereau Colissimo n’a pas encore été généré.",
            ]);
        }

        return Storage::disk('local')->download(
            $transaction->colissimo_label_path,
            'bordereau-colissimo-' . $transaction->id . '.pdf'
        );
    }

    private function isColissimoTransaction(Transaction $transaction): bool
    {
        return $transaction->delivery_method === 'colissimo'
            || $transaction->shipping_method === 'colissimo'
            || $transaction->colissimo_delivery_type === 'home';
    }

    private function normalizeColissimoTransaction(Transaction $transaction): void
    {
        $changed = false;

        if (! $transaction->colissimo_delivery_type) {
            $transaction->colissimo_delivery_type = 'home';
            $changed = true;
        }

        if (! $transaction->delivery_method) {
            $transaction->delivery_method = 'colissimo';
            $changed = true;
        }

        if (! $transaction->shipping_status) {
            $transaction->shipping_status = 'pending';
            $changed = true;
        }

        if ($changed) {
            $transaction->save();
        }

        $postalCode = (string) $transaction->shipping_postal_code;

        if (Str::startsWith($postalCode, '974')) {
            $transaction->shipping_country = 'RE';
        }
    }
}
