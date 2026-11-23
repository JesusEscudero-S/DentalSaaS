{{-- Tabs de navegación dentro de un módulo. items: [['label', 'href' (null = próximamente), 'active']]. --}}
@props(['items' => []])

<nav {{ $attributes->merge(['class' => 'flex gap-6 border-b border-slate-200']) }} aria-label="Secciones">
    @foreach ($items as $item)
        @php $isActive = $item['active'] ?? false; @endphp
        @if ($item['href'])
            <a href="{{ $item['href'] }}" @if ($isActive) aria-current="page" @endif
                @class([
                    '-mb-px border-b-2 px-1 pb-3 text-body-md font-bold transition-colors focus-visible:text-primary-600 focus-visible:outline-none',
                    'border-primary-600 text-primary-600' => $isActive,
                    'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-900' => ! $isActive,
                ])>{{ $item['label'] }}</a>
        @else
            <span aria-disabled="true" title="Disponible próximamente"
                class="-mb-px cursor-not-allowed border-b-2 border-transparent px-1 pb-3 text-body-md font-bold text-slate-300">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
