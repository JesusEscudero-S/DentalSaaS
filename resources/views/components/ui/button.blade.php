{{--
    Button (Figma: ButtonPrimary / ButtonSecondary / ButtonDanger / ButtonGhost).
    Estados default / hover / pressed / focus / disabled / loading vía clases de Tailwind.
--}}
@props(['variant' => 'primary', 'href' => null, 'type' => 'button', 'loading' => false, 'size' => 'md'])

@php
    $base = 'inline-flex items-center justify-center gap-[7px] rounded-lg font-semibold whitespace-nowrap transition-colors '
        .'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 '
        .'disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50';

    $sizes = [
        'sm' => 'h-8 px-[11px] text-[12.5px]',
        'md' => 'h-9 min-w-[119px] px-4 text-[13px]',
        'lg' => 'h-[38px] px-[15px] text-[13.5px] font-bold',
    ];

    $variants = [
        'primary' => 'bg-primary-600 text-white hover:bg-primary-700 active:bg-primary-800 focus-visible:ring-primary-500/50',
        'secondary' => 'border border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 active:bg-slate-100 focus-visible:ring-primary-500/50',
        'outline' => 'border border-slate-300 bg-white text-slate-900 hover:border-slate-400 hover:bg-slate-50 active:bg-slate-100 focus-visible:ring-primary-500/50',
        'danger' => 'bg-danger-500 text-white hover:bg-danger-600 active:bg-danger-700 focus-visible:ring-danger-500/50',
        'ghost' => 'bg-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900 active:bg-slate-200 focus-visible:ring-primary-500/50',
        'ghost-danger' => 'bg-transparent text-danger-600 hover:bg-danger-50 active:bg-danger-50 focus-visible:ring-danger-500/50',
    ];

    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @disabled($loading) {{ $attributes->merge(['class' => $classes]) }}>
        @if ($loading)
            <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25" />
                <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" />
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif
