@extends('layouts.app')

@section('title', 'Mon compte – Swap’Îles')

@section('content')
@php
    $user = auth()->user();

    $safeRoute = function ($name, $fallback = '#') {
        return \Illuminate\Support\Facades\Route::has($name) ? route($name) : url($fallback);
    };

    $money = fn ($value) => number_format((float) $value, 0, ',', ' ') . ' €';

    try {
        $toShip = \App\Models\Transaction::where('seller_id', $user->id)
            ->where('status', 'paid')
            ->count();

        $toConfirm = \App\Models\Transaction::where('buyer_id', $user->id)
            ->where('status', 'paid')
            ->count();

        $availableWallet = \App\Models\Transaction::where('seller_id', $user->id)
            ->where('status', 'completed')
            ->whereNotNull('released_at')
            ->sum('seller_amount');

        $unfinishedOrders = \App\Models\Transaction::where('buyer_id', $user->id)
            ->whereIn('status', ['pending', 'checkout', 'requires_payment', 'draft'])
            ->count();

        $recentSales = \App\Models\Transaction::with(['listing', 'buyer'])
            ->where('seller_id', $user->id)
            ->latest()
            ->take(2)
            ->get();

        $recentPurchases = \App\Models\Transaction::with(['listing', 'seller'])
            ->where('buyer_id', $user->id)
            ->latest()
            ->take(2)
            ->get();
    } catch (\Throwable $e) {
        $toShip = 0;
        $toConfirm = 0;
        $availableWallet = 0;
        $unfinishedOrders = 0;
        $recentSales = collect();
        $recentPurchases = collect();
    }

    try {
        $notificationsCount = \App\Models\Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    } catch (\Throwable $e) {
        $notificationsCount = 0;
    }

    $walletReady =
        $user->stripe_account_id
        && $user->stripe_charges_enabled
        && $user->stripe_payouts_enabled
        && $user->stripe_details_submitted;

    $menu = [
        [
            'icon' => '👤',
            'title' => 'Mon profil',
            'desc' => 'Infos personnelles, adresse et préférences',
            'href' => $safeRoute('account.profile.edit', '/account/profile'),
        ],
        [
            'icon' => '📦',
            'title' => 'Mes ventes',
            'desc' => $toShip . ' vente(s) payée(s) à traiter',
            'href' => $safeRoute('account.transactions.index', '/account/transactions'),
        ],
        [
            'icon' => '🛍️',
            'title' => 'Mes achats',
            'desc' => $toConfirm . ' achat(s) à confirmer',
            'href' => $safeRoute('account.transactions.index', '/account/transactions'),
        ],
        [
            'icon' => '💰',
            'title' => 'Mon wallet',
            'desc' => $walletReady ? 'Paiements activés' : 'À configurer',
            'href' => $safeRoute('account.wallet.index', '/account/wallet'),
        ],
        [
            'icon' => '🔔',
            'title' => 'Notifications',
            'desc' => $notificationsCount . ' notification(s) non lue(s)',
            'href' => $safeRoute('account.notifications.index', '/account/notifications'),
        ],
        [
            'icon' => '⚙️',
            'title' => 'Paramètres',
            'desc' => 'Sécurité, compte et préférences',
            'href' => $safeRoute('account.settings', '/account/settings'),
        ],
    ];
@endphp

