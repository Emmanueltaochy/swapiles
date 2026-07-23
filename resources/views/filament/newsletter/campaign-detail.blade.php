@php
    $campaign = $getRecord();
    $num = fn ($v) => number_format((float) $v, 0, ',', ' ');

    $opensByHour = array_fill(0, 24, 0);
    $clicksByHour = array_fill(0, 24, 0);
    $topLinks = collect();
    $hasData = false;

    try {
        $rows = \Illuminate\Support\Facades\DB::table('newsletter_events')
            ->where('campaign_id', $campaign->id)
            ->selectRaw('HOUR(created_at) as h, type, COUNT(*) as c')
            ->groupBy('h', 'type')
            ->get();

        foreach ($rows as $r) {
            $hasData = true;
            if ($r->type === 'open') {
                $opensByHour[(int) $r->h] = (int) $r->c;
            } elseif ($r->type === 'click') {
                $clicksByHour[(int) $r->h] = (int) $r->c;
            }
        }

        $topLinks = \Illuminate\Support\Facades\DB::table('newsletter_events')
            ->where('campaign_id', $campaign->id)
            ->where('type', 'click')
            ->whereNotNull('url')
            ->selectRaw('url, COUNT(*) as c')
            ->groupBy('url')
            ->orderByDesc('c')
            ->limit(10)
            ->get();
    } catch (\Throwable $e) {
        report($e);
    }

    $hourLabels = array_map(fn ($h) => str_pad((string) $h, 2, '0', STR_PAD_LEFT) . 'h', range(0, 23));
    $linkMax = max(1, ...($topLinks->count() ? $topLinks->pluck('c')->all() : [1]));
@endphp

@if(! $hasData)
    <p style="opacity:.6;font-size:.9rem;">
        ⏳ Aucune ouverture ni clic enregistré pour l'instant. Les données arrivent dès que les destinataires ouvrent l'e-mail.
        <br><span style="font-size:.8rem;">Note : certains clients mail (ex. Apple Mail) préchargent les images, ce qui peut gonfler légèrement les ouvertures.</span>
    </p>
@else
    <div style="font-size:.9rem;font-weight:700;opacity:.75;margin-bottom:.4rem;">Ouvertures & clics par heure</div>
    <div style="display:flex;flex-wrap:wrap;gap:1rem;font-size:.8rem;font-weight:600;margin-bottom:.4rem;">
        <span style="display:inline-flex;align-items:center;gap:.4rem;opacity:.8;"><i style="width:.7rem;height:.7rem;border-radius:.2rem;background:#0d9488;display:inline-block;"></i> Ouvertures</span>
        <span style="display:inline-flex;align-items:center;gap:.4rem;opacity:.8;"><i style="width:.7rem;height:.7rem;border-radius:.2rem;background:#f59e0b;display:inline-block;"></i> Clics</span>
    </div>
    <div style="color:inherit;">
        {!! \App\Support\Charts::line($hourLabels, [
            ['name' => 'Ouvertures', 'color' => '#0d9488', 'data' => array_values($opensByHour)],
            ['name' => 'Clics', 'color' => '#f59e0b', 'data' => array_values($clicksByHour)],
        ], 200) !!}
    </div>

    <div style="font-size:.9rem;font-weight:700;opacity:.75;margin:1.25rem 0 .5rem;">🔗 Liens les plus cliqués</div>
    @forelse($topLinks as $link)
        <div style="display:flex;align-items:center;gap:.6rem;margin:.35rem 0;font-size:.82rem;">
            <div style="width:45%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600;" title="{{ $link->url }}">{{ $link->url }}</div>
            <div style="flex:1;height:.6rem;border-radius:1rem;background:rgba(148,163,184,.18);overflow:hidden;">
                <div style="height:100%;border-radius:1rem;width:{{ round($link->c / $linkMax * 100) }}%;background:#f59e0b;"></div>
            </div>
            <div style="width:60px;text-align:right;font-weight:800;opacity:.75;">{{ $num($link->c) }}</div>
        </div>
    @empty
        <p style="opacity:.6;font-size:.85rem;">Aucun clic sur un lien pour l'instant.</p>
    @endforelse
@endif

{{-- Détail par destinataire (qui a ouvert / cliqué) --}}
@php
    $recipients = \App\Models\NewsletterRecipient::where('campaign_id', $campaign->id)
        ->orderByDesc('click_count')
        ->orderByDesc('open_count')
        ->orderBy('email')
        ->limit(1000)
        ->get();
@endphp
<div style="font-size:.9rem;font-weight:700;opacity:.75;margin:1.5rem 0 .5rem;">👤 Détail par destinataire <small style="opacity:.6;">({{ $num($recipients->count()) }})</small></div>
<div style="max-height:420px;overflow-y:auto;border:1px solid rgba(148,163,184,.2);border-radius:.6rem;">
    <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
        <thead>
            <tr style="position:sticky;top:0;background:var(--fi-color-gray-50,#f9fafb);text-align:left;">
                <th style="padding:.5rem .7rem;font-weight:800;">E-mail</th>
                <th style="padding:.5rem .7rem;font-weight:800;">Ouvert</th>
                <th style="padding:.5rem .7rem;font-weight:800;">Cliqué</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recipients as $rcp)
                <tr style="border-top:1px solid rgba(148,163,184,.14);">
                    <td style="padding:.45rem .7rem;font-weight:600;">{{ $rcp->email }}</td>
                    <td style="padding:.45rem .7rem;">
                        @if($rcp->opened_at)
                            <span style="color:#0d9488;font-weight:700;">✓ {{ optional($rcp->opened_at)->format('d/m H:i') }}</span>
                            @if($rcp->open_count > 1)<span style="opacity:.5;"> ×{{ $rcp->open_count }}</span>@endif
                        @else
                            <span style="opacity:.4;">—</span>
                        @endif
                    </td>
                    <td style="padding:.45rem .7rem;">
                        @if($rcp->first_clicked_at)
                            <span style="color:#f59e0b;font-weight:700;">✓ {{ optional($rcp->first_clicked_at)->format('d/m H:i') }}</span>
                            @if($rcp->click_count > 1)<span style="opacity:.5;"> ×{{ $rcp->click_count }}</span>@endif
                        @else
                            <span style="opacity:.4;">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" style="padding:1rem;text-align:center;opacity:.5;">Aucun destinataire enregistré.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
