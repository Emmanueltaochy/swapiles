<?php

namespace App\Http\Controllers;

use App\Models\RelayPoint;
use App\Models\RelayPointRequest;
use App\Support\AdminEvent;
use App\Support\TerritoireContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Page publique « Nos points relais » : les commerces partenaires, île par
 * île, et un formulaire pour demander un point relais plus près de chez soi.
 */
class RelayPointsController extends Controller
{
    public function index(Request $request)
    {
        $ile = (string) $request->query('ile', '');
        if (! in_array($ile, TerritoireContext::LABELS, true)) {
            $ile = TerritoireContext::resolve($request);
        }

        $parIle = RelayPoint::query()->active()
            ->selectRaw('territoire, COUNT(*) as total')->groupBy('territoire')
            ->pluck('total', 'territoire');

        $points = RelayPoint::query()->active()
            ->where('territoire', $ile)
            // Les enseignes partenaires d'abord, dans chaque ville.
            ->orderBy('city')->orderByRaw('partenaire_enseigne IS NULL')->orderBy('name')
            ->get();

        return view('relay.points', [
            'ile' => $ile,
            'iles' => TerritoireContext::LABELS,
            'parIle' => $parIle,
            'points' => $points,
            'villes' => $points->groupBy(fn ($p) => $p->city ?: 'Autres'),
        ]);
    }

    public function demander(Request $request)
    {
        // Honeypot anti-spam : un bot remplit ce champ caché, un humain non.
        if (filled($request->input('website'))) {
            return redirect()->to(route('relay.points') . '#demande')->with('relay_request_status', 'Merci ! Votre demande est bien enregistrée.');
        }

        $data = $request->validate([
            'territoire' => ['required', Rule::in(TerritoireContext::LABELS)],
            'city' => ['required', 'string', 'max:120'],
            'business_name' => ['nullable', 'string', 'max:160'],
            'business_address' => ['nullable', 'string', 'max:255'],
            'already_relay' => ['required', Rule::in(array_keys(RelayPointRequest::DEJA_RELAIS))],
            'networks' => ['nullable', 'array'],
            'networks.*' => [Rule::in(array_keys(RelayPointRequest::RESEAUX))],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:2000'],
        ], [
            'city.required' => 'Indiquez votre ville ou votre quartier.',
            'name.required' => 'Indiquez votre prénom.',
            'email.required' => 'Indiquez votre e-mail pour qu’on vous prévienne.',
            'email.email' => 'Cet e-mail ne semble pas valide.',
            'already_relay.required' => 'Dites-nous si ce commerce est déjà point relais.',
        ]);

        // Réseaux seulement si le commerce est déjà point relais.
        $data['networks'] = $data['already_relay'] === 'oui' ? array_values($data['networks'] ?? []) : null;

        $demande = RelayPointRequest::create($data + ['user_id' => optional($request->user())->id]);

        $resume = $demande->city . ' (' . $demande->territoire . ')'
            . ($demande->business_name ? ' — commerce proposé : ' . $demande->business_name : '');

        try {
            Mail::raw(
                $demande->corpsEmail(),
                function ($mail) use ($demande, $resume) {
                    $mail->from('contact@swapiles.com', "Swap'Îles")
                        ->to('contact@swapiles.com')
                        ->replyTo($demande->email, $demande->name)
                        ->subject('Demande de point relais — ' . $resume);
                }
            );
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            AdminEvent::notify('Demande de point relais', $resume . ' — ' . $demande->name, null, 'relay_point_request');
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->to(route('relay.points', ['ile' => $demande->territoire]) . '#demande')
            ->with('relay_request_status', 'Merci ' . $demande->name . ' ! Votre demande est bien enregistrée. Nous contactons les commerces de ' . $demande->city . ' et vous prévenons dès qu’un point relais ouvre. 🌴');
    }
}
