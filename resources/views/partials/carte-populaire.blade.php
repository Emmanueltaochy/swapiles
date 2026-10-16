{{-- Carte d'une annonce populaire de l'accueil.
     $badge : pastille en haut à gauche · $stat : ligne de chiffres · $ile : île affichée si autre que celle choisie. --}}
<a href="{{ route('listings.show', $listing) }}"
   class="group bg-white rounded-3xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl transition">
    <div class="relative aspect-[4/5] bg-gray-100 overflow-hidden">
        @if($listing->images->first())
            <img loading="lazy" decoding="async" src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" class="w-full h-full object-cover group-hover:scale-[1.03] transition duration-300">
        @else
            <div class="w-full h-full flex items-center justify-center text-gray-300 text-4xl">📦</div>
        @endif

        {{-- Laisse la place au cœur favori, en haut à droite. --}}
        <span class="absolute top-2 left-2 max-w-[calc(100%-3.25rem)] truncate rounded-full {{ $couleurBadge ?? 'bg-rose-600' }} text-white px-2.5 py-1 text-[11px] font-bold shadow">{{ $badge }}</span>

        @include('partials.favorite-heart', ['listing' => $listing])
    </div>

    <div class="p-3">
        <p class="text-sm font-bold text-gray-900 line-clamp-1">{{ $listing->title }}</p>
        <p class="text-xs text-gray-500 mt-1 truncate">{{ $stat }}</p>
        @if(! empty($ile))
            <p class="text-xs text-gray-400 mt-0.5 truncate">📍 {{ $ile }}</p>
        @endif

        @if(($listing->price ?? 0) > 0)
            <p class="text-lg font-bold text-gray-900 mt-2">{{ number_format($listing->price, 0, ',', ' ') }} €</p>
        @else
            <p class="text-lg font-bold text-teal-700 mt-2">Gratuit</p>
        @endif
    </div>
</a>
