<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\LiveMessage;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Règles de la vente en direct : présence des spectateurs, chat, article
 * épinglé et réservation d'un article pendant le paiement.
 */
class LiveService
{
    /** Signe de vie d'un spectateur ; renvoie false s'il a été exclu du live. */
    public static function presence(LiveSession $live, string $cle, ?User $user): bool
    {
        $ligne = DB::table('live_spectateurs')->where('live_session_id', $live->id)->where('cle', $cle)->first();

        if ($ligne && $ligne->exclu) {
            return false;
        }

        DB::table('live_spectateurs')->updateOrInsert(
            ['live_session_id' => $live->id, 'cle' => $cle],
            ['user_id' => $user?->id, 'last_seen_at' => now()]
        );

        if ($live->enDirect()) {
            $n = self::spectateurs($live);
            if ($n > $live->spectateurs_max) {
                $live->forceFill(['spectateurs_max' => $n])->saveQuietly();
            }
        }

        return true;
    }

    public static function spectateurs(LiveSession $live): int
    {
        return DB::table('live_spectateurs')
            ->where('live_session_id', $live->id)
            ->where('exclu', false)
            ->where('last_seen_at', '>=', now()->subSeconds((int) config('live.presence_secondes', 30)))
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $live->user_id))
            ->count();
    }

    public static function estExclu(LiveSession $live, ?User $user, string $cle): bool
    {
        return DB::table('live_spectateurs')->where('live_session_id', $live->id)->where('exclu', true)
            ->where(fn ($q) => $q->where('cle', $cle)->when($user, fn ($q) => $q->orWhere('user_id', $user->id)))
            ->exists();
    }

    public static function message(LiveSession $live, ?User $user, string $texte, string $type = 'message', ?int $listingId = null): LiveMessage
    {
        return LiveMessage::create([
            'live_session_id' => $live->id,
            'user_id' => $user?->id,
            // Article concerné (mise en avant, nouvel article, achat) : rejoué dans le replay.
            'listing_id' => $listingId,
            'body' => mb_substr(trim($texte), 0, 300),
            'type' => $type,
            'created_at' => now(),
        ]);
    }

    // --- Réservation pendant le paiement ------------------------------------------

    /** Réservation encore valable sur cet article (null si libre). */
    public static function reservationActive(Listing $article): ?object
    {
        return DB::table('live_reservations')->where('listing_id', $article->id)
            ->where('expire_at', '>', now())->orderByDesc('id')->first();
    }

    /** L'article est-il bloqué pour CE membre (réservé par quelqu'un d'autre) ? */
    public static function reservePourUnAutre(Listing $article, ?User $user): bool
    {
        $r = self::reservationActive($article);

        return $r !== null && (! $user || (int) $r->user_id !== (int) $user->id);
    }

    /**
     * Réserve l'article pour l'acheteur. Renvoie false s'il est déjà vendu ou
     * réservé par quelqu'un d'autre.
     */
    public static function reserver(LiveSession $live, Listing $article, User $acheteur): bool
    {
        if ($article->status !== 'published' || (int) $article->user_id === (int) $acheteur->id) {
            return false;
        }

        return DB::transaction(function () use ($live, $article, $acheteur) {
            // Verrou : deux spectateurs qui appuient en même temps, un seul gagne.
            Listing::whereKey($article->id)->lockForUpdate()->first();

            if (self::reservePourUnAutre($article, $acheteur)) {
                return false;
            }

            DB::table('live_reservations')->insert([
                'listing_id' => $article->id,
                'user_id' => $acheteur->id,
                'live_session_id' => $live->id,
                'expire_at' => now()->addMinutes((int) config('live.reservation_minutes', 5)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    // --- Replay interactif ------------------------------------------------------------

    /**
     * Ce que le replay rejoue, placé sur la ligne de temps de la vidéo (en
     * secondes depuis le début de l'enregistrement) : messages du chat,
     * articles mis en avant, achats, cœurs.
     *
     * @return array{messages:array,coeurs:array,articles:array}
     */
    public static function donneesReplay(LiveSession $live): array
    {
        $debut = $live->enregistrement_debut_at ?? $live->demarre_at ?? $live->created_at;
        // L'enregistreur met quelques secondes à démarrer après la demande.
        $decalage = (int) config('live.replay_decalage_secondes', 3);
        $t = fn ($date) => max(0, \Illuminate\Support\Carbon::parse($date)->getTimestamp() - $debut->getTimestamp() - $decalage);

        $messages = LiveMessage::query()->with('user:id,name')
            ->where('live_session_id', $live->id)->where('masque', false)
            ->orderBy('id')->limit(3000)->get()
            ->map(fn (LiveMessage $m) => [
                't' => $t($m->created_at),
                'auteur' => $m->type === 'message' ? ($m->user->name ?? 'Membre') : null,
                'vendeur' => (int) $m->user_id === (int) $live->user_id,
                'texte' => $m->body,
                'type' => $m->type,
                'article' => $m->listing_id,
            ])->values()->all();

        $coeurs = DB::table('live_coeurs')->where('live_session_id', $live->id)->orderBy('instant')
            ->get(['instant', 'n'])->map(fn ($c) => ['t' => $t($c->instant), 'n' => (int) $c->n])->values()->all();

        $articles = $live->articles()->with('images')->get()->mapWithKeys(fn (Listing $a) => [$a->id => [
            'titre' => $a->title,
            'prix' => TableauDeBord::euros((float) $a->price),
            'photo' => $a->images->sortBy('order')->first()?->url,
            'vendu' => $a->status === 'sold',
            'lien' => route('listings.show', $a, absolute: false),
        ]])->all();

        return ['messages' => $messages, 'coeurs' => $coeurs, 'articles' => $articles];
    }

    // --- État envoyé à la page (toutes les 2-3 secondes) ---------------------------

    /** @return array<string,mixed> */
    public static function etat(LiveSession $live, ?User $user, int $apresMessage = 0): array
    {
        $live->refresh();
        $epingle = $live->articleEpingle;

        $messages = LiveMessage::query()->with('user:id,name')
            ->where('live_session_id', $live->id)->where('masque', false)
            ->where('id', '>', $apresMessage)
            // À l'arrivée : les derniers messages ; ensuite, la suite.
            ->when($apresMessage === 0, fn ($q) => $q->orderByDesc('id')->limit(30), fn ($q) => $q->orderBy('id')->limit(50))
            ->get()->sortBy('id')->values()
            ->map(fn (LiveMessage $m) => [
                'id' => $m->id,
                'auteur' => $m->type === 'message' ? ($m->user->name ?? 'Membre') : null,
                'vendeur' => (int) $m->user_id === (int) $live->user_id,
                'texte' => $m->body,
                'type' => $m->type,
            ]);

        // Articles déjà dévoilés par le vendeur (liste « 🛍️ » des spectateurs).
        $articles = $live->articlesVisibles()->with('images')->get()->map(fn (Listing $a) => [
            'id' => $a->id,
            'titre' => $a->title,
            'prix' => TableauDeBord::euros((float) $a->price),
            'photo' => $a->images->sortBy('order')->first()?->url,
            'vendu' => $a->status === 'sold',
            'achetable' => $a->status === 'published' && $a->isOnlinePayable(),
            'lien' => route('listings.show', $a, absolute: false),
        ])->values()->all();

        return [
            'statut' => $live->statut,
            'articles' => $articles,
            'spectateurs' => self::spectateurs($live),
            'coeurs' => $live->coeurs,
            'messages' => $messages->all(),
            'epingle' => $epingle ? [
                'id' => $epingle->id,
                'titre' => $epingle->title,
                'prix' => TableauDeBord::euros((float) $epingle->price),
                'photo' => $epingle->images()->orderBy('order')->value('url'),
                'vendu' => $epingle->status === 'sold',
                'reserve' => self::reservePourUnAutre($epingle, $user),
                'achetable' => $epingle->isOnlinePayable(),
                'lien' => route('listings.show', $epingle),
            ] : null,
        ];
    }
}
