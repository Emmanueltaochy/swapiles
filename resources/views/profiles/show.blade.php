@extends('layouts.app')

@section('title', $user->name . ' — Profil vendeur' . ($user->territoire ? ' ' . $user->territoire : '') . ' | Swap\'Îles')
@section('meta_description', 'Découvrez le dressing de ' . $user->name . ' sur Swap’Îles : ' . $publishedListingsCount . ' article' . ($publishedListingsCount > 1 ? 's' : '') . ' en vente, ' . $soldListingsCount . ' vendu' . ($soldListingsCount > 1 ? 's' : '') . '. Achat, vente et échange de seconde main dans les îles.')

@php
    $personSchema = array_filter([
        '@type' => 'Person',
        '@id' => route('profiles.show', $user) . '#seller',
        'name' => $user->name,
        'url' => route('profiles.show', $user),
        'image' => $user->avatar ? (\Illuminate\Support\Str::startsWith($user->avatar, 'http') ? $user->avatar : url($user->avatar)) : null,
        'address' => $user->territoire ? ['@type' => 'PostalAddress', 'addressRegion' => $user->territoire, 'addressCountry' => 'FR'] : null,
    ]);

    if ($reviewsCount > 0 && (float) ($user->rating ?? 0) > 0) {
        $personSchema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format((float) $user->rating, 1, '.', ''),
            'reviewCount' => $reviewsCount,
            'bestRating' => '5',
            'worstRating' => '1',
        ];
    }

    $profilePageSchema = [
        '@type' => 'ProfilePage',
        'mainEntity' => $personSchema,
    ];
@endphp

@push('structured_data')
<script type="application/ld+json">
{!! json_encode(['@context' => 'https://schema.org'] + $profilePageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')

{{-- En-tête profil --}}
<section class="bg-white border-b border-gray-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-6">
        <a href="{{ url()->previous() }}" class="text-sm font-semibold text-gray-500 hover:text-teal-700">← Retour</a>

        <div class="mt-4 flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex gap-4 sm:gap-5">
                <div class="grid h-20 w-20 sm:h-24 sm:w-24 shrink-0 place-items-center overflow-hidden rounded-2xl bg-teal-100 text-3xl font-bold text-teal-800">
                    @if($user->avatar)
                        <img src="{{ $user->avatar }}" alt="{{ $user->name }}" class="h-full w-full object-cover">
                    @else
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    @endif
                </div>

                <div class="min-w-0">
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $user->name }}</h1>

                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500">
                        <span>⭐ {{ number_format((float) $user->rating, 1, ',', ' ') }}</span>
                        <span>· {{ $reviewsCount }} avis</span>
                        <span>· {{ number_format($soldListingsCount, 0, ',', ' ') }} vendue{{ $soldListingsCount > 1 ? 's' : '' }}</span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium">
                        <span class="rounded-full bg-teal-50 px-2.5 py-1 text-teal-700">✅ Membre vérifié</span>
                        @if($user->territoire)
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-600">📍 {{ $user->territoire }}</span>
                        @endif
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-600">📅 Depuis {{ $user->created_at->format('Y') }}</span>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex gap-2 sm:shrink-0">
                @if(auth()->check() && auth()->id() !== $user->id)
                    <a href="{{ route('account.messages.show.general', $user) }}" class="flex-1 sm:flex-none rounded-xl bg-teal-600 px-5 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-teal-700">💬 Message</a>
                @elseif(!auth()->check())
                    <a href="{{ route('login') }}" class="flex-1 sm:flex-none rounded-xl bg-teal-600 px-5 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-teal-700">💬 Message</a>
                @endif

                @auth
                    @if(auth()->id() !== $user->id)
                        <button type="button" id="follow-seller-btn" data-url="{{ route('account.seller-follow.toggle', $user) }}"
                                class="flex-1 sm:flex-none rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                            {{ auth()->user()->followedSellers()->where('seller_id', $user->id)->exists() ? '✓ Suivi' : '♡ Suivre' }}
                        </button>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="flex-1 sm:flex-none rounded-xl border border-gray-200 px-5 py-2.5 text-center text-sm font-semibold text-gray-700 transition hover:bg-gray-50">♡ Suivre</a>
                @endauth
            </div>
        </div>

        {{-- Stats --}}
        <div class="mt-6 grid grid-cols-4 gap-2 sm:gap-3">
            @foreach([
                ['v' => number_format($publishedListingsCount, 0, ',', ' '), 'l' => 'annonces'],
                ['v' => number_format($soldListingsCount, 0, ',', ' '), 'l' => 'vendues'],
                ['v' => number_format($totalViewsCount, 0, ',', ' '), 'l' => 'vues'],
                ['v' => number_format($totalFavoritesCount, 0, ',', ' '), 'l' => 'favoris'],
            ] as $stat)
                <div class="rounded-xl border border-gray-100 bg-gray-50 p-3 text-center">
                    <p class="text-lg font-bold text-gray-900">{{ $stat['v'] }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ $stat['l'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Onglets --}}
