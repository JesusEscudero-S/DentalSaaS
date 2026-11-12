{{-- Ícono SVG exportado de Figma (public/img/ui). --}}
@props(['name', 'size' => 24])

<img src="{{ asset("img/ui/{$name}.svg") }}" alt="" aria-hidden="true" width="{{ $size }}" height="{{ $size }}"
    {{ $attributes->merge(['class' => 'shrink-0']) }}>
