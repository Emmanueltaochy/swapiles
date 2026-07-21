@extends('layouts.app')

@section('title', 'Paiement annulé — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen flex items-center justify-center px-4 py-12">
    <div class="max-w-lg w-full bg-white rounded-3xl border border-gray-100 shadow-sm p-8 text-center">
        <div class="text-5xl mb-4">❌</div>

        <h1 class="text-3xl font-extrabold text-gray-900">
            Paiement annulé
        </h1>

        <p class="text-gray-500 mt-3">
            Aucun paiement n’a été validé.
        </p>

        <a href="{{ route('listings.show', $transaction->listing) }}" class="inline-flex mt-6 bg-teal-700 text-white font-bold px-6 py-3 rounded-2xl hover:bg-teal-800 transition">
            Retour à l’annonce
        </a>
    </div>
</section>
@endsection
