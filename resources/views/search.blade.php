@extends('layouts.app')

@section('title', 'Recherche — Swap\'Îles')

@section('content')
@php
    $selectedCategory = request('category');
    $selectedLevel2 = request('category_level2');
    $selectedLevel3 = request('category_level3');
    $prettyCategory = fn ($value) => ucfirst(str_replace('-', ' ', (string) $value));
    $advancedActive = request()->filled('category_level2') || request()->filled('category_level3')
        || request()->filled('etat') || request()->filled('taille')
        || request()->filled('min_price') || request()->filled('max_price');

    $pillSelect = 'shrink-0 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none';
    $pillInput = 'rounded-full border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none';
@endphp

<section class="bg-white border-b border-gray-200 sticky top-0 z-30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <form method="GET" action="{{ route('search') }}" class="space-y-3">

            {{-- Recherche --}}
            <div class="flex gap-2">
                <label for="q" class="sr-only">Rechercher</label>
                <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="Rechercher un article, une marque…"
                       class="flex-1 rounded-full border-0 bg-gray-100 px-5 py-3 text-sm outline-none focus:ring-2 focus:ring-teal-500">
                <button class="rounded-full bg-teal-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-teal-700">Rechercher</button>
            </div>

            {{-- Filtres principaux --}}
            <div class="flex flex-wrap gap-2">
                <select name="category" id="category_level1_select" onchange="resetSubCategoriesAndSubmit()" class="{{ $pillSelect }}">
                    <option value="">Toutes catégories</option>
                    @foreach($categoryTree as $level1 => $children)
                        <option value="{{ $level1 }}" @selected($selectedCategory === $level1)>{{ $prettyCategory($level1) }}</option>
                    @endforeach
                </select>

                <select name="listing_type" onchange="this.form.submit()" class="{{ $pillSelect }}">
                    <option value="">Tous types</option>
                    <option value="achat" @selected(request('listing_type') === 'achat')>🔒 Achat protégé</option>
                    <option value="negoce-prix" @selected(request('listing_type') === 'negoce-prix')>💵 Prix négociable</option>
                    <option value="don" @selected(request('listing_type') === 'don')>🎁 Don</option>
                    <option value="echange-produits" @selected(request('listing_type') === 'echange-produits')>🔄 Échange</option>
                    <option value="location-vetements" @selected(request('listing_type') === 'location-vetements')>👗 Location</option>
                </select>

                <select name="territoire" onchange="this.form.submit()" class="{{ $pillSelect }}">
                    <option value="">Tous les territoires</option>
                    <option value="La Réunion" @selected(($selectedTerritoire ?? request('territoire')) === 'La Réunion')>🇷🇪 La Réunion</option>
                    <option value="Martinique" @selected(($selectedTerritoire ?? request('territoire')) === 'Martinique')>🇲🇶 Martinique</option>
                    <option value="Guadeloupe" @selected(($selectedTerritoire ?? request('territoire')) === 'Guadeloupe')>🇬🇵 Guadeloupe</option>
                    <option value="Guyane" @selected(($selectedTerritoire ?? request('territoire')) === 'Guyane')>🇬🇫 Guyane</option>
                    <option value="Mayotte" @selected(($selectedTerritoire ?? request('territoire')) === 'Mayotte')>🇾🇹 Mayotte</option>
                </select>

                <select name="sort" onchange="this.form.submit()" class="{{ $pillSelect }}">
                    <option value="">Plus récents</option>
                    <option value="price_asc" @selected(request('sort') === 'price_asc')>Prix croissant</option>
                    <option value="price_desc" @selected(request('sort') === 'price_desc')>Prix décroissant</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Plus anciens</option>
                </select>

                <label class="inline-flex shrink-0 cursor-pointer items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800">
                    <input type="checkbox" name="inter_iles" value="1" onchange="this.form.submit()" @checked(request()->boolean('inter_iles')) class="rounded text-emerald-600 focus:ring-emerald-500">
                    🌍 Inter-îles
                </label>
            </div>

            {{-- Filtres avancés (repliables) --}}
            <details class="group" @if($advancedActive) open @endif>
                <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 text-sm font-semibold text-teal-700 hover:text-teal-900">
                    <span>Plus de filtres</span>
                    <span class="transition group-open:rotate-180" aria-hidden="true">▾</span>
                </summary>

                <div class="mt-3 flex flex-wrap gap-2">
                    <select name="category_level2" id="category_level2_select" onchange="resetLevel3AndSubmit()" class="{{ $pillSelect }}">
                        <option value="">Sous-catégorie</option>
                        @foreach($categoryTree as $level1 => $children)
                            @foreach($children as $level2 => $level3Items)
                                <option value="{{ $level2 }}" data-parent="{{ $level1 }}" @selected($selectedLevel2 === $level2 && (!$selectedCategory || $selectedCategory === $level1))>
                                    {{ $prettyCategory($level1) }} — {{ $prettyCategory($level2) }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>

                    <select name="category_level3" id="category_level3_select" onchange="this.form.submit()" class="{{ $pillSelect }}">
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

                    <select name="etat" onchange="this.form.submit()" class="{{ $pillSelect }}">
                        <option value="">État</option>
                        <option value="Neuf avec étiquette" @selected(request('etat') === 'Neuf avec étiquette')>Neuf avec étiquette</option>
                        <option value="Neuf sans étiquette" @selected(request('etat') === 'Neuf sans étiquette')>Neuf sans étiquette</option>
                        <option value="Très bon état" @selected(request('etat') === 'Très bon état')>Très bon état</option>
                        <option value="Bon état" @selected(request('etat') === 'Bon état')>Bon état</option>
                        <option value="Satisfaisant" @selected(request('etat') === 'Satisfaisant')>Satisfaisant</option>
                    </select>

                    <input name="taille" value="{{ request('taille') }}" type="text" placeholder="Taille" class="{{ $pillInput }} w-24">
                    <input name="min_price" value="{{ request('min_price') }}" type="number" placeholder="Min €" class="{{ $pillInput }} w-24">
                    <input name="max_price" value="{{ request('max_price') }}" type="number" placeholder="Max €" class="{{ $pillInput }} w-24">
                </div>
            </details>

            {{-- Filtres actifs --}}
            @if(collect(request()->query())->filter(fn ($v) => filled($v) && $v !== '0')->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2 pt-1 text-sm">
                    @foreach(request()->query() as $key => $value)
                        @if(filled($value) && $value !== '0' && $key !== 'page')
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-gray-700">
                                {{ $key === 'inter_iles' ? 'Inter-îles' : $prettyCategory($value) }}
                            </span>
                        @endif
                    @endforeach
                    <a href="{{ route('search') }}" class="font-semibold text-teal-700 hover:text-teal-900">Réinitialiser</a>
                </div>
            @endif
        </form>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <div class="mb-5">
        <h1 class="text-xl font-bold text-gray-900">Articles</h1>
        <p class="mt-0.5 text-sm text-gray-500">{{ $listings->total() }} résultat{{ $listings->total() > 1 ? 's' : '' }}</p>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-5">
        @forelse($listings as $listing)
            <a href="{{ route('listings.show', $listing) }}" class="group block">
                <div class="relative aspect-[4/5] overflow-hidden rounded-2xl bg-gray-100">
                    @if($listing->images->first())
                        <img loading="lazy" decoding="async" src="{{ $listing->images->first()->url }}" alt="{{ $listing->title }}"
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

                    @if($listing->listing_type === 'don')
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
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
                <div class="text-5xl" aria-hidden="true">🔍</div>
                <h3 class="mt-3 text-lg font-bold text-gray-900">Aucune annonce trouvée</h3>
                <p class="mt-1 text-gray-500">Essaie un autre mot-clé ou enlève certains filtres.</p>
                <a href="{{ route('search') }}" class="mt-5 inline-flex rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">Voir toutes les annonces</a>
            </div>
        @endforelse
    </div>

    @if($listings->hasPages())
        <div class="mt-10">{{ $listings->links() }}</div>
    @endif
</section>

<script>
const categoryTree = @json($categoryTree);

function prettyCategory(value) {
    if (!value) return '';
    return String(value).replaceAll('-', ' ').replace(/\b\w/g, c => c.toUpperCase());
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
            if (previousLevel2 === level2Key) option.selected = true;
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
                if (previousLevel3 === level3Key) option.selected = true;
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
