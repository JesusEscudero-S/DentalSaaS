{{--
    Overlay + Modal de Figma (Scrim clic para cerrar, Cabecera con ButtonClose, Cuerpo, Acciones).
    Abrir:  $dispatch('open-modal', { name: 'x', ...datos })   Cerrar: $dispatch('close-modal', 'x')
    Los datos extra llegan al slot como `payload` (ver x-on:open-modal).
--}}
@props(['name', 'title' => null, 'subtitle' => null, 'show' => false, 'width' => 'max-w-[560px]'])

<div x-data="{ open: @js((bool) $show), payload: {} }"
    x-on:open-modal.window="if ($event.detail.name === '{{ $name }}') { payload = $event.detail; open = true; $nextTick(() => $refs.panel.querySelector('input:not([type=hidden]),select,textarea,button[data-autofocus]')?.focus()) }"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open" x-cloak x-transition.opacity.duration.150ms
    class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-10 sm:items-center"
    role="dialog" aria-modal="true" @if ($title) aria-labelledby="{{ $name }}-title" @endif>

    {{-- Scrim (clic para cerrar) --}}
    <div class="fixed inset-0 bg-slate-900/50" x-on:click="open = false"></div>

    <div x-ref="panel"
        {{ $attributes->merge(['class' => "relative flex w-full {$width} flex-col gap-5 rounded-xl bg-white p-6 shadow-[0_12px_32px_rgba(15,23,42,0.12)]"]) }}>
        <div class="flex items-start justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-1">
                <h2 id="{{ $name }}-title" class="text-[18px] font-semibold leading-[26px] text-slate-900">
                    {{ $title }}{{ $heading ?? '' }}
                </h2>
                @if ($subtitle || isset($sub))
                    <p class="text-body-md leading-[18px] text-slate-600">{{ $subtitle }}{{ $sub ?? '' }}</p>
                @endif
            </div>
            <button type="button" x-on:click="open = false" aria-label="Cerrar"
                class="group flex size-8 shrink-0 items-center justify-center rounded hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <x-ui.icon name="close" :size="18" class="group-hover:hidden" />
                <x-ui.icon name="close-hover" :size="18" class="hidden group-hover:block" />
            </button>
        </div>

        {{ $slot }}
    </div>
</div>
