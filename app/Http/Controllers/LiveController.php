<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\LiveMessage;
use App\Models\LiveSession;
use App\Support\LiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Vente en direct : programmer, animer, regarder, discuter, acheter. */
class LiveController extends Controller
{
    public function index(Request $request)
    {
        return view('live.index', [
            'enDirect' => LiveSession::with(['vendeur', 'articles.images'])->where('statut', 'en_direct')->latest('demarre_at')->get(),
            'programmes' => LiveSession::with(['vendeur', 'articles.images'])->where('statut', 'programme')
                ->where(fn ($q) => $q->whereNull('debut_prevu_at')->orWhere('debut_prevu_at', '>=', now()->subHours(6)))
                ->orderBy('debut_prevu_at')->get(),
            'mesLives' => $request->user() ? LiveSession::where('user_id', $request->user()->id)->latest()->limit(10)->get() : collect(),
            'replays' => LiveSession::with(['vendeur', 'articles.images'])->where('statut', 'termine')
                ->whereNotNull('replay_publie_at')->whereNotNull('replay_chemin')
                ->latest('replay_publie_at')->limit(12)->get(),
            'mesRappels' => $request->user() ? \Illuminate\Support\Facades\DB::table('live_rappels')->where('user_id', $request->user()->id)->pluck('live_session_id')->all() : [],
        ]);
    }

    public function create(Request $request)
    {
        return view('live.create', [
            'articles' => Listing::where('user_id', $request->user()->id)->where('status', 'published')
                ->with('images')->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'titre' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'debut_prevu_at' => ['nullable', 'date'],
            'articles' => ['required', 'array', 'min:1', 'max:50'],
            'articles.*' => ['integer', Rule::exists('listings', 'id')->where('user_id', $request->user()->id)->where('status', 'published')],
        ], [
            'articles.required' => 'Choisissez au moins un article à présenter.',
        ]);

        $live = LiveSession::create([
            'user_id' => $request->user()->id,
            'titre' => $data['titre'],
            'description' => $data['description'] ?? null,
            'debut_prevu_at' => $data['debut_prevu_at'] ?? null,
        ]);

        foreach (array_values($data['articles']) as $i => $id) {
            $live->articles()->attach($id, ['ordre' => $i]);
        }

        return redirect()->route('live.show', $live)->with('status', 'Live créé. Appuyez sur « Démarrer » quand vous êtes prêt.');
    }

    public function show(Request $request, LiveSession $live)
    {
        $live->load(['vendeur', 'articles.images']);

        // Pour proposer « Revenir au live » si on va voir ailleurs (paiement…).
        if ($live->enDirect()) {
            $request->session()->put('live_retour', $live->id);
            $this->arriveeParInvitation($request, $live);
        }

        return view('live.show', [
            'live' => $live,
            'estVendeur' => $live->estVendeur($request->user()),
            'inscrit' => $request->user() ? $live->abonnes()->whereKey($request->user()->id)->exists() : false,
            'nbInscrits' => $live->abonnes()->count(),
        ]);
    }

    /**
     * Lien partagé (« ?de=12 ») : on salue l'arrivée dans le chat, une seule
     * fois par visiteur. Ça montre au vendeur et au public que le partage
     * marche, et donne envie de partager à son tour.
     */
    private function arriveeParInvitation(Request $request, LiveSession $live): void
    {
        $de = (int) $request->query('de');
        $cle = 'live_invite_' . $live->id;

        if ($de <= 0 || $request->session()->has($cle) || $request->user()?->id === $de) {
            return;
        }

        $parrain = \App\Models\User::find($de);
        if (! $parrain) {
            return;
        }

        $request->session()->put($cle, true);
        $prenom = \Illuminate\Support\Str::of($parrain->name)->trim()->explode(' ')->first();
        LiveService::message($live, null, '👋 Un invité de ' . $prenom . ' vient de rejoindre le live', 'systeme');
    }

    // --- Vendeur ----------------------------------------------------------------

