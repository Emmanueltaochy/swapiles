@extends('layouts.app')

@section('title', 'Mes adresses — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-2xl mx-auto px-4">
        <div class="mb-6">
            <a href="{{ route('account.dashboard') }}" class="text-sm font-bold text-teal-700">← Retour à mon compte</a>
            <h1 class="text-3xl font-extrabold text-gray-900 mt-3">Mes adresses</h1>
            <p class="text-gray-500 mt-2">Cette adresse sera utilisée pour générer les bordereaux Colissimo.</p>
        </div>

        @if(session('status'))
            <div class="mb-5 rounded-2xl bg-green-50 text-green-700 p-4 font-bold">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('account.addresses.update') }}" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 space-y-4">
            @csrf

            <div>
                <label class="text-sm font-bold text-gray-700">Téléphone</label>
                <input name="phone" value="{{ old('phone', $user->phone) }}" class="mt-1 w-full rounded-2xl bg-gray-100 border-0 px-4 py-3">
            </div>

            <div>
                <label class="text-sm font-bold text-gray-700">Adresse</label>
                <input name="address_line1" value="{{ old('address_line1', $user->address_line1) }}" required class="mt-1 w-full rounded-2xl bg-gray-100 border-0 px-4 py-3">
            </div>

            <div>
                <label class="text-sm font-bold text-gray-700">Complément</label>
                <input name="address_line2" value="{{ old('address_line2', $user->address_line2) }}" class="mt-1 w-full rounded-2xl bg-gray-100 border-0 px-4 py-3">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-bold text-gray-700">Code postal</label>
                    <input name="postal_code" value="{{ old('postal_code', $user->postal_code) }}" required class="mt-1 w-full rounded-2xl bg-gray-100 border-0 px-4 py-3">
                </div>

                <div>
                    <label class="text-sm font-bold text-gray-700">Ville</label>
                    <input name="city" value="{{ old('city', $user->city) }}" required class="mt-1 w-full rounded-2xl bg-gray-100 border-0 px-4 py-3">
                </div>
            </div>

            <input type="hidden" name="country_code" value="FR">

            <button class="w-full bg-teal-600 hover:bg-teal-700 text-white font-extrabold rounded-2xl px-6 py-4">
                Enregistrer mon adresse
            </button>
        </form>
    </div>
</section>
@endsection
