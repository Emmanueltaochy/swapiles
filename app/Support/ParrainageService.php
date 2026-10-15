<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\Parrainage;
use App\Models\ParrainageEvenement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Programme de parrainage.
 *
 *  - Le filleul : protection acheteur offerte jusqu'à son 1er achat abouti.
 *  - Le parrain : un crédit quand ce 1er achat (payé dans l'appli) est
 *    TERMINÉ (réception confirmée, retrait au relais ou validation auto).
 *    Jamais à l'inscription, jamais sur une vente déclarée en main propre.
 *
 * Tout passe par ici ; les points d'entrée (inscription, checkout, paiement,
 * fin de commande, remboursement) ne font qu'appeler ces méthodes.
 */
class ParrainageService
{
    // --- Accès (feature flag) ----------------------------------------------------

    /** Le programme est-il ouvert à ce membre (parrain ou visiteur) ? */
    public static function actifPour(?User $user): bool
    {
        if (config('referral.enabled')) {
            return true;
        }
        if (! $user) {
            return false;
        }

        return $user->isAdmin()
            || in_array(mb_strtolower($user->email), array_map('mb_strtolower', (array) config('referral.testeurs', [])), true);
    }

    /** Parrain correspondant à un code (actuel ou ancien code). */
    public static function parrainDuCode(?string $code): ?User
    {
        $code = CodeParrainage::normaliser((string) $code);
        if ($code === '') {
            return null;
        }

        return User::where('code_parrainage', $code)->orWhere('code_parrainage_ancien', $code)->first();
    }

    // --- Code capté (lien d'invitation, lien différé) ------------------------------

    public static function capter(Request $request, string $code, string $source = 'lien'): void
    {
        $code = CodeParrainage::normaliser($code);
        $request->session()->put('parrainage_code', $code);
        $request->session()->put('parrainage_source', $source);
        Cookie::queue(config('referral.cookie'), $code, (int) config('referral.cookie_jours', 30) * 24 * 60);
    }

    /** @return array{code:?string,source:?string} */
    public static function codeCapte(Request $request): array
    {
        $code = $request->session()->get('parrainage_code') ?: $request->cookie(config('referral.cookie'));
        $code = $code ? CodeParrainage::normaliser((string) $code) : null;

        return ['code' => $code ?: null, 'source' => $code ? ($request->session()->get('parrainage_source') ?: 'lien') : null];
    }

    public static function ipHash(Request $request): string
    {
        return hash('sha256', 'invite|' . \App\Models\UserSession::clientIp($request) . '|' . config('app.key'));
    }

    public static function plateforme(?string $ua): string
    {
        $ua = mb_strtolower((string) $ua);

        return str_contains($ua, 'iphone') || str_contains($ua, 'ipad') ? 'ios' : (str_contains($ua, 'android') ? 'android' : 'web');
    }

    /** Ouverture d'un lien d'invitation : notée pour le lien différé (7 jours max). */
    public static function noterOuverture(Request $request, User $parrain, string $code): void
    {
        if (BotDetector::isBot($request->userAgent())) {
            return;
        }

        DB::table('invitations_ouvertes')->insert([
            'code' => $code,
            'parrain_id' => $parrain->id,
            'ip_hash' => self::ipHash($request),
            'plateforme' => self::plateforme($request->userAgent()),
            'created_at' => now(),
        ]);
        self::journal('referral_link_opened', $parrain->id, donnees: ['plateforme' => self::plateforme($request->userAgent())]);
    }

    /**
     * Lien différé : à la 1re ouverture de l'appli, on retrouve l'invitation
     * ouverte depuis la même connexion (même IP, même type de téléphone) dans
     * les 48 h. Ce n'est qu'un pré-remplissage : le membre voit et corrige.
     */
    public static function apparierInstallation(Request $request, string $plateforme): ?string
    {
        if (self::codeCapte($request)['code']) {
            return null;
        }

        $invitation = DB::table('invitations_ouvertes')
            ->where('ip_hash', self::ipHash($request))
            ->whereIn('plateforme', [$plateforme, 'web'])
            ->where('created_at', '>=', now()->subHours((int) config('referral.appariement_heures', 48)))
            ->orderByDesc('id')->first();

        if (! $invitation) {
            return null;
        }

        self::capter($request, $invitation->code, 'appariement');

        return $invitation->code;
    }

    // --- Attribution ----------------------------------------------------------------

