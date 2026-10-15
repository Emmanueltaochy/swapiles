<?php

namespace App\Http\Controllers;

use App\Models\Parrainage;
use App\Models\PortefeuilleMouvement;
use App\Support\CodeParrainage;
use App\Support\ParrainageService;
use App\Support\Portefeuille;
use Illuminate\Http\Request;

/** Parrainage : invitation, « Inviter des amis », filleuls, portefeuille, conditions. */
class ParrainageController extends Controller
{
    private function exigerAcces(Request $request): void
    {
        abort_unless(ParrainageService::actifPour($request->user()), 404);
    }

    /** Lien d'invitation https://swapiles.com/invite/{CODE}. */
    public function invite(Request $request, string $code)
    {
        $parrain = ParrainageService::parrainDuCode($code);
        abort_unless($parrain && ParrainageService::actifPour($parrain) && ! $parrain->is_banned, 404);

        $code = $parrain->code_parrainage;
        $moi = $request->user();

        if (! $moi) {
            ParrainageService::capter($request, $code, 'lien');
            ParrainageService::noterOuverture($request, $parrain, $code);
        }

        $plateforme = ParrainageService::plateforme($request->userAgent());

        return view('parrainage.invite', [
            'parrain' => $parrain,
            'code' => $code,
            'prenom' => ParrainageService::prenom($parrain),
            'moi' => $moi,
            'peutSaisir' => $moi && $moi->id !== $parrain->id && ParrainageService::peutSaisir($moi),
            'plateforme' => $plateforme,
            'store' => match ($plateforme) {
                // Android : le code voyage dans le lien Play Store (Install Referrer, lot 2).
                'android' => 'https://play.google.com/store/apps/details?id=' . config('deeplinks.android.package', 'com.swapiles.app')
                    . '&referrer=' . rawurlencode('utm_source=parrainage&utm_medium=invite&utm_content=' . $code),
                'ios' => config('deeplinks.stores.ios'),
                default => null,
            },
            'redirectionStore' => (bool) config('referral.redirection_store') && ! $moi && $plateforme !== 'web',
        ]);
    }

    /** Compte → Inviter des amis + Mes filleuls. */
    public function index(Request $request)
    {
        $this->exigerAcces($request);
        $moi = $request->user();

        $filleuls = Parrainage::with('filleul:id,name')->where('parrain_id', $moi->id)->latest()->get();
        $valides = $filleuls->where('statut', 'valide')->count();

        return view('parrainage.index', [
            'moi' => $moi,
            'lien' => route('parrainage.invite', $moi->code_parrainage),
            'filleuls' => $filleuls,
            'valides' => $valides,
            'palier' => (int) config('referral.palier_ambassadeur', 3),
            'totalGagne' => (int) $filleuls->where('statut', 'valide')->sum('montant_centimes'),
            'solde' => Portefeuille::solde($moi),
            'recompense' => (float) config('referral.reward_amount', 0),
            'peutSaisir' => ParrainageService::peutSaisir($moi),
            'protectionOfferte' => ParrainageService::protectionOfferte($moi),
        ]);
    }

    /** Saisie d'un code dans les 7 jours après l'inscription. */
    public function saisir(Request $request)
    {
        $this->exigerAcces($request);
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);

        $erreur = ParrainageService::attacher($request->user(), $data['code'], 'saisie');

        return $erreur
            ? back()->withErrors(['code' => $erreur])->withInput()
            : back()->with('status', '🎉 C’est noté ! Tes frais de protection sont offerts sur ton 1er achat.');
    }

    /** Personnalisation du code, une seule fois. */
    public function personnaliser(Request $request)
    {
        $this->exigerAcces($request);
        $moi = $request->user();
        abort_if($moi->code_personnalise_at, 403);

        $data = $request->validate(['nouveau_code' => ['required', 'string', 'max:20']]);
        $nouveau = CodeParrainage::normaliser($data['nouveau_code']);

        if ($erreur = CodeParrainage::erreurPersonnalisation($nouveau, $moi->id)) {
            return back()->withErrors(['nouveau_code' => $erreur])->withInput();
        }

        $ancien = $moi->code_parrainage;
        $moi->forceFill([
            'code_parrainage' => $nouveau,
            'code_parrainage_ancien' => $ancien,
            'code_personnalise_at' => now(),
        ])->save();
        ParrainageService::journal('referral_code_customized', $moi->id, donnees: ['ancien' => $ancien, 'nouveau' => $nouveau]);

        return back()->with('status', '✨ Ton nouveau code ' . $nouveau . ' est prêt ! Tes anciens liens marchent toujours.');
    }

    /** Partage du lien (mesure du canal). */
    public function partage(Request $request)
    {
        $this->exigerAcces($request);
        $canal = $request->validate(['canal' => ['required', 'in:whatsapp,instagram,sms,autre,copie']])['canal'];
        ParrainageService::journal('referral_link_shared', $request->user()->id, canal: $canal);

        return response()->noContent();
    }

    public function portefeuille(Request $request)
    {
        $this->exigerAcces($request);
        $moi = $request->user();

        return view('parrainage.portefeuille', [
            'solde' => Portefeuille::solde($moi),
            'lots' => PortefeuilleMouvement::where('user_id', $moi->id)->whereIn('type', PortefeuilleMouvement::LOTS)
                ->where('restant_centimes', '>', 0)->where('expire_at', '>', now())->orderBy('expire_at')->get(),
            'mouvements' => PortefeuilleMouvement::with('transaction.listing:id,title')->where('user_id', $moi->id)
                ->latest('id')->limit(100)->get(),
        ]);
    }

    public function conditions(Request $request)
    {
        abort_unless(config('referral.enabled') || ParrainageService::actifPour($request->user()), 404);

        return view('parrainage.conditions');
    }
}
