{{--
    Modal "Pieza NN" (RF 6.3): estado y caras afectadas. Pantalla propuesta con componentes de Figma.
    El payload llega desde la casilla del diente: { tooth, condition, surfaces }.
    Si la validación falla, se reabre con old().
--}}
@php
    use App\Enums\ToothCondition;
    use App\Enums\ToothSurface;

    $hasErrors = $errors->tooth->any();
    $initial = $hasErrors
        ? ['tooth' => (int) old('tooth_fdi'), 'condition' => old('condition', 'sano'), 'surfaces' => old('surfaces', [])]
        : ['tooth' => null, 'condition' => 'sano', 'surfaces' => []];

    // Estados que llevan caras (para mostrar u ocultar las casillas con Alpine).
    $withSurfaces = collect(ToothCondition::cases())->filter->usesSurfaces()->map->value->values();
@endphp

<x-ui.modal name="tooth" title="Actualizar pieza" width="max-w-[480px]" :show="$hasErrors">
    {{-- El título vive fuera del <form>: usa el payload del modal (o old() si se reabrió por un error) --}}
    <x-slot:heading><span x-text="' ' + (payload.tooth ?? @js($initial['tooth'] ?? ''))"></span></x-slot:heading>

    <form method="POST" action="{{ route('records.odontogram.store', $patient) }}" class="flex flex-col gap-5" novalidate
        x-data="{ form: @js($initial), withSurfaces: @js($withSurfaces) }"
        x-on:open-modal.window="if ($event.detail.name === 'tooth') form = { tooth: $event.detail.tooth, condition: $event.detail.condition, surfaces: [...$event.detail.surfaces] }">
        @csrf
        <input type="hidden" name="tooth_fdi" :value="form.tooth">

        @if ($errors->tooth->any())
            <p class="rounded-lg bg-danger-50 px-3 py-2 text-body-md text-danger-700">{{ $errors->tooth->first() }}</p>
        @endif

        <fieldset>
            <legend class="text-body-md font-medium text-black">Estado<span class="text-danger-500">*</span></legend>
            <div class="mt-2 grid grid-cols-2 gap-2">
                @foreach (ToothCondition::cases() as $condition)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-body-md transition-colors has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50">
                        <input type="radio" name="condition" value="{{ $condition->value }}" x-model="form.condition" class="accent-primary-600">
                        <span class="size-2.5 rounded-sm border {{ $condition->swatch() }}"></span>
                        {{ $condition->label() }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset x-show="withSurfaces.includes(form.condition)" x-cloak>
            <legend class="text-body-md font-medium text-black">Caras afectadas</legend>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach (ToothSurface::cases() as $surface)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-1.5 text-body-md has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50">
                        {{-- Si no aplica (sano, ausente…) se desactivan y no se envían --}}
                        <input type="checkbox" name="surfaces[]" value="{{ $surface->value }}" x-model="form.surfaces"
                            :disabled="! withSurfaces.includes(form.condition)" class="accent-primary-600">
                        <span class="font-mono font-semibold">{{ $surface->value }}</span> {{ $surface->label() }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <x-ui.modal-footer />
    </form>
</x-ui.modal>
