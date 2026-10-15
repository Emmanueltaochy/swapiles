<x-filament-widgets::widget>
    <x-filament::section heading="Top parrains" description="Les membres qui font le plus grandir Swap’Îles.">
        @if($top->isEmpty())
            <p style="font-size:14px;opacity:.7">Aucun parrainage pour l’instant.</p>
        @else
            <table style="width:100%;border-collapse:collapse;font-size:14px">
                <thead><tr style="text-align:left;font-size:12px;opacity:.7;text-transform:uppercase">
                    <th style="padding:6px">Parrain</th><th style="padding:6px;text-align:right">Filleuls</th><th style="padding:6px;text-align:right">Validés</th><th style="padding:6px;text-align:right">Gagné</th>
                </tr></thead>
                <tbody>
                    @foreach($top as $l)
                        <tr style="border-top:1px solid rgba(127,127,127,.15)">
                            <td style="padding:8px 6px">
                                @if($l->parrain)
                                    <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $l->parrain_id]) }}" style="font-weight:600;color:#0d9488">{{ $l->parrain->name }}</a>
                                    @if($l->parrain->a_mettre_en_avant_at) <span style="margin-left:6px;border-radius:999px;background:#fef3c7;color:#92400e;padding:2px 8px;font-size:12px;font-weight:700">⭐ à mettre en avant</span>@endif
                                    @if($l->parrain->ambassadeur_at) <span style="margin-left:6px;border-radius:999px;background:#d1fae5;color:#065f46;padding:2px 8px;font-size:12px;font-weight:700">💚 Ambassadeur</span>@endif
                                @else — @endif
                            </td>
                            <td style="padding:8px 6px;text-align:right">{{ $l->filleuls }}</td>
                            <td style="padding:8px 6px;text-align:right;font-weight:700">{{ $l->valides }}</td>
                            <td style="padding:8px 6px;text-align:right">{{ number_format(((int) $l->gagne) / 100, 2, ',', ' ') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
