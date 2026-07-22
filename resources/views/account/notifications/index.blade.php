@extends('layouts.app')

@section('title', 'Notifications — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

        @php
            $unreadCount = \App\Models\Notification::where('user_id', auth()->id())->whereNull('read_at')->count();
        @endphp

        <div class="flex items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-2">
                    🔔 Notifications
                    @if($unreadCount > 0)
                        <span class="bg-red-600 text-white text-xs font-extrabold rounded-full px-2.5 py-1">{{ $unreadCount }}</span>
                    @endif
                </h1>
                <p class="text-gray-500 mt-2">Suivez vos ventes, achats, messages et paiements.</p>
            </div>

            @if($unreadCount > 0)
                <form method="POST" action="{{ route('account.notifications.read') }}">
                    @csrf
                    <button class="bg-gray-900 hover:bg-black text-white font-bold px-4 py-3 rounded-2xl text-sm transition">
                        Tout marquer comme lu
                    </button>
                </form>
            @endif
        </div>

        @if(session('status'))
            <div class="mb-6 bg-teal-50 text-teal-800 rounded-2xl p-4 text-sm font-semibold">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            @forelse($notifications as $notification)
                <a href="{{ $notification->clickUrl() }}" class="block p-5 border-b border-gray-100 hover:bg-gray-50 transition {{ is_null($notification->read_at) ? 'bg-red-50 border-l-4 border-l-red-500' : 'bg-white' }}">

                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-extrabold text-gray-900 flex items-center gap-2">
                                @if(is_null($notification->read_at))
                                    <span class="w-2.5 h-2.5 rounded-full bg-red-600 inline-block"></span>
                                @endif
                                {{ $notification->title }}
                            </p>

                            @if($notification->message)
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ $notification->message }}
                                </p>
                            @endif

                            <p class="text-[11px] text-gray-400 mt-2">
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                        </div>

                        @if(is_null($notification->read_at))
                            <span class="bg-red-600 text-white text-[10px] font-extrabold rounded-full px-2 py-1">
                                Nouveau
                            </span>
                        @endif
                    </div>
                </a>
            @empty
                <div class="p-10 text-center">
                    <div class="text-5xl mb-3">🔔</div>
                    <h2 class="text-xl font-bold text-gray-900">Aucune notification</h2>
                    <p class="text-gray-500 mt-2">Vos activités importantes apparaîtront ici.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $notifications->links() }}
        </div>

    </div>
</section>
@endsection
