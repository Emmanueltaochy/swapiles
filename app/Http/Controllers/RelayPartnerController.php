<?php

namespace App\Http\Controllers;

use App\Models\RelayPoint;
use App\Models\Transaction;
use App\Support\AdminEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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

    /** Réception du formulaire « devenir partenaire » : e-mail à l'équipe. */
    public function submit(Request $request)
    {
        // Honeypot anti-spam : un bot remplit ce champ caché, un humain non.
        if (filled($request->input('website'))) {
            return back()->with('relay_contact_status', 'Merci ! Nous revenons vers vous très vite.');
        }

        $data = $request->validate([
            'business' => ['required', 'string', 'max:160'],
            'name' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:191'],
            'hours' => ['nullable', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:2000'],
        ], [
            'business.required' => 'Indiquez le nom de votre commerce.',
            'name.required' => 'Indiquez votre nom.',
            'city.required' => 'Indiquez la ville de votre commerce.',
            'phone.required' => 'Indiquez un numéro de téléphone pour vous recontacter.',
            'email.required' => 'Indiquez un e-mail de contact.',
            'email.email' => 'Cet e-mail ne semble pas valide.',
        ]);

        $body = "Nouvelle demande de point relais partenaire :\n\n"
            . "Commerce : {$data['business']}\n"
            . "Contact : {$data['name']}\n"
            . "Ville : {$data['city']}\n"
            . "Téléphone : {$data['phone']}\n"
            . "E-mail : {$data['email']}\n"
            . 'Horaires : ' . ($data['hours'] ?? '—') . "\n\n"
            . 'Message : ' . ($data['message'] ?? '—') . "\n";

        try {
            Mail::raw($body, function ($mail) use ($data) {
                $mail->from('contact@swapiles.com', "Swap'Îles")
                    ->to('contact@swapiles.com')
                    ->replyTo($data['email'], $data['name'])
                    ->subject('Point relais partenaire — ' . $data['business'] . ' (' . $data['city'] . ')');
            });
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->withErrors(['relay_contact' => "L'envoi a échoué. Réessaie ou écris-nous directement à contact@swapiles.com."]);
        }

        try {
            AdminEvent::notify(
                'Nouvelle demande point relais',
                $data['business'] . ' à ' . $data['city'] . ' — ' . $data['name'] . ' (' . $data['phone'] . ')'
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('relay_contact_status', 'Merci ! Votre demande est bien envoyée, nous vous recontactons très vite. 🌴');
    }
}
