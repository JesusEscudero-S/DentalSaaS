{{-- Card de Figma: superficie blanca, borde slate-200 y radio 10px. --}}
<div {{ $attributes->merge(['class' => 'rounded-[10px] border border-slate-200 bg-white']) }}>
    {{ $slot }}
</div>