<section class="sticky top-0 z-30 border-b border-gray-100 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex gap-6 overflow-x-auto no-scrollbar">
            <a href="{{ route('profiles.show', $user) }}" class="border-b-2 py-4 text-sm font-semibold {{ $activeTab === 'annonces' ? 'border-teal-600 text-teal-700' : 'border-transparent text-gray-400 hover:text-gray-600' }}">Annonces</a>
            <a href="{{ route('profiles.show', ['user' => $user, 'tab' => 'about']) }}" class="border-b-2 py-4 text-sm font-semibold {{ $activeTab === 'about' ? 'border-teal-600 text-teal-700' : 'border-transparent text-gray-400 hover:text-gray-600' }}">À propos</a>
            <a href="{{ route('profiles.show', ['user' => $user, 'tab' => 'reviews']) }}" class="border-b-2 py-4 text-sm font-semibold {{ $activeTab === 'reviews' ? 'border-teal-600 text-teal-700' : 'border-transparent text-gray-400 hover:text-gray-600' }}">Avis</a>
        </div>
    </div>
</section>

<section class="bg-gray-50 min-h-screen py-6 sm:py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        @if($activeTab === 'about')
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="lg:col-span-2 rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-gray-900">À propos de {{ $user->name }}</h2>
                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm text-gray-500">Territoire</p>
                            <p class="mt-0.5 font-semibold text-gray-900">{{ $user->territoire ?? 'Non renseigné' }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm text-gray-500">Membre depuis</p>
                            <p class="mt-0.5 font-semibold text-gray-900">{{ $user->created_at->format('m/Y') }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm text-gray-500">Annonces en ligne</p>
                            <p class="mt-0.5 font-semibold text-gray-900">{{ number_format($publishedListingsCount, 0, ',', ' ') }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <p class="text-sm text-gray-500">Note vendeur</p>
                            <p class="mt-0.5 font-semibold text-gray-900">⭐ {{ number_format((float) $user->rating, 1, ',', ' ') }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-gray-900">Confiance</h2>
                    <div class="mt-4 space-y-2.5 text-sm text-gray-600">
                        <p>✅ Profil actif sur Swap'Îles</p>
                        <p>💬 Messagerie intégrée</p>
                        <p>🛡️ Paiement sécurisé selon les annonces</p>
                        <p>🌍 Expédition inter-îles selon les annonces</p>
                    </div>
                </div>
            </div>

        @elseif($activeTab === 'reviews')
            <div class="max-w-3xl rounded-2xl border border-gray-100 bg-white p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-bold text-gray-900">Avis ({{ $reviewsCount }})</h2>
                @forelse($reviews as $review)
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <p class="font-semibold text-gray-900">⭐ {{ number_format((float) ($review->rating ?? 0), 1, ',', ' ') }}</p>
                        <p class="mt-1 text-gray-600">{{ $review->comment ?? 'Aucun commentaire.' }}</p>
                    </div>
                @empty
                    <div class="mt-5 rounded-xl border border-dashed border-gray-200 bg-gray-50 p-8 text-center">
                        <div class="text-5xl" aria-hidden="true">⭐</div>
                        <p class="mt-3 font-semibold text-gray-900">Aucun avis pour le moment</p>
                        <p class="mt-1 text-gray-500">Les avis apparaîtront après les transactions.</p>
                    </div>
                @endforelse
            </div>

        @else
            <div class="mb-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-teal-600">Dressing</p>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">{{ number_format($listings->total(), 0, ',', ' ') }} annonce{{ $listings->total() > 1 ? 's' : '' }} en ligne</h2>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-5">
                @forelse($listings as $listing)
                    <a href="{{ route('listings.show', $listing) }}" class="group overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:shadow-md">
                        <div class="relative aspect-[3/4] overflow-hidden bg-gray-100">
                            @if($listing->images->first())
                                <img src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                            @else
                                <div class="grid h-full w-full place-items-center text-5xl text-gray-300" aria-hidden="true">📦</div>
                            @endif
                            @if(($listing->requires_online_payment ?? false))
                                <span class="absolute left-2 top-2 rounded-full bg-teal-700 px-2.5 py-1 text-[11px] font-semibold text-white">🛡️ Protégé</span>
                            @endif
                        </div>
                        <div class="p-3">
                            <p class="line-clamp-1 text-sm font-medium text-gray-900">{{ $listing->title }}</p>
                            <p class="mt-0.5 line-clamp-1 text-xs text-gray-400">
                                @if($listing->taille){{ strtoupper($listing->taille) }}@endif
                                @if($listing->etat) · {{ $listing->etat }}@endif
                            </p>
                            <p class="mt-1 text-sm font-bold text-gray-900">
                                @if($listing->price > 0){{ number_format($listing->price, 0, ',', ' ') }} €@else<span class="text-green-600">Gratuit</span>@endif
                            </p>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
                        <div class="text-5xl" aria-hidden="true">🌴</div>
                        <h3 class="mt-3 text-lg font-bold text-gray-900">Aucune annonce en ligne</h3>
                    </div>
                @endforelse
            </div>

            @if($listings->hasPages())
                <div class="mt-10">{{ $listings->links() }}</div>
            @endif
        @endif

    </div>
</section>

<script>
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
            pop.className = 'fixed left-1/2 bottom-8 -translate-x-1/2 z-[9999] rounded-full bg-gray-950 text-white px-5 py-3 text-sm font-semibold shadow-2xl';
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
@endsection
