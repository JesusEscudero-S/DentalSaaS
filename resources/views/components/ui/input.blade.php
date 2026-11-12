{{--
    InputText / Field de Figma: label 13px medium + campo con estados
    default, hover, focus (borde primary), error (borde danger) y disabled.
--}}
@props(['name', 'label' => null, 'type' => 'text', 'required' => false, 'bag' => 'default', 'value' => null, 'hint' => null])

@php
    $id = $attributes->get('id', $name.'-'.$bag);
    $error = $errors->getBag($bag)->first($name);
@endphp

<div class="flex w-full flex-col gap-2">
    @if ($label)
        <label for="{{ $id }}" class="text-body-md font-medium text-black">
            {{ $label }}@if ($required)<span class="text-danger-500">*</span>@endif
        </label>
    @endif

    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}"
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('id')->merge(['class' => 'w-full rounded-lg border border-slate-200 bg-surface px-[13px] py-2 text-body text-slate-900 placeholder:text-slate-400 transition-colors hover:border-slate-300 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/15 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-50 disabled:text-slate-400 aria-[invalid=true]:border-danger-500 aria-[invalid=true]:focus:ring-danger-500/15']) }}>

    @if ($error)
        <p id="{{ $id }}-error" class="text-xs text-danger-600">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
