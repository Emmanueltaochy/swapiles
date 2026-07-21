@extends('layouts.app')

@section('title', 'Recherche — Swap\'Îles')

@section('content')

<section class="bg-white border-b border-gray-200 sticky top-0 z-30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">

        <form method="GET" action="{{ route('search') }}" class="space-y-4">

            <div class="flex flex-col sm:flex-row gap-3">
                <input
                    type="text"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Rechercher des articles"
                    class="flex-1 px-4 py-3 bg-gray-100 rounded-2xl border-0 text-sm focus:ring-2 focus:ring-teal-600"
                >

                <button class="bg-teal-700 hover:bg-teal-800 text-white font-bold px-6 py-3 rounded-2xl transition">
                    Rechercher
                </button>
            </div>

            <div class="flex gap-2 overflow-x-auto pb-1">
                @php
                    $selectedCategory = request('category');
                    $selectedLevel2 = request('category_level2');
                    $selectedLevel3 = request('category_level3');

                    $prettyCategory = function ($value) {
                        return ucfirst(str_replace('-', ' ', (string) $value));
                    };
                @endphp

                <select name="category" id="category_level1_select" onchange="resetSubCategoriesAndSubmit()" class="shrink-0 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                    <option value="">Catégorie</option>
                    @foreach($categoryTree as $level1 => $children)
                        <option value="{{ $level1 }}" @selected($selectedCategory === $level1)>
                            {{ $prettyCategory($level1) }}
                        </option>
                    @endforeach
                </select>

                <select name="category_level2" id="category_level2_select" onchange="resetLevel3AndSubmit()" class="shrink-0 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                    <option value="">Sous-catégorie</option>
                    @foreach($categoryTree as $level1 => $children)
                        @foreach($children as $level2 => $level3Items)
                            <option value="{{ $level2 }}" data-parent="{{ $level1 }}" @selected($selectedLevel2 === $level2 && (!$selectedCategory || $selectedCategory === $level1))>
                                {{ $prettyCategory($level1) }} — {{ $prettyCategory($level2) }}
                            </option>
                        @endforeach
                    @endforeach
                </select>

                <select name="category_level3" id="category_level3_select" onchange="this.form.submit()" class="shrink-0 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                    <option value="">Type précis</option>
                    @foreach($categoryTree as $level1 => $children)
                        @foreach($children as $level2 => $level3Items)
                            @foreach($level3Items as $level3)
                                <option value="{{ $level3 }}" data-parent="{{ $level1 }}" data-level2="{{ $level2 }}" @selected($selectedLevel3 === $level3 && (!$selectedCategory || $selectedCategory === $level1) && (!$selectedLevel2 || $selectedLevel2 === $level2))>
                                    {{ $prettyCategory($level3) }}
                                </option>
                            @endforeach
                        @endforeach
                    @endforeach
                </select>

                <select name="listing_type" onchange="this.form.submit()" class="shrink-0 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                    <option value="">Type</option>
                    <option value="achat" @selected(request('listing_type') === 'achat')>🔒 Achat protégé</option>
                    <option value="negoce-prix" @selected(request('listing_type') === 'negoce-prix')>💵 Prix négociable</option>
                    <option value="don" @selected(request('listing_type') === 'don')>🎁 Don</option>
                    <option value="echange-produits" @selected(request('listing_type') === 'echange-produits')>🔄 Échange</option>
                    <option value="location-vetements" @selected(request('listing_type') === 'location-vetements')>👗 Location</option>
                </select>

                <select name="territoire" onchange="this.form.submit()" class="shrink-0 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                    <option value="">Tous les territoires</option>
                    <option value="La Réunion" @selected(($selectedTerritoire ?? request('territoire')) === 'La Réunion')>🇷🇪 La Réunion</option>
                    <option value="Martinique" @selected(($selectedTerritoire ?? request('territoire')) === 'Martinique')>🇲🇶 Martinique</option>
                    <option value="Guadeloupe" @selected(($selectedTerritoire ?? request('territoire')) === 'Guadeloupe')>🇬🇵 Guadeloupe</option>
                    <option value="Guyane" @selected(($selectedTerritoire ?? request('territoire')) === 'Guyane')>🇬🇫 Guyane</option>
                    <option value="Mayotte" @selected(($selectedTerritoire ?? request('territoire')) === 'Mayotte')>🇾🇹 Mayotte</option>
                </select>

                <label class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-full border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm font-black cursor-pointer">
                    <input type="checkbox" name="inter_iles" value="1" onchange="this.form.submit()" @checked(request()->boolean('inter_iles')) class="rounded text-emerald-700 focus:ring-emerald-600">
                    🌍 Inter-îles
                </label>

                <select name="etat" onchange="this.form.submit()" class="shrink-0 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                    <option value="">État</option>
                    <option value="Neuf avec étiquette" @selected(request('etat') === 'Neuf avec étiquette')>Neuf avec étiquette</option>
                    <option value="Neuf sans étiquette" @selected(request('etat') === 'Neuf sans étiquette')>Neuf sans étiquette</option>
                    <option value="Très bon état" @selected(request('etat') === 'Très bon état')>Très bon état</option>
                    <option value="Bon état" @selected(request('etat') === 'Bon état')>Bon état</option>
                    <option value="Satisfaisant" @selected(request('etat') === 'Satisfaisant')>Satisfaisant</option>
                </select>

                <input name="taille" value="{{ request('taille') }}" type="text" placeholder="Taille" class="shrink-0 w-24 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">

                <input name="min_price" value="{{ request('min_price') }}" type="number" placeholder="Min €" class="shrink-0 w-24 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">

                <input name="max_price" value="{{ request('max_price') }}" type="number" placeholder="Max €" class="shrink-0 w-24 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">

                <select name="sort" onchange="this.form.submit()" class="shrink-0 px-4 py-2 rounded-full border border-gray-300 bg-white text-sm">
                    <option value="">Plus récents</option>
                    <option value="price_asc" @selected(request('sort') === 'price_asc')>Prix croissant</option>
                    <option value="price_desc" @selected(request('sort') === 'price_desc')>Prix décroissant</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Plus anciens</option>
                </select>

            </div>

            @if(request()->query())
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    @foreach(request()->query() as $key => $value)
                        @if($value)
                            <span class="inline-flex items-center gap-2 bg-gray-100 text-gray-700 px-3 py-1.5 rounded-full">
                                {{ $value }}
                            </span>
                        @endif
                    @endforeach

                    <a href="{{ route('search') }}" class="font-bold text-teal-700 hover:text-teal-900">
                        Réinitialiser
                    </a>
                </div>
            @endif

        </form>

    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900">Articles</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $listings->total() }} résultats</p>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-5">

        @forelse($listings as $listing)

            <a href="{{ route('listings.show', $listing) }}" class="group block">

                <div class="relative aspect-[4/5] bg-gray-100 rounded-2xl overflow-hidden">
                    @if($listing->images->first())
                        <img loading="lazy" decoding="async" src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-300 text-5xl">📦</div>
                    @endif

                    @auth
                        <button
                            type="button"
                            onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('account.favorites.toggle.get', $listing) }}';"
                            class="absolute top-2 right-2 w-9 h-9 rounded-full bg-white/90 flex items-center justify-center shadow text-lg z-20"
                        >
                            {{ auth()->user()->favorites()->where('listing_id', $listing->id)->exists() ? '❤️' : '🤍' }}
                        </button>
                    @else
                        <button
                            type="button"
                            onclick="event.preventDefault(); event.stopPropagation(); window.location.href='{{ route('login') }}';"
                            class="absolute top-2 right-2 w-9 h-9 rounded-full bg-white/90 flex items-center justify-center shadow text-gray-500 z-20"
                        >
                            ♡
                        </button>
                    @endauth

                    @if($listing->listing_type === 'don')
                        <span class="absolute top-2 left-2 bg-green-600 text-white text-[11px] font-bold px-2 py-1 rounded-full">🎁 Don</span>
                    @elseif($listing->listing_type === 'echange-produits')
                        <span class="absolute top-2 left-2 bg-blue-600 text-white text-[11px] font-bold px-2 py-1 rounded-full">🔄 Échange</span>
                    @elseif($listing->listing_type === 'achat')
                        <span class="absolute top-2 left-2 bg-teal-700 text-white text-[11px] font-bold px-2 py-1 rounded-full">🔒 Protégé</span>
                    @endif
                </div>

                <div class="pt-2">
                    <p class="text-sm font-semibold text-gray-900 line-clamp-1">{{ $listing->title }}</p>

                    @if($listing->user)
                        <p class="text-xs text-gray-500 mt-1 line-clamp-1">
                            Vendu par <span class="font-semibold text-gray-700">{{ $listing->user->name }}</span>
                        </p>
                    @endif

                    <p class="text-xs text-gray-500 mt-1 line-clamp-1">
                        @if($listing->taille){{ strtoupper($listing->taille) }}@endif
                        @if($listing->etat) · {{ $listing->etat }}@endif
                        @if($listing->marque) · {{ $listing->marque }}@endif
                    </p>

                    <p class="text-sm font-extrabold text-gray-900 mt-1">
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
                <div class="text-5xl mb-3">🔍</div>
                <h3 class="text-xl font-bold text-gray-900">Aucune annonce trouvée</h3>
                <p class="text-gray-500 mt-2">Essaie avec un autre mot-clé ou enlève certains filtres.</p>
            </div>

        @endforelse

    </div>

    <div class="mt-10">
        {{ $listings->links() }}
    </div>

