<?php

namespace App\Models;

use App\Jobs\SendPasswordResetEmail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Support\AdminEvent;
use Illuminate\Notifications\Notifiable;
use App\Notifications\ResetPasswordSwapiles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    /**
     * Contrôle l'accès au panneau d'administration Filament.
     *
     * En production, Filament exige cette méthode : sans elle, personne ne peut
     * ouvrir /admin (erreur 403). L'accès est réservé aux e-mails administrateurs
     * (liste configurable via ADMIN_EMAILS dans le .env, séparés par des virgules).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    /** L'utilisateur fait-il partie des administrateurs (liste ADMIN_EMAILS) ? */
    public function isAdmin(): bool
    {
        if ($this->is_banned) {
            return false;
        }

        $admins = array_filter(array_map(
            'trim',
            explode(',', (string) env('ADMIN_EMAILS', 'cabinet@taochyconsulting.fr'))
        ));

        $admins = array_map('strtolower', $admins);

        return in_array(strtolower((string) $this->email), $admins, true);
    }

    protected static function booted(): void
    {
        static::created(function ($user) {
            AdminEvent::notify(
                'Nouvel utilisateur inscrit',
                ($user->name ?? 'Utilisateur') . ' vient de créer un compte avec l’email ' . ($user->email ?? '-'),
                url('/admin/users/' . $user->id)
            );
        });

        // Un membre banni supprimé garde son e-mail bloqué : impossible de se
        // réinscrire avec la même adresse (la liste noire survit au compte).
        static::deleting(function ($user) {
            if ($user->is_banned) {
                \App\Models\BlockedEmail::block($user->email, 'Compte banni puis supprimé');
            }

            // Les messages sont supprimés en cascade avec le compte : on prévient
            // AVANT (tant qu'ils existent) chaque interlocuteur que le membre a
            // quitté Swap'Îles, pour qu'il comprenne la disparition du fil.
            static::notifyConversationPartnersOfDeletion($user);
        });
    }

    /** Prévient les interlocuteurs qu'un membre supprimé a quitté Swap'Îles. */
    protected static function notifyConversationPartnersOfDeletion(self $user): void
    {
        try {
            $partnerIds = \App\Models\Message::query()
                ->where(fn ($q) => $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id))
                ->get(['sender_id', 'receiver_id'])
                ->flatMap(fn ($m) => [$m->sender_id, $m->receiver_id])
                ->reject(fn ($id) => $id === null || (int) $id === (int) $user->id)
                ->unique()
                ->values();

            foreach ($partnerIds as $partnerId) {
                \App\Models\Notification::create([
                    'user_id' => $partnerId,
                    'type' => 'user_deleted',
                    'title' => 'Membre parti',
                    'message' => 'Un membre avec qui tu échangeais a quitté Swap’Îles (compte supprimé). La conversation a été fermée.',
                    'url' => null,
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'email_verified_at',
        'sharetribe_id', 'phone', 'avatar',
        'stripe_account_id', 'territoire',
        'stripe_charges_enabled', 'stripe_payouts_enabled',
        'stripe_details_submitted', 'stripe_onboarding_complete',
        'address_line1', 'address_line2', 'postal_code', 'city', 'country_code',
        'comment_connu', 'is_pro', 'is_banned',
        'rating', 'transactions_count',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_pro' => 'boolean',
        'is_banned' => 'boolean',
        'rating' => 'decimal:2',
    ];

    public function sendPasswordResetNotification($token): void
    {
        SendPasswordResetEmail::dispatch($this->id, $token);
    }

    public function listings()
    {
        return $this->hasMany(Listing::class);
    }

    public function favorites()
    {
        return $this->belongsToMany(Listing::class, 'favorites')
            ->withTimestamps();
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function sales()
    {
        return $this->hasMany(Transaction::class, 'seller_id');
    }

    public function purchases()
    {
        return $this->hasMany(Transaction::class, 'buyer_id');
    }

    public function reviewsGiven()
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function reviewsReceived()
    {
        return $this->hasMany(Review::class, 'reviewed_id');
    }
    public function favoriteAlerts()
    {
        return $this->hasMany(\App\Models\FavoriteAlert::class);
    }
    public function followedSellers()
    {
        return $this->belongsToMany(User::class, 'seller_follows', 'follower_id', 'seller_id')
            ->withTimestamps();
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'seller_follows', 'seller_id', 'follower_id')
            ->withTimestamps();
    }

    /**
     * Critères de complétion du profil (pour la barre de progression + le badge).
     *
     * @return array<int,array{key:string,label:string,hint:string,done:bool}>
     */
    public function profileChecklistItems(): array
    {
        $hasAddress = filled($this->address_line1) && filled($this->postal_code) && filled($this->city);

        $paymentsOn = $this->stripe_account_id
            && $this->stripe_charges_enabled
            && $this->stripe_payouts_enabled
            && $this->stripe_details_submitted;

        return [
            ['key' => 'avatar', 'label' => 'Photo de profil', 'hint' => 'Ajoutez une photo pour inspirer confiance', 'done' => filled($this->avatar)],
            ['key' => 'email', 'label' => 'E-mail vérifié', 'hint' => 'Confirmez votre adresse e-mail', 'done' => ! is_null($this->email_verified_at)],
            ['key' => 'phone', 'label' => 'Téléphone', 'hint' => 'Renseignez votre numéro', 'done' => filled($this->phone)],
            ['key' => 'address', 'label' => 'Adresse complète', 'hint' => 'Indispensable pour expédier vos ventes', 'done' => $hasAddress],
            ['key' => 'payments', 'label' => 'Paiements activés (CB)', 'hint' => 'Recevez vos ventes en CB sécurisé', 'done' => (bool) $paymentsOn],
            ['key' => 'listing', 'label' => 'Première annonce publiée', 'hint' => 'Mettez un article en vente', 'done' => $this->listings()->exists()],
        ];
    }

    /** Pourcentage de complétion du profil (0-100). */
    public function profileCompletion(): int
    {
        $items = $this->profileChecklistItems();
        $done = 0;
        foreach ($items as $item) {
            if ($item['done']) {
                $done++;
            }
        }

        return count($items) > 0 ? (int) round($done / count($items) * 100) : 0;
    }

    public function hasCompleteProfile(): bool
    {
        return $this->profileCompletion() >= 100;
    }
}


