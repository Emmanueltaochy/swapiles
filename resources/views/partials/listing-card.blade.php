<a href="{{ route('listings.show', $listing) }}" class="group block">
    <div class="relative aspect-[4/5] overflow-hidden rounded-2xl bg-gray-100">
        @if($listing->images->first())
            <img loading="lazy" decoding="async" src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}{{ $listing->marque ? ' ' . $listing->marque : '' }}{{ $listing->territoire ? ' — ' . $listing->territoire : '' }}"
                 class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="grid h-full w-full place-items-center text-5xl text-gray-300" aria-hidden="true">📦</div>
        @endif

        @auth
            <button type="button" aria-label="Ajouter aux favoris"
                    onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('account.favorites.toggle.get', $listing) }}';"
                    class="absolute right-2 top-2 z-20 grid h-9 w-9 place-items-center rounded-full bg-white/90 text-lg shadow">
                {{ auth()->user()->favorites()->where('listing_id', $listing->id)->exists() ? '❤️' : '🤍' }}
            </button>
        @else
            <button type="button" aria-label="Se connecter pour ajouter aux favoris"
                    onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('login') }}';"
                    class="absolute right-2 top-2 z-20 grid h-9 w-9 place-items-center rounded-full bg-white/90 text-gray-500 shadow">♡</button>
        @endauth

        @if($listing->status === 'sold')
            <span class="absolute left-2 top-2 rounded-full bg-red-600 px-2 py-1 text-[11px] font-semibold text-white">🔴 Vendu</span>
        @elseif($listing->listing_type === 'don')
            <span class="absolute left-2 top-2 rounded-full bg-green-600 px-2 py-1 text-[11px] font-semibold text-white">🎁 Don</span>
        @elseif($listing->listing_type === 'echange-produits')
            <span class="absolute left-2 top-2 rounded-full bg-blue-600 px-2 py-1 text-[11px] font-semibold text-white">🔄 Échange</span>
        @elseif($listing->listing_type === 'achat')
            <span class="absolute left-2 top-2 rounded-full bg-teal-700 px-2 py-1 text-[11px] font-semibold text-white">🔒 Protégé</span>
        @endif
    </div>

    <div class="pt-2">
        <p class="line-clamp-1 text-sm font-medium text-gray-900">{{ $listing->title }}</p>
        @if($listing->user)
            <p class="mt-0.5 line-clamp-1 text-xs text-gray-500">{{ $listing->user->name }}</p>
        @endif
        <p class="mt-0.5 line-clamp-1 text-xs text-gray-400">
            @if($listing->taille){{ strtoupper($listing->taille) }}@endif
            @if($listing->etat) · {{ $listing->etat }}@endif
            @if($listing->marque) · {{ $listing->marque }}@endif
        </p>
        <p class="mt-1 text-sm font-bold text-gray-900">
            @if($listing->price > 0)
                {{ number_format($listing->price, 0, ',', ' ') }} €
            @else
                <span class="text-green-600">Gratuit</span>
            @endif
        </p>
    </div>
</a>
