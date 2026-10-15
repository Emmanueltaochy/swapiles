@extends('layouts.app')

@section('title', 'Lives — Swap\'Îles')

@section('content')
@php
    $couverture = fn ($live) => $live->articles->first()?->images->sortBy('order')->first()?->url;
@endphp
<section class="max-w-3xl mx-auto px-4 py-6 sm:py-10">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900">🔴 Ventes en direct</h1>
            <p class="mt-1 text-sm text-gray-600">Les vendeurs présentent leurs articles en vidéo. Posez vos questions, achetez en un clic.</p>
            @unless(config('live.ouvert_a_tous'))
                <p class="mt-2 inline-block rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800">🧪 Mode test : visible seulement par vous</p>
            @endunless
        </div>
        @auth
            <a href="{{ route('live.create') }}" class="shrink-0 whitespace-nowrap rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-teal-800">+ Créer un live</a>
        @endauth
    </div>

    <h2 class="mt-8 mb-3 text-lg font-extrabold text-gray-900">En ce moment</h2>
    @if($enDirect->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-200 bg-white px-4 py-8 text-center">
            <p class="text-3xl">📺</p>
            <p class="mt-2 text-sm font-semibold text-gray-600">Aucun live en cours pour l’instant.</p>
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach($enDirect as $live)
                <a href="{{ route('live.show', $live) }}" class="group relative block aspect-[3/4] overflow-hidden rounded-2xl bg-gray-900 shadow-sm">
                    @if($image = $couverture($live))
                        <img src="{{ $image }}" alt="" class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-105">
                    @else
                        <span class="absolute inset-0 grid place-items-center bg-gradient-to-br from-teal-700 to-gray-900 text-5xl">🛍️</span>
                    @endif
                    <span class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/10 to-black/30"></span>
                    <span class="absolute left-2 top-2 flex items-center gap-1.5">
                        <span class="rounded-md bg-red-600 px-1.5 py-0.5 text-[11px] font-extrabold tracking-wide text-white">LIVE</span>
                        <span class="rounded-md bg-black/50 px-1.5 py-0.5 text-[11px] font-bold text-white">👁 {{ \App\Support\LiveService::spectateurs($live) }}</span>
                    </span>
                    <span class="absolute inset-x-0 bottom-0 p-2.5">
                        <span class="block line-clamp-2 text-sm font-extrabold leading-tight text-white">{{ $live->titre }}</span>
                        <span class="mt-1 flex items-center gap-1.5 text-xs text-white/85">
                            <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-teal-600 text-[10px] font-extrabold text-white">{{ mb_strtoupper(mb_substr($live->vendeur->name ?? '?', 0, 1)) }}</span>
                            <span class="truncate">{{ $live->vendeur->name ?? '' }}</span>
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    <h2 class="mt-10 mb-3 text-lg font-extrabold text-gray-900">À venir</h2>
    @forelse($programmes as $live)
        <div class="mb-3 flex items-center gap-2 rounded-2xl border border-gray-100 bg-white p-2.5 shadow-sm">
        <a href="{{ route('live.show', $live) }}" class="flex min-w-0 flex-1 items-center gap-3">
            @if($image = $couverture($live))
                <img src="{{ $image }}" alt="" class="h-16 w-16 shrink-0 rounded-xl bg-gray-100 object-cover">
            @else
                <span class="grid h-16 w-16 shrink-0 place-items-center rounded-xl bg-teal-50 text-2xl">📅</span>
            @endif
            <span class="min-w-0 flex-1">
                <span class="block truncate font-bold text-gray-900">{{ $live->titre }}</span>
                <span class="block truncate text-sm text-gray-600">{{ $live->vendeur->name ?? '' }} · {{ $live->articles->count() }} article{{ $live->articles->count() > 1 ? 's' : '' }}</span>
                @if($live->debut_prevu_at)
                    <span class="mt-1 inline-block rounded-full bg-teal-50 px-2 py-0.5 text-xs font-bold text-teal-800">{{ ucfirst($live->debut_prevu_at->locale('fr')->translatedFormat('l j F à H\hi')) }}</span>
                @endif
            </span>
        </a>
        @if(auth()->id() !== $live->user_id)
            @php $inscrit = in_array($live->id, $mesRappels); @endphp
            @auth
                <form method="POST" action="{{ route('live.rappel', $live) }}" class="shrink-0">@csrf
                    <button class="grid h-11 w-11 place-items-center rounded-full text-lg {{ $inscrit ? 'bg-teal-600 text-white' : 'border border-gray-200 bg-white' }}"
                            aria-label="{{ $inscrit ? 'Ne plus être prévenu' : 'Me prévenir du début du live' }}" title="{{ $inscrit ? 'Vous serez prévenu' : 'Me prévenir' }}">{{ $inscrit ? '🔔' : '🔕' }}</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="grid h-11 w-11 shrink-0 place-items-center rounded-full border border-gray-200 text-lg" aria-label="Me prévenir du début du live">🔕</a>
            @endauth
        @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">Aucun live programmé.</p>
    @endforelse

    @if($replays->isNotEmpty())
        <h2 class="mt-10 mb-3 text-lg font-extrabold text-gray-900">🎬 Replays</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach($replays as $live)
                @include('live.partials.vignette-replay', ['live' => $live])
            @endforeach
        </div>
    @endif

    @if($mesLives->isNotEmpty())
        <h2 class="mt-10 mb-3 text-lg font-extrabold text-gray-900">Mes lives</h2>
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white">
            @foreach($mesLives as $live)
                <a href="{{ route('live.show', $live) }}" class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 text-sm last:border-b-0 hover:bg-gray-50">
                    <span class="truncate font-semibold text-gray-800">{{ $live->titre }}</span>
                    <span @class([
                        'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold',
                        'bg-red-100 text-red-700' => $live->statut === 'en_direct',
                        'bg-teal-50 text-teal-800' => $live->statut === 'programme',
                        'bg-gray-100 text-gray-600' => $live->statut === 'termine',
                    ])>{{ \App\Models\LiveSession::STATUTS[$live->statut] }}</span>
                </a>
            @endforeach
        </div>
    @endif
</section>
@endsection
