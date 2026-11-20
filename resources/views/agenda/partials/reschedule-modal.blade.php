{{-- Overlay "Reagendar cita" (RF 4.3). No existe en Figma: usa el mismo estilo que "Nueva cita".
     Se abre desde el detalle con el payload de la cita (precarga doctor, fecha, hora y duración).
     Si la validación falla, la página regresa y el modal se reabre con lo que el usuario escribió (old()). --}}
@php
    $bag = 'rescheduleAppointment';
    $hasErrors = $errors->getBag($bag)->any();

    $doctorOptions = $doctors->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all();
    $durationOptions = [15 => '15 min', 30 => '30 min', 45 => '45 min', 60 => '60 min', 90 => '90 min', 120 => '120 min'];

    // Estado inicial cuando el modal se reabre por un error de validación.
    $initial = $hasErrors ? [
        'form' => [
            'doctor_member_id' => old('doctor_member_id'),
            'date' => old('date'),
            'start_time' => old('start_time'),
            'duration_minutes' => (string) old('duration_minutes'),
        ],
        'action' => old('_action'),
        'patient' => old('_patient'),
    ] : ['form' => (object) [], 'action' => '', 'patient' => ''];
@endphp

<x-ui.modal name="reschedule-appointment" title="Reagendar cita" :show="$hasErrors">
    <x-slot:sub><span x-text="payload.patientName ?? @js($initial['patient'])"></span></x-slot:sub>

    <form method="POST" :action="action" class="flex flex-col gap-5" novalidate
        x-data="{ form: @js($initial['form']), action: @js($initial['action']), patient: @js($initial['patient']) }"
        x-on:open-modal.window="if ($event.detail.name === 'reschedule-appointment') { form = { ...$event.detail.form }; action = $event.detail.urls.reschedule; patient = $event.detail.patientName }">
        @csrf
        @method('PUT')
        {{-- Para poder reabrir el modal si hay errores --}}
        <input type="hidden" name="_action" :value="action">
        <input type="hidden" name="_patient" :value="patient">

        <div class="flex flex-col gap-4">
            <x-ui.select name="doctor_member_id" label="Doctor" required :bag="$bag"
                :options="$doctorOptions" placeholder="Selecciona un doctor" x-model="form.doctor_member_id" />

            <div class="grid grid-cols-3 gap-4">
                <x-ui.input name="date" type="date" label="Fecha" required :bag="$bag"
                    :min="now()->toDateString()" x-model="form.date" />
                <x-ui.input name="start_time" type="time" label="Hora de inicio" required :bag="$bag"
                    min="08:00" max="20:00" step="900" x-model="form.start_time" />
                <x-ui.select name="duration_minutes" label="Duración" :bag="$bag"
                    :options="$durationOptions" x-model="form.duration_minutes" />
            </div>
        </div>

        <x-ui.modal-footer submit="Reagendar" />
    </form>
</x-ui.modal>
