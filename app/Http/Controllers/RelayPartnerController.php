<?php

namespace App\Http\Controllers;

use App\Models\RelayPoint;
use App\Models\Transaction;

/**
 * Page vitrine « Devenir point relais partenaire » (accessible via le footer).
 * Présente les avantages commerçant. Les statistiques ne sont affichées que
 * lorsqu'elles sont significatives (sinon on ne montre que la proposition de
 * valeur, pour éviter des chiffres vides au lancement du pilote).
 */
class RelayPartnerController extends Controller
{
    /** Seuil en-dessous duquel on masque les statistiques chiffrées. */
    private const STATS_MIN_PARCELS = 20;

    public function show()
    {
        $merchantFee = (float) config('pricing.relay_merchant_fee', 1.00);

        $activeRelays = RelayPoint::query()->active()->count();
        $parcelsDelivered = Transaction::query()->where('relay_status', 'collected')->count();
        $merchantEarned = (float) Transaction::query()->where('relay_status', 'collected')->sum('relay_merchant_fee');

        return view('relay.partner', [
            'merchantFee' => $merchantFee,
            'activeRelays' => $activeRelays,
            'parcelsDelivered' => $parcelsDelivered,
            'merchantEarned' => $merchantEarned,
            'showStats' => $parcelsDelivered >= self::STATS_MIN_PARCELS,
        ]);
    }
}
