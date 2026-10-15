@php $image = $live->articles->first()?->images->sortBy('order')->first()?->url; @endphp
<a href="{{ route('live.show', $live) }}" class="group relative block aspect-[3/4] overflow-hidden rounded-2xl bg-gray-900 shadow-sm">
    @if($image)
        <img src="{{ $image }}" alt="" class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-105">
    @else
        <span class="absolute inset-0 grid place-items-center bg-gradient-to-br from-teal-700 to-gray-900 text-5xl">🎬</span>
    @endif
    <span class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/10 to-black/30"></span>
    <span class="absolute left-2 top-2 rounded-md bg-black/60 px-1.5 py-0.5 text-[11px] font-extrabold text-white">▶ REPLAY</span>
    <span class="absolute inset-x-0 bottom-0 p-2.5">
        <span class="block line-clamp-2 text-sm font-extrabold leading-tight text-white">{{ $live->titre }}</span>
        <span class="mt-1 block truncate text-xs text-white/85">{{ $live->vendeur->name ?? '' }} · {{ $live->articles->count() }} article{{ $live->articles->count() > 1 ? 's' : '' }}</span>
    </span>
</a>
