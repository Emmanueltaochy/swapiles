<x-filament-panels::page>
    @php $libelles = \App\Support\InteretLive::CHOIX; @endphp

    <x-filament::section>
        <x-slot name="heading">📊 Sondage « Aimeriez-vous faire du live shopping ? »</x-slot>
        <x-slot name="description">{{ $total }} réponse(s) · {{ $autorises }} membre(s) autorisé(s) à faire des lives. Affiché sur l’accueil et dans « Mon compte ».</x-slot>
        <div style="display:grid;gap:.6rem;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
            @foreach($libelles as $cle => $libelle)
                @php $n = (int) ($reponses[$cle] ?? 0); @endphp
                <div wire:click="filtrer('{{ $cle }}')" role="button" style="cursor:pointer;border:{{ $filtre === $cle ? '2px solid rgb(225,29,72)' : '1px solid rgba(148,163,184,.3)' }};border-radius:.9rem;padding:.8rem;">
                    <div style="font-size:.8rem;font-weight:700;opacity:.7;">{{ $libelle }}</div>
                    <div style="font-size:1.6rem;font-weight:800;">{{ $n }}</div>
                    <div style="font-size:.78rem;opacity:.6;">{{ $total ? round($n * 100 / $total) : 0 }} %</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">🗳️ Qui a voté quoi ({{ $votants->count() }})</x-slot>
        <x-slot name="description">
            {{ $filtre ? 'Filtré sur « ' . $libelles[$filtre] . ' ».' : 'Toutes les réponses, les plus récentes en premier.' }}
            Touche une case ci-dessus pour filtrer, touche-la encore pour tout revoir.
        </x-slot>
        @if($filtre)
            <div style="margin-bottom:.6rem;"><x-filament::button size="xs" color="gray" wire:click="filtrer(null)">Voir toutes les réponses</x-filament::button></div>
        @endif
        @forelse($votants as $v)
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.6rem;flex-wrap:wrap;padding:.55rem 0;border-bottom:1px solid rgba(148,163,184,.2);">
                <div style="min-width:0;">
                    <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $v->id]) }}" style="font-weight:800;color:rgb(13,148,136);">{{ $v->name }}</a>
                    <div style="font-size:.78rem;opacity:.7;word-break:break-all;">{{ $v->email }} · {{ $v->territoire ?: 'île non renseignée' }}{{ $v->is_pro ? ' · Pro' : '' }}</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:.85rem;font-weight:700;">{{ $libelles[$v->live_interet] ?? $v->live_interet }}</div>
                    <div style="font-size:.72rem;opacity:.6;">
                        {{ $v->live_interet_at?->format('d/m/Y H:i') }}
                        @if($v->live_demande_statut === 'en_attente') · demande en attente
                        @elseif($v->live_demande_statut === 'acceptee') · ✅ autorisé
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p style="font-size:.9rem;opacity:.75;">Personne n’a encore répondu{{ $filtre ? ' « ' . $libelles[$filtre] . ' »' : '' }}.</p>
        @endforelse
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">🎥 Demandes pour organiser des lives ({{ $enAttente->count() }})</x-slot>
        <x-slot name="description">Conseil : accepte d’abord les membres actifs avec de bons avis. En acceptant, le membre reçoit une notification et peut programmer son premier live.</x-slot>
        @forelse($enAttente as $d)
            @php [$u, $p] = [$d['u'], $d['p']]; @endphp
            <div style="padding:.9rem 0;border-bottom:1px solid rgba(148,163,184,.2);">
                <div style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
                    <div>
                        <a href="{{ route('profiles.show', $u) }}" target="_blank" style="font-weight:800;">{{ $u->name }}</a>
                        <span style="font-size:.8rem;opacity:.7;"> · {{ $u->territoire ?: 'île non renseignée' }} · {{ $p['pro'] ? 'Compte Pro' : 'Particulier' }}</span>
                    </div>
                    <span style="font-size:.8rem;opacity:.7;">Demandé le {{ $u->live_demande_at?->format('d/m à H:i') }}</span>
                </div>
                <div style="font-size:.88rem;margin-top:.3rem;">
                    {{ $p['ventes'] }} vente(s) · {{ $p['annonces'] }} annonce(s) en ligne ·
                    {{ $p['note'] ? '⭐ ' . $p['note'] . '/5 (' . $p['avis'] . ' avis)' : 'pas encore d’avis' }}
                </div>
                @if($u->live_demande_message)
                    <div style="font-size:.9rem;margin-top:.3rem;">« {{ $u->live_demande_message }} »</div>
                @endif
                <div style="margin-top:.5rem;display:flex;gap:.5rem;">
                    <x-filament::button size="sm" color="success" wire:click="accepter({{ $u->id }})" wire:confirm="Autoriser {{ $u->name }} à faire des lives ?">✅ Accepter</x-filament::button>
                    <x-filament::button size="sm" color="gray" wire:click="refuser({{ $u->id }})" wire:confirm="Refuser la demande de {{ $u->name }} ?">Refuser</x-filament::button>
                </div>
            </div>
        @empty
            <p style="font-size:.9rem;opacity:.75;">Aucune demande en attente pour l’instant.</p>
        @endforelse
    </x-filament::section>

    @if($traitees->count())
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Demandes déjà traitées</x-slot>
            @foreach($traitees as $u)
                <div style="font-size:.88rem;padding:.3rem 0;">{{ $u->live_demande_statut === 'acceptee' ? '✅' : '—' }} {{ $u->name }} · {{ $u->live_demande_statut === 'acceptee' ? 'acceptée' : 'refusée' }}</div>
            @endforeach
        </x-filament::section>
    @endif
</x-filament-panels::page>
