{{-- Paginación de Figma: ButtonArrow (34px) + ButtonNumber (32px; activo = primary). --}}
@if ($paginator->hasPages())
    @php
        $arrow = 'flex size-[34px] items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50';
        $number = 'flex size-8 items-center justify-center rounded-lg border px-2 font-mono text-[12.5px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50';
    @endphp
    <nav role="navigation" aria-label="Paginación" class="flex items-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="{{ $arrow }} cursor-not-allowed opacity-50" aria-disabled="true" aria-label="Anterior">
                <x-ui.icon name="expand-right" :size="20" class="rotate-180" />
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $arrow }}" aria-label="Anterior">
                <x-ui.icon name="expand-right" :size="20" class="rotate-180" />
            </a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="{{ $number }} border-transparent text-slate-400">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="{{ $number }} border-primary-600 bg-primary-600 text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="{{ $number }} border-slate-200 bg-white text-slate-600 hover:bg-slate-100" aria-label="Página {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $arrow }}" aria-label="Siguiente">
                <x-ui.icon name="expand-right" :size="20" />
            </a>
        @else
            <span class="{{ $arrow }} cursor-not-allowed opacity-50" aria-disabled="true" aria-label="Siguiente">
                <x-ui.icon name="expand-right" :size="20" />
            </span>
        @endif
    </nav>
@endif
