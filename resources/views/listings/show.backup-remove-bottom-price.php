@extends('layouts.app')

@section('title', $listing->title . ' — Swap\'Îles')

@section('content')

<section class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <a href="{{ url()->previous() }}" class="text-sm font-semibold text-gray-500 hover:text-teal-700">
            ← Retour aux annonces
        </a>
    </div>
</section>

<section class="bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

        @if(session('status'))
            <div class="mb-6 bg-teal-50 text-teal-800 rounded-2xl p-4 text-sm font-semibold">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <div class="lg:col-span-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @forelse($listing->images as $image)
                        <div class="bg-gray-100 overflow-hidden {{ $loop->first ? 'sm:rounded-l-3xl' : '' }} {{ $loop->iteration == 2 ? 'sm:rounded-r-3xl' : '' }} rounded-2xl">
                            <img src="{{ $image->url }}" alt="{{ $listing->title }}" class="w-full aspect-[4/5] object-cover">
                        </div>

                        @if($loop->iteration >= 4)
                            @break
                        @endif
                    @empty
                        <div class="sm:col-span-2 bg-gray-100 rounded-3xl aspect-[4/3] flex items-center justify-center text-gray-300 text-7xl">
                            📦
                        </div>
                    @endforelse
                </div>

                <div class="mt-6 hidden lg:block">
                    @if($listing->user)
                        <h2 class="text-lg font-extrabold text-gray-900 mb-4">Dressing du membre</h2>

                        <div class="grid grid-cols-4 gap-4">
                            @foreach($listing->user->listings()->with('images')->where('status', 'published')->where('id', '!=', $listing->id)->latest()->limit(4)->get() as $other)
                                <a href="{{ route('listings.show', $other) }}" class="group">
                                    <div class="relative aspect-square bg-gray-100 rounded-2xl overflow-hidden">
                                        @if($other->images->first())
                                            <img src="{{ $other->images->first()->url }}" alt="{{ $other->title }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-gray-300 text-4xl">📦</div>
                                        @endif

                                        <span class="absolute bottom-2 right-2 bg-white/90 rounded-full w-8 h-8 flex items-center justify-center shadow">
                                            ♡
                                        </span>
                                    </div>

                                    <p class="text-sm font-semibold text-gray-900 line-clamp-1 mt-2">{{ $other->title }}</p>
                                    <p class="text-xs text-gray-500 line-clamp-1">
                                        @if($other->taille){{ strtoupper($other->taille) }}@endif
                                        @if($other->etat) · {{ $other->etat }}@endif
                                    </p>
                                    <p class="text-sm font-bold text-gray-900 mt-1">
                                        @if($other->price > 0)
                                            {{ number_format($other->price, 0, ',', ' ') }} €
                                        @else
                                            Gratuit
                                        @endif
                                    </p>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <aside class="lg:col-span-4">
                <div class="lg:sticky lg:top-24 space-y-4">

                    <div class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm">

                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 leading-tight">
                                    {{ $listing->title }}
                                </h1>

@php
    $protectionFee = ($listing->shipping_enabled ?? false)
        ? max(0.99, round(($listing->price * 0.05) + 0.70, 2))
        : 0;

    $protectedTotal = $listing->price + $protectionFee;
@endphp

<div class="mt-5 rounded-3xl bg-gray-50 border border-gray-100 p-4">
    <p class="text-sm text-gray-500">Prix article vendeur</p>

    <p class="text-2xl font-bold text-gray-900">
        {{ number_format($listing->price, 2, ',', ' ') }} €
    </p>

    @if($listing->shipping_enabled ?? false)
        <button type="button"
                class="prix-protege mt-3 text-left"
                data-title="{{ e($listing->title) }}"
                data-price="{{ number_format($listing->price, 2, ',', ' ') }}"
                data-fee="{{ number_format($protectionFee, 2, ',', ' ') }}"
                data-total="{{ number_format($protectedTotal, 2, ',', ' ') }}">
            <span class="block text-lg font-extrabold text-teal-700">
                {{ number_format($protectedTotal, 2, ',', ' ') }} € protégé 🛡️
            </span>

            <span class="block text-xs text-gray-500 mt-1">
                Inclut la protection acheteur Swap’Îles. Livraison calculée au paiement.
            </span>
        </button>
    @endif

    <div class="mt-3 flex flex-wrap gap-2">
        @if($listing->shipping_enabled ?? false)
            <span class="inline-flex rounded-full bg-blue-50 text-blue-700 text-xs font-bold px-3 py-2">
                📦 Colissimo disponible
            </span>
            <span class="inline-flex rounded-full bg-teal-50 text-teal-700 text-xs font-bold px-3 py-2">
                🔒 Paiement CB sécurisé
            </span>
        @endif

        @if($listing->pickup_enabled ?? true)
            <span class="inline-flex rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold px-3 py-2">
                🤝 Remise en main propre
            </span>
        @endif
    </div>