    public static function normaliserEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));
        if (! str_contains($email, '@')) {
            return $email;
        }
        [$local, $domaine] = explode('@', $email, 2);
        $local = explode('+', $local, 2)[0];
        if (in_array($domaine, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
            $domaine = 'gmail.com';
        }

        return $local . '@' . $domaine;
    }

    /** Achats payés dans l'appli (en cours ou terminés). */
    public static function achatsEnLigne(User $user)
    {
        return Transaction::query()->where('buyer_id', $user->id)->enLigne()->whereIn('status', ['paid', 'completed']);
    }

    /** Le membre peut-il encore saisir un code (7 jours, aucun achat, pas de parrain) ? */
    public static function peutSaisir(User $user): bool
    {
        return ! $user->parrain_id
            && $user->created_at && $user->created_at->gte(now()->subDays((int) config('referral.delai_saisie_jours', 7)))
            && ! self::achatsEnLigne($user)->exists();
    }

    /**
     * Rattache un filleul à son parrain. Renvoie un message d'erreur, ou null.
     */
    public static function attacher(User $filleul, string $code, string $source): ?string
    {
        $parrain = self::parrainDuCode($code);

        $erreur = match (true) {
            ! $parrain => 'Ce code de parrainage n’existe pas. Vérifie l’orthographe 🙂',
            ! self::actifPour($parrain) => 'Ce code de parrainage n’est pas valable.',
            $parrain->id === $filleul->id => 'Tu ne peux pas utiliser ton propre code 😉',
            (bool) $parrain->is_banned => 'Ce code de parrainage n’est plus valable.',
            (bool) $filleul->parrain_id => 'Tu as déjà un parrain.',
            ! self::peutSaisir($filleul) => 'Le code de parrainage se saisit dans les ' . config('referral.delai_saisie_jours') . ' jours après l’inscription, avant le 1er achat.',
            self::normaliserEmail($parrain->email) === self::normaliserEmail($filleul->email) => 'Ce code de parrainage ne peut pas être utilisé avec ce compte.',
            default => null,
        };

        if ($erreur) {
            self::journal('referral_code_rejected', $filleul->id, motif: Str::limit(Str::slug($erreur, '_'), 40, ''), donnees: ['code' => CodeParrainage::normaliser($code)]);

            return $erreur;
        }

        $parrainage = DB::transaction(function () use ($filleul, $parrain, $code, $source) {
            $filleul->forceFill(['parrain_id' => $parrain->id, 'parraine_at' => now()])->save();

            return Parrainage::create([
                'parrain_id' => $parrain->id,
                'filleul_id' => $filleul->id,
                'code' => CodeParrainage::normaliser($code),
                'source' => $source,
                'statut' => 'inscrit',
            ]);
        });

        self::journal('referral_signup', $filleul->id, $parrainage->id, canal: $source);

        $prenom = self::prenom($filleul);
        Notification::create([
            'user_id' => $parrain->id,
            'type' => 'referral_joined',
            'title' => '🎉 ' . $prenom . ' a rejoint Swap’Îles grâce à toi !',
            'message' => 'Dès son 1er achat terminé, tu recevras ta récompense.',
            'url' => route('parrainage.index', absolute: false),
        ]);
        Notification::create([
            'user_id' => $filleul->id,
            'type' => 'referral_welcome',
            'title' => 'Bienvenue ! 🌴',
            'message' => 'Tes frais de protection sont offerts sur ton 1er achat 🌴',
            'url' => route('search', absolute: false),
        ]);

        return null;
    }

    public static function prenom(?User $user): string
    {
        $mots = preg_split('/\s+/', trim((string) $user?->name)) ?: [];
        $prenom = $mots[0] ?? 'Un membre';
        $initiale = isset($mots[1]) ? ' ' . mb_strtoupper(mb_substr($mots[1], 0, 1)) . '.' : '';

        return $prenom . $initiale;
    }

    // --- Checkout ------------------------------------------------------------------

    /** Protection offerte : filleul d'un parrain actif, aucun achat abouti. */
    public static function protectionOfferte(User $acheteur): bool
    {
        if (! $acheteur->parrain_id) {
            return false;
        }

        $parrainage = Parrainage::with('parrain')->where('filleul_id', $acheteur->id)->first();

        return $parrainage
            && $parrainage->statut === 'inscrit'
            && $parrainage->parrain
            && self::actifPour($parrainage->parrain)
            && ! self::achatsEnLigne($acheteur)->exists();
    }

    // --- Cycle de vie de la commande -----------------------------------------------

    /** Point d'entrée unique : la transaction vient de changer de statut. */
    public static function surStatut(Transaction $t): void
    {
        try {
            match ($t->status) {
                'paid' => self::apresPaiement($t),
                'completed' => self::apresFinCommande($t),
                'refunded', 'cancelled' => self::apresAnnulation($t),
                default => null,
            };
        } catch (\Throwable $e) {
            // Le parrainage ne doit JAMAIS bloquer un paiement ou une livraison.
            report($e);
        }
    }

    public static function apresPaiement(Transaction $t): void
    {
        if ($t->estDeclaree() || ! $t->buyer_id) {
            return;
        }

        // Empreinte de la carte (anti-fraude), lue chez Stripe.
        self::enregistrerEmpreinte($t);

        $parrainage = Parrainage::where('filleul_id', $t->buyer_id)->where('statut', 'inscrit')->first();
        if ($parrainage) {
            $parrainage->update(['statut' => 'achat_en_cours']);
            self::journal('referral_first_purchase', $t->buyer_id, $parrainage->id, $t->id);
        }
    }

    public static function apresAnnulation(Transaction $t): void
    {
        Portefeuille::restituer($t);

        $parrainage = Parrainage::where('filleul_id', $t->buyer_id)->where('statut', 'achat_en_cours')->first();
        if ($parrainage && $t->buyer && ! self::achatsEnLigne($t->buyer)->exists()) {
            $parrainage->update(['statut' => 'inscrit']);
        }
    }

    public static function enregistrerEmpreinte(Transaction $t): ?string
    {
        if ($t->carte_empreinte || ! $t->stripe_payment_intent_id) {
            return $t->carte_empreinte;
        }
        if (! ($t->buyer?->parrain_id || Parrainage::where('parrain_id', $t->buyer_id)->exists() || self::actifPour($t->buyer))) {
            return null;
        }

        try {
            $empreinte = app(\App\Services\StripePaymentIntentService::class)->carteEmpreinte($t->stripe_payment_intent_id);
        } catch (\Throwable $e) {
            report($e);
            $empreinte = null;
        }

        if ($empreinte) {
            Transaction::whereKey($t->id)->update(['carte_empreinte' => $empreinte]);
            $t->carte_empreinte = $empreinte;
        }

        return $empreinte;
    }

    /**
     * 1er achat terminé : récompense du parrain (une seule fois, contrôles
     * anti-fraude et plafond mensuel).
     */
    public static function apresFinCommande(Transaction $t): void
    {
        if ($t->estDeclaree() || $t->status !== 'completed' || ! $t->buyer_id) {
            return;
        }

        DB::transaction(function () use ($t) {
            $parrainage = Parrainage::where('filleul_id', $t->buyer_id)->lockForUpdate()->first();

            // Déjà décidé (validé, refusé, annulé) : on ne refait rien.
            if (! $parrainage || ! in_array($parrainage->statut, ['inscrit', 'achat_en_cours'], true)) {
                return;
            }

            $parrain = User::find($parrainage->parrain_id);
            $filleul = User::find($parrainage->filleul_id);
            $motif = self::motifRefus($parrain, $filleul, $t);

            if ($motif) {
                $parrainage->update(['statut' => 'refuse', 'motif_refus' => $motif, 'transaction_id' => $t->id]);
                self::journal('referral_reward_rejected', $parrain?->id, $parrainage->id, $t->id, motif: $motif);
                Log::info('[parrainage] récompense refusée', ['parrainage' => $parrainage->id, 'motif' => $motif]);

                return;
            }

            $montant = (int) round(max(0, (float) config('referral.reward_amount', 0)) * 100);
            $parrainage->update([
                'statut' => 'valide',
                'transaction_id' => $t->id,
                'montant_centimes' => $montant,
                'valide_at' => now(),
            ]);

            Portefeuille::crediter($parrain, $montant, 'credit_parrainage', now()->addDays((int) config('referral.credit_validity_days', 90)), $parrainage, 'Parrainage de ' . self::prenom($filleul));
            self::journal('referral_reward_granted', $parrain->id, $parrainage->id, $t->id, donnees: ['montant_centimes' => $montant]);

            if ($montant > 0) {
                Notification::create([
                    'user_id' => $parrain->id,
                    'type' => 'referral_reward',
                    'title' => '💚 ' . self::prenom($filleul) . ' a fait son 1er achat',
                    'message' => TableauDeBord::euros($montant / 100) . ' ajoutés à ton portefeuille. Merci de faire grandir Swap’Îles !',
                    'url' => route('parrainage.portefeuille', absolute: false),
                ]);
            }

            self::paliers($parrain);
        });
    }

    /** Motif de refus, ou null si la récompense peut être versée. */
    public static function motifRefus(?User $parrain, ?User $filleul, Transaction $t): ?string
    {
        if (! $parrain || ! $filleul || $parrain->is_banned) {
            return 'parrain_indisponible';
        }
        if (! self::actifPour($parrain)) {
            return 'programme_inactif';
        }
        if (self::normaliserEmail($parrain->email) === self::normaliserEmail($filleul->email)) {
            return 'meme_email';
        }
        if (self::memeAppareil($parrain, $filleul)) {
            return 'meme_appareil';
        }
        if (self::memeCarte($parrain, $t)) {
            return 'meme_carte';
        }
        if (self::memeTelephone($parrain, $filleul)) {
            return 'meme_telephone';
        }

        $ceMois = Parrainage::where('parrain_id', $parrain->id)->where('statut', 'valide')
            ->where('valide_at', '>=', now()->startOfMonth())->count();
        if ($ceMois >= (int) config('referral.max_rewards_per_month', 10)) {
            return 'plafond_mensuel';
        }

        return null;
    }

    public static function memeAppareil(User $a, User $b): bool
    {
        $empreintes = fn (User $u) => DB::table('user_sessions')->where('user_id', $u->id)->whereNotNull('device_fingerprint')->distinct()->pluck('device_fingerprint');
        $jetons = fn (User $u) => DB::table('device_tokens')->where('user_id', $u->id)->pluck('token');

        return $empreintes($a)->intersect($empreintes($b))->isNotEmpty()
            || $jetons($a)->intersect($jetons($b))->isNotEmpty();
    }

    public static function memeCarte(User $parrain, Transaction $t): bool
    {
        $carte = self::enregistrerEmpreinte($t);

        return $carte !== null && Transaction::where('buyer_id', $parrain->id)->where('carte_empreinte', $carte)->exists();
    }

    public static function memeTelephone(User $a, User $b): bool
    {
        $numeros = function (User $u) {
            return collect([$u->phone])
                ->merge(Transaction::where('buyer_id', $u->id)->whereNotNull('buyer_phone')->pluck('buyer_phone'))
                ->map(fn ($n) => substr(preg_replace('/\D/', '', (string) $n), -9))
                ->filter(fn ($n) => strlen($n) === 9)->unique();
        };

        return $numeros($a)->intersect($numeros($b))->isNotEmpty();
    }

    /** Badges : Ambassadeur (3 filleuls validés), à mettre en avant (10). */
    public static function paliers(User $parrain): void
    {
        $valides = Parrainage::where('parrain_id', $parrain->id)->where('statut', 'valide')->count();

        if ($valides >= (int) config('referral.palier_ambassadeur', 3) && ! $parrain->ambassadeur_at) {
            $parrain->forceFill(['ambassadeur_at' => now()])->save();
        }
        if ($valides >= (int) config('referral.palier_mise_en_avant', 10) && ! $parrain->a_mettre_en_avant_at) {
            $parrain->forceFill(['a_mettre_en_avant_at' => now()])->save();
            AdminEvent::notify('Parrain à mettre en avant', $parrain->name . ' a ' . $valides . ' filleuls validés.', null, 'parrainage');
        }
    }

    /** Annulation manuelle (fraude). */
    public static function annulerRecompense(Parrainage $parrainage, string $raison): array
    {
        $bilan = Portefeuille::annulerParrainage($parrainage);
        $parrainage->update(['statut' => 'annule', 'annule_at' => now(), 'annule_raison' => $raison, 'motif_refus' => 'annulation_admin']);
        self::journal('referral_reward_cancelled', $parrainage->parrain_id, $parrainage->id, $parrainage->transaction_id, motif: 'annulation_admin', donnees: $bilan + ['raison' => $raison]);

        // Badge retiré si le parrain repasse sous le palier.
        $parrain = $parrainage->parrain;
        if ($parrain && $parrain->ambassadeur_at
            && Parrainage::where('parrain_id', $parrain->id)->where('statut', 'valide')->count() < (int) config('referral.palier_ambassadeur', 3)) {
            $parrain->forceFill(['ambassadeur_at' => null])->save();
        }

        return $bilan;
    }

    // --- Journal ---------------------------------------------------------------------

    public static function journal(string $evenement, ?int $userId = null, ?int $parrainageId = null, ?int $transactionId = null, ?string $canal = null, ?string $motif = null, ?array $donnees = null): void
    {
        ParrainageEvenement::create([
            'evenement' => $evenement,
            'user_id' => $userId,
            'parrainage_id' => $parrainageId,
            'transaction_id' => $transactionId,
            'canal' => $canal ? mb_substr($canal, 0, 20) : null,
            'motif' => $motif,
            'donnees' => $donnees,
            'created_at' => now(),
        ]);
    }
}
