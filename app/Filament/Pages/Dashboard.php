<?php

namespace App\Filament\Pages;

use App\Models\Favorite;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Transaction;
use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Database\Eloquent\Builder;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Tableau de bord';
    protected static ?string $title = 'Tableau de bord Swap’Îles';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.dashboard';

    public function getViewData(): array
    {
        $period = request()->query('period', 'all');
        $startDate = $this->startDateForPeriod($period);

        $filter = function (Builder $query) use ($startDate) {
            return $startDate ? $query->where('created_at', '>=', $startDate) : $query;
        };

        $publishedListings = $filter(Listing::where('status', 'published'));
        $allListings = $filter(Listing::query());
        $transactions = $filter(Transaction::query());
        $paidTransactions = $filter(Transaction::whereIn('status', ['paid', 'completed']));

        return [
            'period' => $period,
            'periodLabel' => $this->periodLabel($period),
            'periods' => [
                'today' => 'Aujourd’hui',
                'week' => 'Cette semaine',
                '15d' => '15 derniers jours',
                '30d' => '30 derniers jours',
                '3m' => '3 mois',
                'all' => 'Infini',
            ],

            'usersCount' => $filter(User::query())->count(),
            'membersTotalCount' => User::count(),
            // « Annonces en ligne » et « Vues » sont des indicateurs d'état ACTUEL :
            // ils ne dépendent pas de la période sélectionnée (sinon ils affichent 0
            // pour une période courte alors que des annonces sont bien en ligne).
            'publishedListingsCount' => Listing::where('status', 'published')->count(),
            'totalListingsCount' => Listing::count(),
            'messagesCount' => $filter(Message::query())->count(),
            'viewsCount' => (int) Listing::sum('views_count'),
            'favoritesCount' => $filter(Favorite::query())->count(),
            'transactionsCount' => (clone $transactions)->count(),
            'paidTransactionsCount' => (clone $paidTransactions)->count(),
            'completedAmount' => (float) $filter(Transaction::where('status', 'completed'))->sum('amount'),
            'commissionAmount' => (float) (clone $paidTransactions)->sum('commission'),
            'buyerProtectionAmount' => (float) (clone $paidTransactions)->sum('buyer_protection_fee'),
            'platformRevenueAmount' => (float) (clone $paidTransactions)->sum('commission') + (float) (clone $paidTransactions)->sum('buyer_protection_fee'),

            'todayUsersCount' => User::whereDate('created_at', today())->count(),
            'todayListingsCount' => Listing::whereDate('created_at', today())->count(),
            'todayMessagesCount' => Message::whereDate('created_at', today())->count(),

            'territories' => (clone $publishedListings)
                ->selectRaw('territoire, COUNT(*) as total')
                ->groupBy('territoire')
                ->orderByDesc('total')
                ->get(),

            'topListings' => (clone $publishedListings)
                ->with('user')
                ->orderByDesc('views_count')
                ->limit(8)
                ->get(),

            'recentTransactions' => (clone $transactions)
                ->with(['listing', 'buyer', 'seller'])
                ->latest()
                ->limit(6)
                ->get(),
        ];
    }

    private function startDateForPeriod(string $period): ?\Illuminate\Support\Carbon
    {
        return match ($period) {
            'today' => today(),
            'week' => now()->startOfWeek(),
            '15d' => now()->subDays(15),
            '30d' => now()->subDays(30),
            '3m' => now()->subMonths(3),
            default => null,
        };
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'today' => 'Aujourd’hui',
            'week' => 'Cette semaine',
            '15d' => '15 derniers jours',
            '30d' => '30 derniers jours',
            '3m' => '3 mois',
            default => 'Infini',
        };
    }
}
