@extends('layouts.app')

@section('title', 'Créer un live — Swap\'Îles')

@section('content')
<section class="max-w-3xl mx-auto px-4 py-6 sm:py-10">
    <a href="{{ route('live.index') }}" class="text-sm font-bold text-teal-700">← Lives</a>
    <h1 class="mt-3 text-2xl font-extrabold text-gray-900">Créer un live</h1>
    <p class="mt-1 text-gray-600">Choisissez les articles que vous allez présenter. Ils restent <b>cachés</b> aux spectateurs : pendant le live, vous les dévoilez un par un (ou les mettez en avant à l’écran), et ils s’achètent en un clic.</p>

    <form method="POST" action="{{ route('live.store') }}" class="mt-6 space-y-5">
        @csrf
        <div>
            <label for="titre" class="mb-1 block text-sm font-semibold text-gray-700">Titre du live <span class="text-red-600">*</span></label>
            <input id="titre" name="titre" value="{{ old('titre') }}" required maxlength="120" placeholder="Ex : Vide-dressing de Marie 👗"
                   class="w-full rounded-xl border px-4 py-3 text-base @error('titre') border-red-500 @else border-gray-200 @enderror">
            @error('titre')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="debut" class="mb-1 block text-sm font-semibold text-gray-700">Date et heure prévues <span class="font-normal text-gray-400">(facultatif)</span></label>
            <input id="debut" name="debut_prevu_at" type="datetime-local" value="{{ old('debut_prevu_at') }}" class="w-full rounded-xl border border-gray-200 px-4 py-3 text-base">
        </div>
        <div>
            <label for="description" class="mb-1 block text-sm font-semibold text-gray-700">Description <span class="font-normal text-gray-400">(facultatif)</span></label>
            <textarea id="description" name="description" rows="2" maxlength="1000" class="w-full rounded-xl border border-gray-200 px-4 py-3 text-base">{{ old('description') }}</textarea>
        </div>

        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-gray-700">Articles à présenter <span class="text-red-600">*</span></legend>
            @error('articles')<p class="mb-2 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            @if($articles->isEmpty())
                <p class="rounded-xl bg-gray-50 p-4 text-sm text-gray-600">Vous n’avez pas d’annonce en ligne. Publiez d’abord vos articles.</p>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach($articles as $a)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="articles[]" value="{{ $a->id }}" class="peer sr-only" @checked(in_array($a->id, old('articles', [])))>
                            <span class="block overflow-hidden rounded-xl border-2 border-gray-100 bg-white peer-checked:border-teal-600 peer-focus-visible:ring-2 peer-focus-visible:ring-teal-300">
                                <img src="{{ $a->images->sortBy('order')->first()?->url }}" alt="{{ $a->title }}" class="aspect-square w-full object-cover bg-gray-100">
                                <span class="block truncate px-2 pt-1.5 text-xs font-semibold text-gray-800">{{ $a->title }}</span>
                                <span class="block px-2 pb-2 text-xs text-gray-500">{{ \App\Support\TableauDeBord::euros((float) $a->price) }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
        </fieldset>

        <button class="w-full rounded-2xl bg-teal-700 px-6 py-4 font-extrabold text-white hover:bg-teal-800">Créer le live</button>
    </form>
</section>
@endsection
