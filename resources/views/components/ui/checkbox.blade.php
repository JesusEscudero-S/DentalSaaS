{{-- Checkbox de Figma (off / on / disabled). --}}
@props(['name', 'label', 'bag' => 'default', 'checked' => false, 'value' => '1'])

@php
    $id = $attributes->get('id', $name.'-'.$bag);
    $error = $errors->getBag($bag)->first($name);
@endphp

<div class="flex flex-col gap-1">
    <label for="{{ $id }}" class="inline-flex cursor-pointer items-start gap-2.5 text-body-md text-slate-900">
        <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked))
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except('id')->merge(['class' => 'mt-0.5 size-4 shrink-0 cursor-pointer rounded border-slate-300 text-primary-600 accent-primary-600 focus:ring-2 focus:ring-primary-600/20 disabled:cursor-not-allowed disabled:opacity-50']) }}>
        <span>{{ $label }}</span>
    </label>
    @if ($error)
        <p class="text-xs text-danger-600">{{ $error }}</p>
    @endif
</div>
