{{--
    Select con buscador (Figma: "Busca un paciente por nombre o folio").
    Se ve como x-ui.input con lupa; al escribir filtra la lista. Lo que se envía es un
    <input type="hidden" name="..."> con el valor elegido, igual que un <select>.
    La lógica (filtrar, teclado, cerrar) está en resources/js/combobox.js → Alpine.data('combobox').

    Uso: <x-ui.combobox name="patient_id" label="Paciente" :options="[id => texto]" bag="..." />
--}}
@props(['name', 'label' => null, 'options' => [], 'placeholder' => null, 'required' => false, 'bag' => 'default', 'value' => null, 'empty' => 'Sin resultados'])

@php
    $id = $attributes->get('id', $name.'-'.$bag);
    $error = $errors->getBag($bag)->first($name);

    // [{ value: 'uuid', label: 'NOMBRE (PAC-0001)' }, ...] para Alpine
    $items = collect($options)->map(fn ($text, $key) => ['value' => (string) $key, 'label' => $text])->values();
    $selected = (string) old($name, $value);
@endphp

<div class="flex w-full flex-col gap-2" x-data="combobox(@js($items), @js($selected))"
    x-on:click.outside="close()" x-on:keydown.escape.stop="close()">
    @if ($label)
        <label for="{{ $id }}" class="text-body-md font-medium text-black">
            {{ $label }}@if ($required)<span class="text-danger-500">*</span>@endif
        </label>
    @endif

    <input type="hidden" name="{{ $name }}" :value="value">

    <div class="relative">
        <x-ui.icon name="search" :size="18" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2" />

        <input id="{{ $id }}" type="text" autocomplete="off" role="combobox"
            aria-controls="{{ $id }}-list" :aria-expanded="open" aria-autocomplete="list"
            placeholder="{{ $placeholder }}" x-model="query"
            x-on:focus="open = true" x-on:click="open = true" x-on:input="search()"
            x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)"
            x-on:keydown.enter.prevent="pick(filtered[active])" x-on:keydown.tab="close()"
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->except('id')->class('w-full rounded-lg border border-slate-200 bg-surface py-2 pl-9 pr-[13px] text-body text-slate-900 placeholder:text-slate-400 transition-colors hover:border-slate-300 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/15 aria-[invalid=true]:border-danger-500') }}>

        {{-- Lista de opciones --}}
        <ul id="{{ $id }}-list" role="listbox" x-show="open" x-cloak x-ref="list"
            class="absolute inset-x-0 top-full z-10 mt-1 max-h-60 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-[0_12px_32px_rgba(15,23,42,0.12)]">
            <template x-for="(item, index) in filtered" :key="item.value">
                <li role="option" :aria-selected="item.value === value" :data-index="index"
                    x-on:mousedown.prevent="pick(item)" x-on:mousemove="active = index"
                    :class="index === active ? 'bg-primary-50 text-primary-600' : 'text-slate-900'"
                    class="cursor-pointer px-3 py-2 text-body" x-text="item.label"></li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-2 text-body text-slate-400">{{ $empty }}</li>
        </ul>
    </div>

    @if ($error)
        <p id="{{ $id }}-error" class="text-xs text-danger-600">{{ $error }}</p>
    @endif
</div>
