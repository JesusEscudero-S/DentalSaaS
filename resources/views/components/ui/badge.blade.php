{{-- Badge de Figma: color blue | sky | green | violet | amber | slate | red. --}}
@props(['color' => 'slate'])

@php
    $colors = [
        'blue' => 'bg-primary-50 text-primary-700',
        'sky' => 'bg-sky-50 text-sky-700',
        'green' => 'bg-emerald-50 text-emerald-700',
        'violet' => 'bg-violet-50 text-violet-700',
        'amber' => 'bg-amber-50 text-amber-700',
        'slate' => 'bg-slate-100 text-slate-600',
        'red' => 'bg-danger-50 text-danger-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex h-[22px] shrink-0 items-center rounded-full px-[9px] text-caption whitespace-nowrap '.($colors[$color] ?? $colors['slate'])]) }}>{{ $slot }}</span>