</div>





<p class="mt-2 text-gray-600">
                                    @if($listing->taille){{ strtoupper($listing->taille) }}@endif
                                    @if($listing->etat) · {{ $listing->etat }}@endif
                                    @if($listing->marque) · {{ $listing->marque }}@endif
                                </p>
                            </div>

                            @auth
                                <form method="POST" action="{{ route('account.favorites.toggle', $listing) }}">
                                    @csrf
                                    <button class="w-11 h-11 rounded-full border border-gray-200 flex items-center justify-center text-xl hover:bg-gray-50">
                                        {{ auth()->user()->favorites()->where('listing_id', $listing->id)->exists() ? '❤️' : '🤍' }}
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('login') }}" class="w-11 h-11 rounded-full border border-gray-200 flex items-center justify-center text-xl text-gray-500 hover:bg-gray-50">
                                    ♡
                                </a>
                            @endauth
                        </div>

                        <div id="product-live-proof" class="mt-4 text-sm text-gray-500"></div>

                        <div class="mt-5">
                            <p class="text-3xl font-extrabold text-teal-700">
                                @if($listing->price > 0)
                                    {{ number_format($listing->price, 0, ',', ' ') }} €
                                @else
                                    Gratuit
                                @endif
                            </p>

                            @if($listing->listing_type === 'achat')
                                <p class="mt-1 text-sm text-teal-700">
                                     🛡️
                                </p>
                            @endif
                        </div>

                        <div class="mt-5 border-t border-gray-100 pt-5 grid grid-cols-2 gap-4 text-sm">
                            @if($listing->marque)
                                <div>
                                    <p class="text-gray-500">Marque</p>
                                    <p class="font-bold text-gray-900">{{ $listing->marque }}</p>
                                </div>
                            @endif

                            @if($listing->taille)
                                <div>
                                    <p class="text-gray-500">Taille</p>
                                    <p class="font-bold text-gray-900">{{ strtoupper($listing->taille) }}</p>
                                </div>
                            @endif

                            @if($listing->etat)
                                <div>
                                    <p class="text-gray-500">État</p>
                                    <p class="font-bold text-gray-900">{{ $listing->etat }}</p>
                                </div>
                            @endif

                            @if($listing->category_level1)
                                <div>
                                    <p class="text-gray-500">Catégorie</p>
                                    <p class="font-bold text-gray-900">{{ $listing->category_level1 }}</p>
                                </div>
                            @endif
                        </div>

                        <div class="mt-5 border-t border-gray-100 pt-5">
                            <h2 class="font-extrabold text-gray-900 mb-2">Description</h2>
                            <p class="text-gray-700 whitespace-pre-line leading-relaxed">{{ $listing->description }}</p>
                        </div>

                        @if($listing->location_address || $listing->territoire)
                            <div class="mt-5 text-sm text-gray-600">
                                📍 {{ $listing->location_address ?? $listing->territoire }}
                            </div>
                        @endif

                        <div class="mt-6 space-y-3">
                            @if($listing->listing_type === 'achat')
                                @auth
                                    @if(auth()->id() !== $listing->user_id)
                                        <a href="{{ route('checkout.show', $listing) }}" class="block text-center w-full bg-teal-700 hover:bg-teal-800 text-white font-extrabold rounded-2xl px-6 py-4 transition">
                                            Acheter
                                        </a>
                                    @endif
                                @else
                                    <a href="{{ route('login') }}" class="block text-center w-full bg-teal-700 hover:bg-teal-800 text-white font-extrabold rounded-2xl px-6 py-4 transition">
                                        Se connecter pour acheter
                                    </a>
                                @endauth
                                <button class="w-full border border-teal-700 text-teal-700 hover:bg-teal-50 font-extrabold rounded-2xl px-6 py-4 transition">
                                    Faire une offre
                                </button>
                            @endif

                            @auth
                                @if(auth()->id() !== $listing->user_id)
                                    <a href="{{ route('account.messages.start', $listing) }}" class="block text-center w-full border border-teal-700 text-teal-700 hover:bg-teal-50 font-extrabold rounded-2xl px-6 py-4 transition">
                                        Message
                                    </a>
                                @else
                                    <a href="{{ route('account.listings.edit', $listing) }}" class="block text-center w-full border border-gray-300 text-gray-700 hover:bg-gray-50 font-extrabold rounded-2xl px-6 py-4 transition">
                                        Modifier mon annonce
                                    </a>
                                @endif
                            @else
                                <a href="{{ route('login') }}" class="block text-center w-full border border-teal-700 text-teal-700 hover:bg-teal-50 font-extrabold rounded-2xl px-6 py-4 transition">
                                    Connectez-vous pour envoyer un message
                                </a>
                            @endauth
                        </div>
                    </div>

                    @if($listing->listing_type === 'achat')
                        <div class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm">
                            <div class="flex gap-3">
                                <div class="w-10 h-10 rounded-full bg-teal-100 flex items-center justify-center">
                                    🛡️
                                </div>
                                <div>
                                    <h3 class="font-extrabold text-gray-900">Protection acheteur</h3>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Pour les achats en ligne, Swap'Îles sécurise le paiement jusqu’à la bonne réception.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($listing->user)
                        <a href="{{ route('profiles.show', $listing->user) }}" class="block bg-white border border-gray-200 rounded-3xl p-5 shadow-sm hover:shadow-md transition">
                            <div class="flex items-center gap-4">
                                <div class="w-14 h-14 rounded-full bg-teal-100 flex items-center justify-center overflow-hidden font-extrabold text-teal-800">
                                    @if($listing->user->avatar)
                                        <img src="{{ $listing->user->avatar }}" alt="{{ $listing->user->name }}" class="w-full h-full object-cover">
                                    @else
                                        {{ strtoupper(substr($listing->user->name, 0, 1)) }}
                                    @endif
                                </div>

                                <div class="flex-1">
                                    <p class="font-extrabold text-gray-900">{{ $listing->user->name }}</p>
                                    <p class="text-sm text-gray-500">
                                        ⭐ {{ number_format((float) $listing->user->rating, 1, ',', ' ') }}
                                        · {{ $listing->user->transactions_count ?? 0 }} transactions
                                    </p>
                                    <p class="text-xs text-teal-700 font-bold mt-1">
                                        Voir le profil →
                                    </p>
                                </div>
                            
