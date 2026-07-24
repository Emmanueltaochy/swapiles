<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\ListingInterest;
use App\Models\Message;
use App\Models\Review;
use App\Models\SentEmail;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Territoires;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;

class AdminActivity extends Page
{
    protected static ?string $navigationLabel = 'Activité & Emails';

    protected static ?string $title = 'Activité & Emails';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.admin-activity';

    public function getViewData(): array
    {
        $tab = request()->query('tab') === 'emails' ? 'emails' : 'activity';

        return [
            'tab' => $tab,
            'activities' => $tab === 'activity' ? $this->buildActivityFeed() : collect(),
            'emails' => $tab === 'emails' ? $this->recentEmails() : collect(),
            'emailsTable' => Schema::hasTable('sent_emails'),
        ];
    }

    /** Derniers e-mails envoyés aux utilisateurs. */
    protected function recentEmails()
    {
        if (! Schema::hasTable('sent_emails')) {
            return collect();
        }

        return SentEmail::query()
            ->latest('created_at')
            ->limit(300)
            ->get();
    }

    /** Flux fusionné des dernières actions du site. */
    protected function buildActivityFeed()
    {
        $items = collect();
        $take = 40;

        // Inscriptions
        foreach (User::query()->latest('created_at')->limit($take)->get() as $u) {
            $items->push([
                'at' => $u->created_at,
                'icon' => '👋',
                'color' => 'text-indigo-600',
                'text' => 'Nouvel inscrit : ' . ($u->name ?? 'Membre') . ' — ' . Territoires::display($u->territoire ?: '—'),
                'url' => UserResource::getUrl('view', ['record' => $u->id]),
            ]);
        }

        // Annonces publiées
        foreach (Listing::query()->with('user')->latest('created_at')->limit($take)->get() as $l) {
            $items->push([
                'at' => $l->created_at,
                'icon' => '🆕',
                'color' => 'text-teal-600',
                'text' => 'Nouvelle annonce : « ' . $l->title . ' » par ' . ($l->user?->name ?? '—'),
                'url' => ListingResource::getUrl('view', ['record' => $l->id]),
            ]);
        }

        // Transactions
        foreach (Transaction::query()->with(['buyer', 'listing'])->latest('created_at')->limit($take)->get() as $t) {
            $items->push([
                'at' => $t->created_at,
                'icon' => '💳',
                'color' => 'text-emerald-700',
                'text' => 'Transaction #' . $t->id . ' — ' . number_format((float) $t->amount, 2, ',', ' ') . ' € · ' . $t->status
                    . ($t->listing ? ' · ' . $t->listing->title : ''),
                'url' => TransactionResource::getUrl('view', ['record' => $t->id]),
            ]);
        }

        // Demandes de livraison (intérêt inter-îles)
        foreach (ListingInterest::query()->with(['listing', 'buyer'])->latest('created_at')->limit($take)->get() as $i) {
            $items->push([
                'at' => $i->created_at,
                'icon' => '📩',
                'color' => 'text-amber-600',
                'text' => 'Demande de livraison ' . Territoires::origin($i->buyer_territoire)
                    . ' sur « ' . ($i->listing?->title ?? '—') . ' »',
                'url' => $i->listing ? ListingResource::getUrl('view', ['record' => $i->listing_id]) : null,
            ]);
        }

        // Favoris
        foreach (Favorite::query()->with(['user', 'listing'])->latest('created_at')->limit($take)->get() as $f) {
            $items->push([
                'at' => $f->created_at,
                'icon' => '❤️',
                'color' => 'text-rose-600',
                'text' => ($f->user?->name ?? 'Un membre') . ' a ajouté « ' . ($f->listing?->title ?? '—') . ' » en favori',
                'url' => $f->listing ? ListingResource::getUrl('view', ['record' => $f->listing_id]) : null,
            ]);
        }

        // Avis
        foreach (Review::query()->with(['reviewer', 'reviewed'])->latest('created_at')->limit($take)->get() as $r) {
            $items->push([
                'at' => $r->created_at,
                'icon' => '⭐',
                'color' => 'text-yellow-600',
                'text' => 'Avis ' . $r->rating . '/5 : ' . ($r->reviewer?->name ?? '—') . ' → ' . ($r->reviewed?->name ?? '—'),
                'url' => null,
            ]);
        }

        // Messages (modération)
        foreach (Message::query()->with(['sender', 'receiver'])->latest('created_at')->limit($take)->get() as $m) {
            $items->push([
                'at' => $m->created_at,
                'icon' => '💬',
                'color' => 'text-gray-600',
                'text' => 'Message : ' . ($m->sender?->name ?? '—') . ' → ' . ($m->receiver?->name ?? '—'),
                'url' => null,
            ]);
        }

        return $items
            ->filter(fn ($i) => $i['at'] !== null)
            ->sortByDesc(fn ($i) => $i['at']->timestamp)
            ->take(120)
            ->values();
    }
}
