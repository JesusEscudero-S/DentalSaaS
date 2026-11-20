{{--
    Textarea con el mismo estilo que x-ui.input (InputText/Field de Figma): label, error y ayuda.
    Uso: <x-ui.textarea name="objective" label="Nota" required bag="createNote" rows="4" />
--}}
@props(['name', 'label' => null, 'required' => false, 'bag' => 'default', 'value' => null, 'hint' => null, 'rows' => 3])

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

    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('id')->merge(['class' => 'w-full resize-y rounded-lg border border-slate-200 bg-surface px-[13px] py-2 text-body text-slate-900 placeholder:text-slate-400 transition-colors hover:border-slate-300 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/15 disabled:cursor-not-allowed disabled:bg-slate-50 aria-[invalid=true]:border-danger-500']) }}>{{ old($name, $value) }}</textarea>

    @if ($error)
        <p id="{{ $id }}-error" class="text-xs text-danger-600">{{ $error }}</p>
    @elseif ($hint)
        <p class="text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
