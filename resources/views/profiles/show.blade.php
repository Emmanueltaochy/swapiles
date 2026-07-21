@extends('layouts.app')

@section('title', $user->name . ' — Profil vendeur Swap\'Îles')
@section('meta_description', 'Découvrez le dressing de ' . $user->name . ' sur Swap’Îles : annonces, avis vendeur et articles disponibles.')

@section('content')

<section class="bg-gray-950 text-white relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-teal-900 via-gray-950 to-emerald-900"></div>
    <div class="absolute -top-20 -right-16 text-[220px] opacity-10">🌴</div>
    <div class="absolute -bottom-20 -left-16 text-[220px] opacity-10">🌊</div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
        <a href="{{ url()->previous() }}" class="text-sm font-black text-white/70 hover:text-white">
            ← Retour
        </a>
    </div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-10 sm:pb-14">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-8">

            <div class="flex flex-col sm:flex-row gap-6 sm:items-center">
                <div class="w-32 h-32 sm:w-40 sm:h-40 rounded-[36px] bg-white/10 border border-white/15 flex items-center justify-center overflow-hidden text-6xl font-black text-white shrink-0 shadow-2xl">
                    @if($user->avatar)
                        <img src="{{ $user->avatar }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                    @else
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    @endif
                </div>

                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <span class="inline-flex rounded-full bg-white/10 border border-white/10 px-3 py-2 text-xs font-black">
                            ✅ Membre vérifié
                        </span>

                        @if($user->territoire)
                            <span class="inline-flex rounded-full bg-white/10 border border-white/10 px-3 py-2 text-xs font-black">
                                📍 {{ $user->territoire }}
                            </span>
                        @endif

                        <span class="inline-flex rounded-full bg-white/10 border border-white/10 px-3 py-2 text-xs font-black">
                            📅 Depuis {{ $user->created_at->format('Y') }}
                        </span>
                    </div>

                    <h1 class="text-4xl sm:text-6xl font-black leading-tight">
                        {{ $user->name }}
                    </h1>

                    <p class="mt-3 text-white/70 max-w-2xl">
                        Dressing membre Swap’Îles · Achetez, échangez ou discutez directement avec ce vendeur.
                    </p>

                    <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="rounded-3xl bg-white/10 border border-white/10 p-4">
                            <p class="text-2xl font-black">{{ number_format($publishedListingsCount, 0, ',', ' ') }}</p>
                            <p class="text-xs text-white/65 font-bold mt-1">annonces</p>
                        </div>

                        <div class="rounded-3xl bg-white/10 border border-white/10 p-4">
                            <p class="text-2xl font-black">{{ number_format($soldListingsCount, 0, ',', ' ') }}</p>
                            <p class="text-xs text-white/65 font-bold mt-1">vendues</p>
                        </div>

                        <div class="rounded-3xl bg-white/10 border border-white/10 p-4">
                            <p class="text-2xl font-black">{{ number_format($totalViewsCount, 0, ',', ' ') }}</p>
                            <p class="text-xs text-white/65 font-bold mt-1">vues</p>
                        </div>

                        <div class="rounded-3xl bg-white/10 border border-white/10 p-4">
                            <p class="text-2xl font-black">{{ number_format($totalFavoritesCount, 0, ',', ' ') }}</p>
                            <p class="text-xs text-white/65 font-bold mt-1">favoris</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                @if(auth()->check() && auth()->id() !== $user->id)
                    <a href="{{ route('account.messages.show.general', $user) }}"
                       class="px-6 py-4 rounded-2xl bg-white text-teal-800 font-black hover:scale-[1.02] transition text-center">
                        💬 Message
                    </a>
                @elseif(!auth()->check())
                    <a href="{{ route('login') }}"
                       class="px-6 py-4 rounded-2xl bg-white text-teal-800 font-black hover:scale-[1.02] transition text-center">
                        💬 Message
                    </a>
                @endif

                @auth
                    @if(auth()->id() !== $user->id)
                        <button type="button"
                                id="follow-seller-btn"
                                data-url="{{ route('account.seller-follow.toggle', $user) }}"
                                class="px-6 py-4 rounded-2xl border border-white/25 text-white font-black hover:bg-white/10 transition">
                            {{ auth()->user()->followedSellers()->where('seller_id', $user->id)->exists() ? '✓ Suivi' : '♡ Suivre' }}
                        </button>
                    @endif
                @else
                    <a href="{{ route('login') }}"
                       class="px-6 py-4 rounded-2xl border border-white/25 text-white font-black hover:bg-white/10 transition text-center">
                        ♡ Suivre
                    </a>
                @endauth
            </div>
        </div>
    </div>
