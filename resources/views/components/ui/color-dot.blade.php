{{-- Punto de color del doctor (columna DOCTOR en Figma). --}}
@props(['color' => 'blue'])

@php
    $colors = [
        'blue' => 'bg-primary-600',
        'sky' => 'bg-sky-500',
        'green' => 'bg-emerald-500',
        'violet' => 'bg-violet-500',
        'amber' => 'bg-amber-500',
        'slate' => 'bg-slate-400',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-block size-2 shrink-0 rounded-full '.($colors[$color] ?? $colors['blue'])]) }}></span>
