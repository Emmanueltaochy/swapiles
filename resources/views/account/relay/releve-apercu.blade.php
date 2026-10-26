@extends('layouts.app')

@section('title', $titre . ' — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-4">
    <div class="max-w-4xl mx-auto px-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('account.relay.dashboard') }}" class="inline-flex items-center gap-1 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-bold text-gray-800 shadow-sm">
                ← Retour à mon espace relais
            </a>
            <button type="button" data-partager-pdf="{{ $pdf }}" data-fichier="{{ $fichier }}"
                    class="inline-flex items-center gap-1 rounded-xl bg-teal-700 px-4 py-3 text-sm font-bold text-white shadow-sm">
                📄 Enregistrer / partager le PDF
            </button>
        </div>
        <h1 class="mt-4 text-xl font-extrabold text-gray-900">{{ $titre }}</h1>

        {{-- Le relevé, à l'échelle de l'écran (format A4 de 794 px de large). --}}
        <div data-apercu-a4 class="mt-3 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <iframe src="{{ $contenu }}" title="{{ $titre }}" style="width:794px;height:1123px;border:0;transform-origin:0 0;display:block;"></iframe>
        </div>
    </div>
</section>
@endsection
