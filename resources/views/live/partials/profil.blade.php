{{-- Lives du vendeur sur son profil : en direct, à venir, replays. --}}
@if($lives->whereIn('statut', ['en_direct', 'programme'])->isNotEmpty())
    @php
        $enCours = $lives->firstWhere('statut', 'en_direct');
        $aVenir = $lives->where('statut', 'programme');
        $replays = $lives->where('statut', 'termine');
    @endphp
    <div class="mb-8">
        @if($enCours)
            <a href="{{ route('live.show', $enCours) }}" class="mb-4 flex items-center gap-3 rounded-2xl bg-gray-900 p-3 text-white shadow-lg">
                <span class="relative flex h-3 w-3 shrink-0"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75"></span><span class="relative inline-flex h-3 w-3 rounded-full bg-red-600"></span></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-xs font-extrabold text-red-400">EN DIRECT MAINTENANT</span>
                    <span class="block truncate font-bold">{{ $enCours->titre }}</span>
                </span>
                <span class="shrink-0 rounded-full bg-red-600 px-4 py-2 text-sm font-extrabold">Regarder</span>
            </a>
        @endif
        @foreach($aVenir as $live)
            <a href="{{ route('live.show', $live) }}" class="mb-3 flex items-center gap-3 rounded-2xl border border-teal-100 bg-teal-50 p-3">
                <span class="text-2xl">📅</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-bold text-gray-900">Live : {{ $live->titre }}</span>
                    @if($live->debut_prevu_at)<span class="block text-sm text-teal-800">{{ ucfirst($live->debut_prevu_at->locale('fr')->translatedFormat('l j F à H\hi')) }}</span>@endif
                </span>
                <span class="shrink-0 text-sm font-extrabold text-teal-700">🔔 Me prévenir</span>
            </a>
        @endforeach
        {{-- Les replays ont leur propre onglet (« 🎬 Replays des lives »). --}}
    </div>
@endif
