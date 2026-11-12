{{--
    Select de Figma (default / open / disabled). Con label se usa en formularios;
    sin label es el filtro compacto de la barra de la tabla.
--}}
@props(['name', 'label' => null, 'options' => [], 'placeholder' => null, 'required' => false, 'bag' => 'default', 'value' => null, 'compact' => false])

@php
    $id = $attributes->get('id', $name.'-'.$bag);
    $error = $errors->getBag($bag)->first($name);
    $selected = (string) old($name, $value);
@endphp

<div @class(['flex flex-col gap-2', 'w-full' => ! $compact])>
    @if ($label)
        <label for="{{ $id }}" class="text-body-md font-medium text-black">
            {{ $label }}@if ($required)<span class="text-danger-500">*</span>@endif
        </label>
    @endif

    <div class="relative">
        <select id="{{ $id }}" name="{{ $name }}"
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->except('id')->class([
                'w-full appearance-none rounded-lg border bg-surface pr-10 text-slate-900 transition-colors hover:border-slate-400 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/15 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400 aria-[invalid=true]:border-danger-500',
                'h-9 border-slate-300 pl-3 text-[13.5px]' => $compact,
                'border-slate-200 py-2 pl-[13px] text-body hover:border-slate-300' => ! $compact,
            ]) }}>
            @if ($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
        <x-ui.icon name="expand-down" :size="24" class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2" />
    </div>

    @if ($error)
        <p id="{{ $id }}-error" class="text-xs text-danger-600">{{ $error }}</p>
    @endif
</div>
