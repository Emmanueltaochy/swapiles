@if($listings->count())
    <div class="p-2">
        @foreach($listings as $listing)
            <a href="{{ route('listings.show', $listing) }}"
               class="flex items-center gap-3 p-3 rounded-2xl hover:bg-gray-50">
                <div class="w-14 h-14 rounded-xl bg-gray-100 overflow-hidden shrink-0">
                    @if($listing->images->first())
                        <img src="{{ $listing->images->first()->url }}" class="w-full h-full object-cover">
                    @endif
                </div>

                <div class="min-w-0">
                    <p class="font-extrabold text-gray-900 truncate">{{ $listing->title }}</p>
                    <p class="text-sm text-gray-500 truncate">Vendu par {{ $listing->user->name ?? 'Utilisateur' }}</p>
                    <p class="text-sm font-extrabold text-teal-700">{{ number_format($listing->price, 0, ',', ' ') }} €</p>
                </div>
            </a>
        @endforeach
    </div>
@else
    <div class="p-5 text-sm text-gray-500">
        Aucun article trouvé.
    </div>
@endif
