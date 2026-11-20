{{-- Ícono de una plantilla (parte2.html). Variables: $template, $size (sm | lg), $hoverable (bool) --}}
@php
    $tones = [
        'blue' => 'border-primary-100 bg-primary-50 text-primary-600'.(($hoverable ?? false) ? ' group-hover:border-primary-600 group-hover:bg-primary-600 group-hover:text-white' : ''),
        'violet' => 'border-violet-100 bg-violet-50 text-violet-600'.(($hoverable ?? false) ? ' group-hover:border-violet-600 group-hover:bg-violet-600 group-hover:text-white' : ''),
    ];
    $large = ($size ?? 'sm') === 'lg';
@endphp

<span @class([
    'flex shrink-0 items-center justify-center rounded-lg border transition-colors',
    'size-12' => $large,
    'size-9' => ! $large,
    $tones[$template->color()] ?? $tones['blue'],
]) aria-hidden="true">
    <svg @class(['size-6' => $large, 'size-5' => ! $large]) fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <path d="{{ $template->icon() }}" />
    </svg>
</span>