    private function vendeurSeulement(Request $request, LiveSession $live): void
    {
        abort_unless($live->estVendeur($request->user()), 403);
    }

    public function demarrer(Request $request, LiveSession $live)
    {
        $this->vendeurSeulement($request, $live);
        abort_if($live->statut === 'termine', 403);

        $premiereFois = $live->statut === 'programme';
        $live->update(['statut' => 'en_direct', 'demarre_at' => $live->demarre_at ?? now()]);
        LiveService::message($live, null, '🔴 Le live commence !', 'systeme');

        if ($premiereFois) {
            \App\Support\LiveNotifications::demarrage($live);
        }

        return back();
    }

    public function terminer(Request $request, LiveSession $live)
    {
        $this->vendeurSeulement($request, $live);

        $live->update(['statut' => 'termine', 'termine_at' => now(), 'article_epingle_id' => null]);
        LiveService::message($live, null, 'Le live est terminé. Merci à tous ! 🌴', 'systeme');
        \App\Support\LiveEnregistrement::arreter($live);

        return redirect()->route('live.show', $live)->with('status', 'Live terminé. Merci !');
    }

    /** Le vendeur montre ou cache un article dans la liste des spectateurs. */
    public function visibilite(Request $request, LiveSession $live, Listing $listing)
    {
        $this->vendeurSeulement($request, $live);
        $article = $live->articles()->whereKey($listing->id)->first();
        abort_unless($article, 404);

        $visible = $request->boolean('visible');
        $live->articles()->updateExistingPivot($listing->id, ['visible' => $visible]);

        if ($visible && ! $article->pivot->visible && $live->enDirect()) {
            LiveService::message($live, null, '🛍️ Nouvel article : ' . $listing->title . ' — ' . \App\Support\TableauDeBord::euros((float) $listing->price), 'epingle');
        }
        if (! $visible && (int) $live->article_epingle_id === (int) $listing->id) {
            $live->update(['article_epingle_id' => null]);
        }

        return response()->json(['visible' => $visible]);
    }

    /** Lancement de l'enregistrement, quand la caméra du vendeur tourne. */
    public function enregistrer(Request $request, LiveSession $live)
    {
        $this->vendeurSeulement($request, $live);

        return response()->json(['enregistre' => \App\Support\LiveEnregistrement::demarrer($live)]);
    }

    /** Le vendeur publie (ou retire) le replay sur son profil. */
    public function publierReplay(Request $request, LiveSession $live)
    {
        $this->vendeurSeulement($request, $live);
        abort_unless($live->statut === 'termine' && $live->replayUrl(), 404);

        $publier = $request->boolean('publier');
        $live->update(['replay_publie_at' => $publier ? now() : null]);

        return back()->with('status', $publier ? '🎬 Replay publié sur votre profil.' : 'Replay retiré de votre profil.');
    }

    /** « Me prévenir » : inscription (ou désinscription) au début du live. */
    public function rappel(Request $request, LiveSession $live)
    {
        abort_if($live->statut === 'termine', 404);
        abort_if($live->estVendeur($request->user()), 403);

        $deja = $live->abonnes()->whereKey($request->user()->id)->exists();
        $deja ? $live->abonnes()->detach($request->user()->id) : $live->abonnes()->attach($request->user()->id);

        if ($request->expectsJson()) {
            return response()->json(['inscrit' => ! $deja, 'inscrits' => $live->abonnes()->count()]);
        }

        return back()->with('status', $deja ? 'Vous ne serez plus prévenu de ce live.' : '🔔 C’est noté ! On vous prévient 15 min avant et au début du live.');
    }

    public function epingler(Request $request, LiveSession $live)
    {
        $this->vendeurSeulement($request, $live);
        $data = $request->validate(['listing_id' => ['required', 'integer']]);
        abort_unless($live->articles()->whereKey($data['listing_id'])->exists(), 422);

        $live->update(['article_epingle_id' => $data['listing_id']]);
        // Mis en avant = forcément visible dans la liste.
        $live->articles()->updateExistingPivot($data['listing_id'], ['visible' => true]);
        $article = Listing::find($data['listing_id']);
        LiveService::message($live, null, '📌 ' . $article->title . ' — ' . \App\Support\TableauDeBord::euros((float) $article->price), 'epingle');

        return response()->noContent();
    }

