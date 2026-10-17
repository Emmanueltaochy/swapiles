<x-filament-panels::page>
    <style>
        .ai-grille{display:grid;gap:1rem;grid-template-columns:repeat(3,minmax(0,1fr))}
        @media(max-width:1024px){.ai-grille{grid-template-columns:1fr}}
        .ai-gros{font-size:2.4rem;font-weight:800;line-height:1;margin-top:.3rem}
        .ai-ligne{display:flex;justify-content:space-between;gap:1rem;padding:.45rem 0;border-bottom:1px solid rgba(148,163,184,.15);font-size:.88rem}
        .ai-ligne:last-child{border-bottom:0}
        .ai-ligne span:first-child{opacity:.65}
        .ai-ligne span:last-child{font-weight:700}
        .ai-note{font-size:.82rem;opacity:.65;line-height:1.5}
    </style>

    @php
        $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
        $cartes = [
            'ios' => ['🍎 iPhone / iPad', '#3b82f6'],
            'android' => ['🤖 Android', '#0d9488'],
            'toutes' => ['📱 Total', '#7c3aed'],
        ];
    @endphp

    <div class="ai-grille">
        @foreach($cartes as $cle => [$titre, $couleur])
            @php $c = $chiffres[$cle]; @endphp
            <x-filament::section>
                <div style="font-weight:700;opacity:.7;">{{ $titre }}</div>
                <div class="ai-gros" style="color:{{ $couleur }};">{{ $n($c['total']) }}</div>
                <div class="ai-note" style="margin-bottom:.6rem;">installations ouvertes au moins une fois</div>
                <div class="ai-ligne"><span>Utilisées ces 30 derniers jours</span><span>{{ $n($c['actives_30j']) }}</span></div>
                <div class="ai-ligne"><span>Utilisées ces 7 derniers jours</span><span>{{ $n($c['actives_7j']) }}</span></div>
                <div class="ai-ligne"><span>Ouvertes aujourd'hui</span><span>{{ $n($c['aujourdhui']) }}</span></div>
                <div class="ai-ligne"><span>Nouvelles cette semaine</span><span>{{ $n($c['nouvelles_7j']) }}</span></div>
                <div class="ai-ligne"><span>Avec les notifications activées</span><span>{{ $n($c['notifications']) }}</span></div>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section>
        <x-slot name="heading">🔗 Lien unique swapiles.com/app</x-slot>
        <x-slot name="description">iPhone → App Store, Android → Google Play, ordinateur → page avec les deux boutons. À mettre en bio Instagram, sur les affiches, dans les messages.</x-slot>
        @php $plat = $clics['par_plateforme']; @endphp
        <div class="ai-ligne"><span>Clics ces 30 derniers jours</span><span>{{ $n($clics['total']) }}</span></div>
        <div class="ai-ligne"><span>🍎 Envoyés vers l’App Store</span><span>{{ $n($plat['ios'] ?? 0) }}</span></div>
        <div class="ai-ligne"><span>🤖 Envoyés vers Google Play</span><span>{{ $n($plat['android'] ?? 0) }}</span></div>
        <div class="ai-ligne"><span>💻 Depuis un ordinateur</span><span>{{ $n($plat['web'] ?? 0) }}</span></div>
        @if(count($clics['par_source']) > 0)
            <div style="margin-top:.6rem;font-weight:700;font-size:.85rem;">D’où viennent les clics</div>
            @foreach($clics['par_source'] as $source => $nb)
                <div class="ai-ligne"><span>{{ $source }}</span><span>{{ $n($nb) }}</span></div>
            @endforeach
        @endif
        <p class="ai-note" style="margin-top:.6rem;">Astuce : ajoutez « ?src=… » pour savoir d’où vient le clic, par exemple <strong>swapiles.com/app?src=insta</strong>, <strong>swapiles.com/app?src=tiktok</strong> ou <strong>swapiles.com/app?src=affiche</strong>.</p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Comment lire ces chiffres</x-slot>
        <div class="ai-note">
            <p>✅ <strong>Installations ouvertes</strong> : chaque appli installée compte dès qu'elle est lancée une fois. Un identifiant anonyme est tiré au hasard sur le téléphone (aucun lien avec le compte). Une appli désinstallée puis réinstallée compte comme une nouvelle installation.</p>
            <p style="margin-top:.5rem;">📅 Comptage en place depuis {{ $debut ? 'le ' . $debut->format('d/m/Y') : 'cette mise à jour' }} : les installations plus anciennes apparaissent au fur et à mesure que leurs propriétaires ouvrent l'appli.</p>
            <p style="margin-top:.5rem;">🔔 <strong>Avec les notifications activées</strong> : téléphones qui ont accepté les notifications. Ce chiffre existe depuis le lancement de l'appli, c'est un bon repère du minimum installé.</p>
            <p style="margin-top:.5rem;">👤 {{ $n($membres) }} membre(s) connecté(s) ont déjà utilisé l'appli.</p>
            <p style="margin-top:.5rem;">⬇️ Les <strong>téléchargements</strong> (y compris ceux jamais ouverts) ne sont connus que d'Apple et Google :
                <a href="https://appstoreconnect.apple.com/analytics" target="_blank" rel="noopener" style="color:#0d9488;font-weight:700;">App Store Connect → Analyse</a> ·
                <a href="https://play.google.com/console" target="_blank" rel="noopener" style="color:#0d9488;font-weight:700;">Play Console → Statistiques</a>.
            </p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