<section class="bg-gray-50 min-h-screen pb-28">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-950">Mon compte</h1>
                <p class="text-gray-500 mt-1">Bonjour {{ $user->name ?? '👋' }}</p>
            </div>

            <a href="{{ $safeRoute('account.notifications.index', '/account/notifications') }}"
               class="relative w-12 h-12 rounded-full bg-white border border-gray-100 shadow-sm flex items-center justify-center text-2xl">
                🔔
                @if($notificationsCount > 0)
                    <span class="absolute -top-1 -right-1 min-w-6 h-6 px-1 rounded-full bg-red-600 text-white text-xs font-extrabold flex items-center justify-center">
                        {{ $notificationsCount }}
                    </span>
                @endif
            </a>
        </div>

        <div class="rounded-[28px] bg-gradient-to-br from-[#062b2b] to-[#0b7f68] text-white p-5 shadow-sm mb-5">
            <p class="text-sm opacity-80">Résumé</p>
            <div class="grid grid-cols-2 gap-3 mt-4">
                <div class="rounded-2xl bg-white/10 border border-white/10 p-4">
                    <p class="text-3xl font-extrabold">{{ $toShip }}</p>
                    <p class="text-sm font-bold mt-1">À expédier</p>
                </div>

                <div class="rounded-2xl bg-white/10 border border-white/10 p-4">
                    <p class="text-3xl font-extrabold">{{ $toConfirm }}</p>
                    <p class="text-sm font-bold mt-1">À confirmer</p>
                </div>

                <div class="rounded-2xl bg-white/10 border border-white/10 p-4">
                    <p class="text-3xl font-extrabold">{{ $money($availableWallet) }}</p>
                    <p class="text-sm font-bold mt-1">À virer</p>
                </div>

                <div class="rounded-2xl bg-white/10 border border-white/10 p-4">
                    <p class="text-3xl font-extrabold">{{ $unfinishedOrders }}</p>
                    <p class="text-sm font-bold mt-1">Non finalisées</p>
                </div>
            </div>
        </div>

        @if($toShip > 0 || $toConfirm > 0 || $notificationsCount > 0)
            <div class="rounded-[24px] border border-amber-200 bg-amber-50 p-4 text-amber-900 mb-5">
                <p class="font-extrabold">Actions à faire</p>
                <p class="text-sm mt-1">
                    Vous avez peut-être une vente à traiter, un achat à confirmer ou une notification non lue.
                </p>
            </div>
        @endif

        <div class="rounded-[28px] bg-white border border-gray-100 shadow-sm overflow-hidden mb-5">
            @foreach($menu as $item)
                <a href="{{ $item['href'] }}" class="flex items-center gap-4 px-5 py-4 border-b border-gray-100 last:border-b-0 active:bg-gray-50">
                    <div class="w-11 h-11 rounded-2xl bg-gray-50 flex items-center justify-center text-2xl shrink-0">
                        {{ $item['icon'] }}
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="font-extrabold text-gray-950 text-lg">{{ $item['title'] }}</p>
                        <p class="text-sm text-gray-500 truncate">{{ $item['desc'] }}</p>
                    </div>

                    <div class="text-2xl text-gray-300">›</div>
                </a>
            @endforeach

            @if(\Illuminate\Support\Facades\Route::has('logout'))
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-4 px-5 py-4 text-left active:bg-gray-50">
                        <div class="w-11 h-11 rounded-2xl bg-red-50 flex items-center justify-center text-2xl shrink-0">
                            🚪
                        </div>
                        <div class="flex-1">
                            <p class="font-extrabold text-red-600 text-lg">Se déconnecter</p>
                            <p class="text-sm text-gray-500">Quitter mon compte</p>
                        </div>
                    </button>
                </form>
            @endif
        </div>

        <div class="rounded-[28px] bg-white border border-gray-100 shadow-sm overflow-hidden mb-5">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h2 class="text-2xl font-extrabold text-gray-950">Mes ventes</h2>
                    <p class="text-gray-500">{{ $recentSales->count() }} récente(s)</p>
                </div>
                <a href="{{ $safeRoute('account.transactions.index', '/account/transactions') }}" class="text-teal-700 font-extrabold">
                    Voir tout →
                </a>
            </div>

            @forelse($recentSales as $sale)
                <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 last:border-b-0">
                    <div class="w-16 h-16 rounded-2xl bg-gray-100 overflow-hidden shrink-0 flex items-center justify-center text-2xl">
                        @php
                            $img = null;
                            try {
                                $img = $sale->listing?->images?->first()?->url;
                            } catch (\Throwable $e) {
                                $img = null;
                            }
                        @endphp

                        @if($img)
                            <img src="{{ $img }}" class="w-full h-full object-cover" alt="">
                        @else
                            📦
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="font-extrabold text-gray-950 truncate">
                            {{ $sale->listing?->title ?? 'Annonce indisponible' }}
                        </p>
                        <p class="text-sm text-gray-500 truncate">
                            Acheteur : {{ $sale->buyer?->name ?? 'Utilisateur' }}
                        </p>
                    </div>

                    <p class="font-extrabold text-gray-950">
                        {{ $money($sale->amount ?? 0) }}
                    </p>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-gray-500">
                    Aucune vente récente.
                </div>
            @endforelse
        </div>

        <div class="rounded-[28px] bg-white border border-gray-100 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h2 class="text-2xl font-extrabold text-gray-950">Mes achats</h2>
                    <p class="text-gray-500">{{ $recentPurchases->count() }} récent(s)</p>
                </div>
                <a href="{{ $safeRoute('account.transactions.index', '/account/transactions') }}" class="text-teal-700 font-extrabold">
                    Voir tout →
                </a>
            </div>

            @forelse($recentPurchases as $purchase)
                <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 last:border-b-0">
                    <div class="w-16 h-16 rounded-2xl bg-gray-100 overflow-hidden shrink-0 flex items-center justify-center text-2xl">
                        @php
                            $img = null;
                            try {
                                $img = $purchase->listing?->images?->first()?->url;
                            } catch (\Throwable $e) {
                                $img = null;
                            }
                        @endphp

                        @if($img)
                            <img src="{{ $img }}" class="w-full h-full object-cover" alt="">
                        @else
                            📦
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="font-extrabold text-gray-950 truncate">
                            {{ $purchase->listing?->title ?? 'Annonce indisponible' }}
                        </p>
                        <p class="text-sm text-gray-500 truncate">
                            Vendeur : {{ $purchase->seller?->name ?? 'Utilisateur' }}
                        </p>
                    </div>

                    <p class="font-extrabold text-gray-950">
                        {{ $money($purchase->amount ?? 0) }}
                    </p>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-gray-500">
                    Aucun achat récent.
                </div>
            @endforelse
        </div>
    </div>
</section>
@endsection
