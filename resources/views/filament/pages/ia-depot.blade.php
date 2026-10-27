<x-filament-panels::page>
    @php
        $usd = fn ($v, $d = 2) => number_format((float) $v, $d, ',', ' ') . ' $';
        $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    @endphp

    <x-filament::section>
        <x-slot name="heading">État (remplissage des annonces et recherche par photo)</x-slot>
        @if(! $b['active'])
            <p style="font-size:.9rem;">🔒 <strong>Masqué</strong> : la clé <code>ANTHROPIC_API_KEY</code> n’est pas encore renseignée dans les secrets GitHub. Le bouton « ✨ Remplir la fiche avec l’IA » n’apparaît à personne.</p>
        @elseif(! $b['disponible'])
            <p style="font-size:.9rem;">⏸️ <strong>En pause</strong> : le budget du mois ({{ $usd($b['budget']) }}) est atteint. Le bouton revient le 1er du mois prochain (ou en relevant <code>IA_DEPOT_BUDGET_MENSUEL</code>).</p>
        @else
            <p style="font-size:.9rem;">✅ <strong>Actif</strong> sur la page de dépôt · modèle <code>{{ $b['modele'] }}</code> · {{ $b['limite_jour'] }} utilisations par membre et par jour · budget {{ $usd($b['budget']) }} par mois.</p>
        @endif
    </x-filament::section>

    <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
        <x-filament::section><div style="opacity:.65;font-weight:700;font-size:.8rem;">Utilisations ce mois-ci</div><div style="font-size:1.8rem;font-weight:800;">{{ $n($b['appels']) }}</div><div style="font-size:.8rem;opacity:.65;">{{ $n($b['depots']) }} annonce(s) remplie(s) · {{ $n($b['recherches_photo']) }} recherche(s) par photo · {{ $n($b['reussis']) }} réussies</div></x-filament::section>
        <x-filament::section><div style="opacity:.65;font-weight:700;font-size:.8rem;">Coût du mois (estimé)</div><div style="font-size:1.8rem;font-weight:800;">{{ $usd($b['cout'], 2) }}</div><div style="font-size:.8rem;opacity:.65;">sur un budget de {{ $usd($b['budget']) }}</div></x-filament::section>
        <x-filament::section><div style="opacity:.65;font-weight:700;font-size:.8rem;">Coût moyen par annonce</div><div style="font-size:1.8rem;font-weight:800;">{{ $usd($b['cout_moyen'], 4) }}</div><div style="font-size:.8rem;opacity:.65;">d’après les jetons facturés</div></x-filament::section>
    </div>

    @if(count($b['par_jour']))
        <x-filament::section>
            <x-slot name="heading">30 derniers jours</x-slot>
            @foreach($b['par_jour'] as $j)
                <div style="display:flex;justify-content:space-between;gap:1rem;padding:.4rem 0;border-bottom:1px solid rgba(148,163,184,.15);font-size:.88rem;">
                    <span>{{ \Illuminate\Support\Carbon::parse($j['jour'])->format('d/m') }}</span>
                    <span><strong>{{ $n($j['appels']) }}</strong> utilisation(s) · {{ $usd($j['cout'], 4) }}</span>
                </div>
            @endforeach
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">Bon à savoir</x-slot>
        <div style="font-size:.85rem;opacity:.8;line-height:1.6;">
            <p>Le coût affiché est une estimation à partir des tarifs publics ; la facture exacte est dans la console Anthropic (fixez-y aussi une limite de dépense).</p>
            <p>Les photos sont envoyées à l’IA uniquement quand le vendeur touche le bouton, réduites, et ne sont pas conservées ici.</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
