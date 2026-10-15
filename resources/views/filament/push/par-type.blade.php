<x-filament-widgets::widget>
    <x-filament::section heading="Par type de notification" description="30 derniers jours : lesquelles font revenir les membres.">
        @if(empty($lignes))
            <p class="text-sm text-gray-500">Aucune notification enregistrée pour l’instant. Le journal se remplit à partir d’aujourd’hui.</p>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:14px">
                    <thead>
                        <tr style="text-align:left;color:#6b7280;font-size:12px;text-transform:uppercase">
                            <th style="padding:6px 8px">Type</th>
                            <th style="padding:6px 8px;text-align:right">Parties</th>
                            <th style="padding:6px 8px;text-align:right">Ouvertes</th>
                            <th style="padding:6px 8px;width:35%">Taux d’ouverture</th>
                            <th style="padding:6px 8px;text-align:right">Retenues</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lignes as $l)
                            <tr style="border-top:1px solid rgba(127,127,127,.15)">
                                <td style="padding:8px">{{ $l['libelle'] }}</td>
                                <td style="padding:8px;text-align:right;font-variant-numeric:tabular-nums">{{ $l['parties'] }}</td>
                                <td style="padding:8px;text-align:right;font-variant-numeric:tabular-nums">{{ $l['ouvertes'] }}</td>
                                <td style="padding:8px">
                                    @if($l['taux'] !== null)
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <div style="flex:1;height:8px;border-radius:4px;background:rgba(127,127,127,.15)">
                                                <div style="height:8px;border-radius:4px;background:#0d9488;width:{{ min(100, $l['taux']) }}%"></div>
                                            </div>
                                            <span style="min-width:48px;text-align:right;font-variant-numeric:tabular-nums">{{ str_replace('.', ',', (string) $l['taux']) }} %</span>
                                        </div>
                                    @else
                                        <span style="color:#9ca3af">—</span>
                                    @endif
                                </td>
                                <td style="padding:8px;text-align:right;color:#6b7280;font-variant-numeric:tabular-nums">{{ $l['retenues'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
