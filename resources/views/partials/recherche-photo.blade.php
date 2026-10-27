{{-- 📷 Recherche par photo (public/js/recherche-photo.js). Affiché seulement quand l'IA est active. --}}
@if(\App\Support\IaDepot::disponible())
    <button type="button" data-recherche-photo="{{ route('search.photo') }}"
            title="Chercher avec une photo" aria-label="Chercher avec une photo"
            class="{{ $classe ?? 'shrink-0 rounded-full bg-violet-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-violet-700' }}">
        📷
    </button>
@endif
