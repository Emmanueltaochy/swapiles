@php
    /** @var \App\Models\User|null $source */
    /** @var \Illuminate\Support\Collection $linked */
@endphp
<div class="space-y-3 text-sm">
    <p class="text-gray-600 dark:text-gray-300">
        Comptes ayant partagé la <strong>même IP</strong> ou la <strong>même empreinte d'appareil</strong>
        que @if($source)<strong>{{ $source->name }}</strong> ({{ $source->email }})@else ce compte @endif.
        Un recoupement fort suggère un même individu derrière plusieurs comptes.
    </p>

    @forelse($linked as $u)
        <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $u->id]) }}"
           class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 dark:border-gray-700 p-3 hover:bg-gray-50 dark:hover:bg-gray-800">
            <div class="min-w-0">
                <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                    {{ $u->name }}
                    @if($u->is_banned)
                        <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs font-bold text-red-700">banni</span>
                    @endif
                </p>
                <p class="text-xs text-gray-500 truncate">{{ $u->email }}</p>
            </div>
            <div class="shrink-0 text-right text-xs text-gray-500">
                <p>{{ $u->listings_count }} annonce{{ $u->listings_count > 1 ? 's' : '' }}</p>
                <p>Inscrit le {{ optional($u->created_at)->format('d/m/Y') }}</p>
            </div>
        </a>
    @empty
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-6 text-center text-gray-500">
            Aucun autre compte ne partage cette IP ni cette empreinte d'appareil.
        </div>
    @endforelse
</div>
