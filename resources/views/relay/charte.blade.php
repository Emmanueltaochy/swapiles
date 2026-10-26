@extends('layouts.app')

@section('title', 'Charte des points relais — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 sm:p-10 shadow-sm">
            <h1 class="text-3xl font-extrabold text-gray-900">Charte des points relais</h1>
            <p class="mt-2 text-gray-500">Les règles simples entre Swap’Îles et les commerçants qui accueillent nos colis.</p>
            <div class="mt-8">
                @include('relay.partials.charte-texte')
            </div>
            <div class="mt-8 rounded-2xl bg-teal-50 p-5 text-sm text-teal-900">
                Envie de devenir point relais ? <a href="{{ route('relay.partner') }}" class="font-bold underline">Découvrez le programme</a>.
            </div>
        </div>
    </div>
</section>
@endsection
