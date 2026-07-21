@if ($paginator->hasPages())
    <nav class="flex items-center justify-center mt-14 mb-4" role="navigation">

        <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-full px-3 py-2 shadow-sm">

            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="w-10 h-10 flex items-center justify-center rounded-full text-gray-300">
                    ←
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}"
                   class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 transition text-gray-700">
                    ←
                </a>
            @endif

            {{-- Pages --}}
            @foreach ($elements as $element)

                @if (is_string($element))
                    <span class="px-2 text-gray-400 text-sm">
                        {{ $element }}
                    </span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)

                        @if ($page == $paginator->currentPage())
                            <span class="min-w-[42px] h-10 px-3 flex items-center justify-center rounded-full bg-[#0f766e] text-white font-semibold text-sm">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}"
                               class="min-w-[42px] h-10 px-3 flex items-center justify-center rounded-full text-gray-700 hover:bg-gray-100 transition text-sm">
                                {{ $page }}
                            </a>
                        @endif

                    @endforeach
                @endif

            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}"
                   class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 transition text-gray-700">
                    →
                </a>
            @else
                <span class="w-10 h-10 flex items-center justify-center rounded-full text-gray-300">
                    →
                </span>
            @endif

        </div>

    </nav>
@endif