    public function masquerMessage(Request $request, LiveSession $live, LiveMessage $message)
    {
        $this->vendeurSeulement($request, $live);
        abort_unless((int) $message->live_session_id === (int) $live->id, 404);
        $message->update(['masque' => true]);

        return response()->noContent();
    }

    public function exclure(Request $request, LiveSession $live, LiveMessage $message)
    {
        $this->vendeurSeulement($request, $live);
        abort_unless((int) $message->live_session_id === (int) $live->id && $message->user_id, 404);

        DB::table('live_spectateurs')->where('live_session_id', $live->id)->where('user_id', $message->user_id)->update(['exclu' => true]);
        DB::table('live_spectateurs')->updateOrInsert(
            ['live_session_id' => $live->id, 'cle' => 'membre-' . $message->user_id],
            ['user_id' => $message->user_id, 'exclu' => true, 'last_seen_at' => now()]
        );
        LiveMessage::where('live_session_id', $live->id)->where('user_id', $message->user_id)->update(['masque' => true]);

        return response()->noContent();
    }

    // --- Spectateurs ------------------------------------------------------------

    private function cle(Request $request): string
    {
        return $request->user() ? 'membre-' . $request->user()->id : 'session-' . hash('sha256', $request->session()->getId());
    }

    public function etat(Request $request, LiveSession $live)
    {
        if (! LiveService::presence($live, $this->cle($request), $request->user())) {
            return response()->json(['exclu' => true]);
        }

        return response()->json(LiveService::etat($live, $request->user(), (int) $request->query('apres', 0)));
    }

    /** Accès à la vidéo : le vendeur filme, les autres regardent. */
    public function jeton(Request $request, LiveSession $live)
    {
        if (! \App\Support\LiveKit::configure()) {
            return response()->json(['erreur' => 'video_non_configuree'], 503);
        }
        if (! $live->enDirect()) {
            return response()->json(['erreur' => 'pas_en_direct'], 409);
        }
        if (LiveService::estExclu($live, $request->user(), $this->cle($request))) {
            return response()->json(['erreur' => 'exclu'], 403);
        }

        $publier = $live->estVendeur($request->user());

        return response()->json([
            'url' => config('live.livekit.url'),
            'jeton' => \App\Support\LiveKit::jeton($live, $request->user(), $publier),
            'publier' => $publier,
        ]);
    }

    public function envoyerMessage(Request $request, LiveSession $live)
    {
        abort_unless($request->user(), 401);
        abort_if($live->statut === 'termine', 403);
        abort_if(LiveService::estExclu($live, $request->user(), $this->cle($request)), 403);

        $data = $request->validate(['texte' => ['required', 'string', 'max:300']]);
        LiveService::message($live, $request->user(), $data['texte']);

        return response()->noContent();
    }

    public function coeur(LiveSession $live)
    {
        $live->increment('coeurs');

        return response()->noContent();
    }

    public function acheter(Request $request, LiveSession $live, Listing $listing)
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }
        // Seulement les articles que le vendeur a déjà montrés.
        abort_unless(
            $live->articlesVisibles()->whereKey($listing->id)->exists() || (int) $live->article_epingle_id === (int) $listing->id,
            404
        );

        if (! LiveService::reserver($live, $listing, $request->user())) {
            return back()->with('status', $listing->status === 'sold'
                ? 'Trop tard, cet article vient d’être vendu 😔'
                : 'Quelqu’un est déjà en train de l’acheter. S’il ne finalise pas, il sera de nouveau disponible dans quelques minutes.');
        }

        LiveService::message($live, null, '🔥 ' . $request->user()->name . ' est en train d’acheter « ' . $listing->title . ' »', 'vente');
        $request->session()->put('live_retour', $live->id);

        return redirect()->route('checkout.show', $listing);
    }
}
