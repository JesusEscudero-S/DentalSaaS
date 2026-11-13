{{-- Avatar / AvatarDot de Figma: size sm | md | lg, color blue | sky | green | violet | amber | slate. --}}
@props(['initials', 'color' => 'blue', 'size' => 'md'])

@php
    $colors = [
        'blue' => 'bg-primary-600',
        'sky' => 'bg-sky-500',
        'green' => 'bg-emerald-500',
        'violet' => 'bg-violet-500',
        'amber' => 'bg-amber-500',
        'slate' => 'bg-slate-500',
    ];
    $sizes = [
        'sm' => 'size-8 text-[12px]',
        'md' => 'size-[34px] text-body-md',
        'lg' => 'size-12 text-[16px]',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center rounded-full font-bold text-white '.($colors[$color] ?? $colors['blue']).' '.($sizes[$size] ?? $sizes['md'])]) }}
    aria-hidden="true">{{ $initials }}</span>
