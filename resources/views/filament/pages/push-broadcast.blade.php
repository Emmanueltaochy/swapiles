<x-filament-panels::page>
    @php $n = fn ($v) => number_format((int) $v, 0, ',', ' '); @endphp

    {{-- L'essentiel : combien de téléphones recevront la notification --}}
    <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));">
        @foreach([
            ['🍎 iPhone / iPad', $ios, $iosPret],
            ['🤖 Android', $android, $androidPret],
            ['📱 Total prêts à recevoir', $total, $iosPret || $androidPret],
        ] as [$libelle, $nombre, $pret])
            <x-filament::section>
                <div style="font-size:.85rem;font-weight:700;opacity:.65;">{{ $libelle }}</div>
                <div style="font-size:2.3rem;font-weight:800;line-height:1.1;margin-top:.3rem;">{{ $n($nombre) }}</div>
                @unless($pret)
                    <div style="margin-top:.35rem;font-size:.75rem;font-weight:700;color:#b45309;">⚠️ envoi pas configuré</div>
                @endunless
            </x-filament::section>
        @endforeach
    </div>
    <p style="font-size:.75rem;opacity:.55;margin-top:-.5rem;">Téléphones qui ont accepté les notifications et ouvert l'appli ces {{ $joursActivite }} derniers jours.</p>

    {{-- Le formulaire --}}
    <x-filament::section>
        <x-slot name="heading">Nouvelle notification</x-slot>

        <form wire:submit="send" style="display:flex;flex-direction:column;gap:1.25rem;">
            {{ $this->form }}

            <div style="display:flex;flex-wrap:wrap;gap:.6rem;">
                <x-filament::button type="submit" color="primary" icon="heroicon-o-bell-alert"
                    wire:confirm="Envoyer cette notification à {{ $n($total) }} téléphone(s) ?">
                    Envoyer à tous
                </x-filament::button>
                <x-filament::button type="button" wire:click="apercu" color="gray" icon="heroicon-o-device-phone-mobile">
                    M’envoyer un aperçu
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
