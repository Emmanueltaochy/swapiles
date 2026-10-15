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
            'enDirect' => LiveSession::with('vendeur')->where('statut', 'en_direct')->latest('demarre_at')->get(),
            'programmes' => LiveSession::with('vendeur')->where('statut', 'programme')
                ->where(fn ($q) => $q->whereNull('debut_prevu_at')->orWhere('debut_prevu_at', '>=', now()->subHours(6)))
                ->orderBy('debut_prevu_at')->get(),
            'mesLives' => $request->user() ? LiveSession::where('user_id', $request->user()->id)->latest()->limit(10)->get() : collect(),
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

        return view('live.show', [
            'live' => $live,
            'estVendeur' => $live->estVendeur($request->user()),
        ]);
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

        $live->update(['statut' => 'en_direct', 'demarre_at' => $live->demarre_at ?? now()]);
        LiveService::message($live, null, '🔴 Le live commence !', 'systeme');

        return back();
    }

    public function terminer(Request $request, LiveSession $live)
    {
        $this->vendeurSeulement($request, $live);

        $live->update(['statut' => 'termine', 'termine_at' => now(), 'article_epingle_id' => null]);
        LiveService::message($live, null, 'Le live est terminé. Merci à tous ! 🌴', 'systeme');

        return redirect()->route('live.show', $live)->with('status', 'Live terminé.');
    }

    public function epingler(Request $request, LiveSession $live)
    {
        $this->vendeurSeulement($request, $live);
        $data = $request->validate(['listing_id' => ['required', 'integer']]);
        abort_unless($live->articles()->whereKey($data['listing_id'])->exists(), 422);

        $live->update(['article_epingle_id' => $data['listing_id']]);
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
        abort_unless($live->articles()->whereKey($listing->id)->exists(), 404);

        if (! LiveService::reserver($live, $listing, $request->user())) {
            return back()->with('status', $listing->status === 'sold'
                ? 'Trop tard, cet article vient d’être vendu 😔'
                : 'Quelqu’un est déjà en train de l’acheter. S’il ne finalise pas, il sera de nouveau disponible dans quelques minutes.');
        }

        LiveService::message($live, null, '🔥 ' . $request->user()->name . ' est en train d’acheter « ' . $listing->title . ' »', 'vente');

        return redirect()->route('checkout.show', $listing);
    }
}