</section>


<script>
document.addEventListener('DOMContentLoaded', function () {
    filterCategoryOptions();
});

const categoryTree = @json($categoryTree);

function prettyCategory(value) {
    if (!value) return '';
    return String(value)
        .replaceAll('-', ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}

function rebuildCategoryOptions() {
    const level1 = document.getElementById('category_level1_select');
    const level2 = document.getElementById('category_level2_select');
    const level3 = document.getElementById('category_level3_select');

    if (!level1 || !level2 || !level3) return;

    const selectedLevel1 = level1.value;
    const previousLevel2 = level2.value;
    const previousLevel3 = level3.value;

    level2.innerHTML = '<option value="">Sous-catégorie</option>';
    level3.innerHTML = '<option value="">Type précis</option>';

    Object.entries(categoryTree).forEach(([level1Key, level2Items]) => {
        if (selectedLevel1 && level1Key !== selectedLevel1) return;

        Object.entries(level2Items).forEach(([level2Key, level3Items]) => {
            const option = document.createElement('option');
            option.value = level2Key;
            option.textContent = selectedLevel1
                ? prettyCategory(level2Key)
                : prettyCategory(level1Key) + ' — ' + prettyCategory(level2Key);

            if (previousLevel2 === level2Key) {
                option.selected = true;
            }

            level2.appendChild(option);
        });
    });

    rebuildLevel3Options(previousLevel3);
}

function rebuildLevel3Options(previousLevel3 = '') {
    const level1 = document.getElementById('category_level1_select');
    const level2 = document.getElementById('category_level2_select');
    const level3 = document.getElementById('category_level3_select');

    if (!level1 || !level2 || !level3) return;

    const selectedLevel1 = level1.value;
    const selectedLevel2 = level2.value;

    level3.innerHTML = '<option value="">Type précis</option>';

    Object.entries(categoryTree).forEach(([level1Key, level2Items]) => {
        if (selectedLevel1 && level1Key !== selectedLevel1) return;

        Object.entries(level2Items).forEach(([level2Key, level3Items]) => {
            if (selectedLevel2 && level2Key !== selectedLevel2) return;

            level3Items.forEach(level3Key => {
                const option = document.createElement('option');
                option.value = level3Key;

                if (selectedLevel2) {
                    option.textContent = prettyCategory(level3Key);
                } else if (selectedLevel1) {
                    option.textContent = prettyCategory(level2Key) + ' — ' + prettyCategory(level3Key);
                } else {
                    option.textContent = prettyCategory(level1Key) + ' — ' + prettyCategory(level2Key) + ' — ' + prettyCategory(level3Key);
                }

                if (previousLevel3 === level3Key) {
                    option.selected = true;
                }

                level3.appendChild(option);
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    rebuildCategoryOptions();
});

function resetSubCategoriesAndSubmit() {
    const level2 = document.getElementById('category_level2_select');
    const level3 = document.getElementById('category_level3_select');
    level2.value = '';
    level3.value = '';
    rebuildCategoryOptions();
    document.getElementById('category_level1_select').form.submit();
}

function resetLevel3AndSubmit() {
    const level3 = document.getElementById('category_level3_select');
    level3.value = '';
    rebuildLevel3Options();
    document.getElementById('category_level2_select').form.submit();
}
</script>

@endsection