</section>

<section class="bg-white border-b border-gray-100 sticky top-0 z-30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 overflow-x-auto no-scrollbar">
        <div class="flex gap-8 min-w-max">
            <a href="{{ route('profiles.show', $user) }}"
               class="py-4 font-black {{ $activeTab === 'annonces' ? 'border-b-4 border-teal-700 text-teal-700' : 'text-gray-400' }}">
                Annonces
            </a>

            <a href="{{ route('profiles.show', ['user' => $user, 'tab' => 'about']) }}"
               class="py-4 font-black {{ $activeTab === 'about' ? 'border-b-4 border-teal-700 text-teal-700' : 'text-gray-400' }}">
                À propos
            </a>

            <a href="{{ route('profiles.show', ['user' => $user, 'tab' => 'reviews']) }}"
               class="py-4 font-black {{ $activeTab === 'reviews' ? 'border-b-4 border-teal-700 text-teal-700' : 'text-gray-400' }}">
                Avis
            </a>
        </div>
    </div>
</section>

<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        @if($activeTab === 'about')
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="lg:col-span-2 bg-white rounded-[34px] border border-gray-100 shadow-sm p-6">
                    <h2 class="text-2xl font-black text-gray-950">À propos de {{ $user->name }}</h2>

                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="rounded-3xl bg-gray-50 border border-gray-100 p-5">
                            <p class="text-sm text-gray-500 font-bold">Territoire</p>
                            <p class="mt-1 font-black text-gray-950">{{ $user->territoire ?? 'Non renseigné' }}</p>
                        </div>

                        <div class="rounded-3xl bg-gray-50 border border-gray-100 p-5">
                            <p class="text-sm text-gray-500 font-bold">Membre depuis</p>
                            <p class="mt-1 font-black text-gray-950">{{ $user->created_at->format('m/Y') }}</p>
                        </div>

                        <div class="rounded-3xl bg-gray-50 border border-gray-100 p-5">
                            <p class="text-sm text-gray-500 font-bold">Annonces en ligne</p>
                            <p class="mt-1 font-black text-gray-950">{{ number_format($publishedListingsCount, 0, ',', ' ') }}</p>
                        </div>

                        <div class="rounded-3xl bg-gray-50 border border-gray-100 p-5">
                            <p class="text-sm text-gray-500 font-bold">Note vendeur</p>
                            <p class="mt-1 font-black text-gray-950">⭐ {{ number_format((float) $user->rating, 1, ',', ' ') }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-[34px] border border-gray-100 shadow-sm p-6">
                    <h3 class="text-xl font-black text-gray-950">Confiance</h3>
                    <div class="mt-5 space-y-3 text-sm font-bold text-gray-700">
                        <p>✅ Profil actif sur Swap’Îles</p>
                        <p>💬 Messagerie intégrée</p>
                        <p>🛡️ Paiement sécurisé disponible selon les annonces</p>
                        <p>🌍 Expédition inter-îles selon les annonces</p>
                    </div>
                </div>
            </div>

        @elseif($activeTab === 'reviews')
            <div class="bg-white rounded-[34px] border border-gray-100 shadow-sm p-6 max-w-3xl">
                <h2 class="text-2xl font-black text-gray-950">Avis</h2>

                @forelse($reviews as $review)
                    <div class="mt-5 border-t border-gray-100 pt-5">
                        <p class="font-black text-gray-950">
                            ⭐ {{ number_format((float) ($review->rating ?? 0), 1, ',', ' ') }}
                        </p>
                        <p class="text-gray-600 mt-2">
                            {{ $review->comment ?? 'Aucun commentaire.' }}
                        </p>
                    </div>
                @empty
                    <div class="mt-6 rounded-3xl bg-gray-50 border border-dashed border-gray-200 p-8 text-center">
                        <div class="text-5xl mb-3">⭐</div>
                        <p class="font-black text-gray-950">Aucun avis pour le moment</p>
                        <p class="text-gray-500 mt-1">Les avis apparaîtront après les transactions.</p>
                    </div>
                @endforelse
            </div>

        @else
            <div class="flex items-center justify-between mb-6">
                <div>
                    <p class="text-sm font-black uppercase tracking-wide text-teal-700">Dressing</p>
                    <h2 class="text-2xl sm:text-3xl font-black text-gray-950">
                        {{ number_format($listings->total(), 0, ',', ' ') }} annonces en ligne
                    </h2>
                </div>

                <span class="hidden sm:inline-flex px-4 py-2 rounded-full bg-white border border-gray-200 text-sm font-black text-gray-600">
                    Trier par : Récentes
                </span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-5">
                @forelse($listings as $listing)
                    <a href="{{ route('listings.show', $listing) }}" class="group bg-white rounded-3xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl transition">
                        <div class="relative aspect-[3/4] bg-gray-100 overflow-hidden">
                            @if($listing->images->first())
                                <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" loading="lazy" decoding="async" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-300 text-5xl">📦</div>
                            @endif

                            <span class="absolute top-2 right-2 w-9 h-9 rounded-full bg-white/90 flex items-center justify-center shadow text-gray-500">
                                ♡
                            </span>

                            @if(($listing->shipping_enabled ?? false))
                                <span class="absolute top-2 left-2 rounded-full bg-teal-700 text-white px-2.5 py-1 text-[11px] font-black">
                                    🛡️ Protégé
                                </span>
                            @endif
                        </div>

                        <div class="p-3">
                            <p class="text-sm font-black text-gray-950 line-clamp-1">{{ $listing->title }}</p>

                            <p class="text-xs text-gray-500 mt-1 line-clamp-1">
                                @if($listing->taille){{ strtoupper($listing->taille) }}@endif
                                @if($listing->etat) · {{ $listing->etat }}@endif
                            </p>

                            <p class="font-black text-gray-950 mt-2">
                                @if($listing->price > 0)
                                    {{ number_format($listing->price, 0, ',', ' ') }} €
                                @else
                                    <span class="text-green-600">Gratuit</span>
                                @endif
                            </p>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full bg-white border border-dashed border-gray-300 rounded-3xl p-10 text-center">
                        <div class="text-5xl mb-3">🌴</div>
                        <h3 class="text-xl font-black text-gray-950">Aucune annonce en ligne</h3>
                    </div>
                @endforelse
            </div>

            <div class="mt-10">
                {{ $listings->links() }}
            </div>
        @endif

    </div>
</section>

@endsection

<script>
// Seller follow AJAX
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('follow-seller-btn');
    if (!btn) return;

    btn.addEventListener('click', async function () {
        btn.disabled = true;

        try {
            const response = await fetch(btn.dataset.url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({}),
            });

            const data = await response.json();
            btn.textContent = data.following ? '✓ Suivi' : '♡ Suivre';

            const pop = document.createElement('div');
            pop.textContent = data.following ? '✅ Vendeur suivi' : 'Vendeur retiré';
            pop.className = 'fixed left-1/2 bottom-8 -translate-x-1/2 z-[9999] rounded-full bg-gray-950 text-white px-5 py-3 text-sm font-black shadow-2xl';
            document.body.appendChild(pop);

            pop.animate([
                { opacity: 0, transform: 'translate(-50%, 16px)' },
                { opacity: 1, transform: 'translate(-50%, 0)' },
                { opacity: 1, transform: 'translate(-50%, 0)' },
                { opacity: 0, transform: 'translate(-50%, -12px)' }
            ], { duration: 1500, easing: 'ease-out' });

            setTimeout(() => pop.remove(), 1500);
        } finally {
            btn.disabled = false;
        }
    });
});
</script>