@if(
    ($listing->accepts_online_payment ?? false)
    || ($listing->payment_method ?? null) === 'cb'
    || ($listing->payment_method ?? null) === 'stripe'
    || ($listing->seller?->stripe_payouts_enabled ?? false)
    || ($listing->user?->stripe_payouts_enabled ?? false)
)
<div class="mt-3 flex flex-wrap gap-2">

    <div class="inline-flex items-center gap-2 bg-teal-50 text-teal-800 text-xs font-bold px-3 py-2 rounded-full border border-teal-100">
        🔒 Paiement sécurisé
    </div>

    <div class="inline-flex items-center gap-2 bg-blue-50 text-blue-800 text-xs font-bold px-3 py-2 rounded-full border border-blue-100">
        📦 Livraison Colissimo disponible
    </div>

    <div class="inline-flex items-center gap-2 bg-emerald-50 text-emerald-800 text-xs font-bold px-3 py-2 rounded-full border border-emerald-100">
        🤝 Remise en main propre possible
    </div>

</div>
@else
<div class="mt-3">
    <div class="inline-flex items-center gap-2 bg-orange-50 text-orange-800 text-xs font-bold px-3 py-2 rounded-full border border-orange-100">
        📍 Remise en main propre uniquement
    </div>
</div>
@endif

</div>
                        </a>
                    @endif

                </div>
            </aside>

        </div>

    </div>
</section>

@endsection

<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('product-live-proof');
    if (!el) return;

    const phrases = [
        '👀 Article consulté récemment',
    if (phrase) {
        el.textContent = phrase;
    } else {
        el.remove();
    }
});
</script>
