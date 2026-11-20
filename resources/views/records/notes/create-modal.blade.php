{{--
    Overlay "Nueva nota clínica" (Figma). Nota simple: tratamiento, pieza y nota.
    Se agregaron doctor responsable, fecha y cita (la BD los pide en la consulta) y el botón
    "Guardar borrador": una nota firmada ya no se puede corregir.
--}}
@php
    $bag = 'createNote';
    $doctorOptions = $doctors->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all();
    $treatmentOptions = $treatments->pluck('name', 'id')->all();
    $appointmentOptions = $appointments->mapWithKeys(fn ($a) => [
        $a->id => \App\Support\Ui::date($a->starts_at).' · '.$a->starts_at->format('H:i').' · '.$a->notes.' ('.$a->doctor->displayName().')',
    ])->all();
@endphp

<x-ui.modal name="create-note" title="Nueva nota clínica" subtitle="{{ $patient->full_name }} · {{ $patient->folio }}"
    :show="$errors->createNote->any() && old('note_type') === 'simple'">
    <form method="POST" action="{{ route('records.notes.store', $patient) }}" class="flex flex-col gap-5" novalidate>
        @csrf
        <input type="hidden" name="note_type" value="simple">

        <div class="flex flex-col gap-4">
            @if ($treatmentOptions)
                <x-ui.select name="treatment_id" label="Tratamiento" :bag="$bag" :options="$treatmentOptions" placeholder="Selecciona un tratamiento" />
            @endif

            <div class="grid grid-cols-2 gap-4">
                <x-ui.select name="doctor_member_id" label="Doctor responsable" required :bag="$bag"
                    :options="$doctorOptions" :value="$defaultDoctorId" placeholder="Selecciona un doctor" />
                <x-ui.input name="occurred_on" type="date" label="Fecha" required :bag="$bag"
                    :value="today()->toDateString()" :max="today()->toDateString()" />
            </div>

            <div class="grid grid-cols-[1fr_120px] gap-4">
                <x-ui.select name="appointment_id" label="Cita relacionada" :bag="$bag" :options="$appointmentOptions"
                    placeholder="{{ $appointmentOptions ? 'Sin cita' : 'No hay citas iniciadas o completadas' }}" />
                <x-ui.input name="tooth_fdi" type="number" label="Pieza dental" :bag="$bag" placeholder="Ej. 26" min="11" max="85" />
            </div>

            <x-ui.textarea name="objective" label="Nota" required :bag="$bag" rows="4" maxlength="5000"
                placeholder="Describe el procedimiento y los hallazgos" />
        </div>

        <div class="flex items-center justify-end gap-3">
            <x-ui.button variant="ghost" x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button variant="secondary" type="submit" name="action" value="draft">Guardar borrador</x-ui.button>
            <x-ui.button type="submit" name="action" value="sign">Firmar nota</x-ui.button>
        </div>
    </form>
</x-ui.modal>
