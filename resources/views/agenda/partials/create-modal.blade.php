{{-- Overlay "Nueva cita" (Figma). Envía a AppointmentController@store. --}}
@php
    $bag = 'createAppointment';   // mismo error bag que StoreAppointmentRequest

    // Opciones de los selects: [valor => texto que se ve]
    $patientOptions = $patients->mapWithKeys(fn ($p) => [$p->id => "{$p->full_name} ({$p->folio})"])->all();
    $doctorOptions = $doctors->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all();
    $durationOptions = [15 => '15 min', 30 => '30 min', 45 => '45 min', 60 => '60 min', 90 => '90 min', 120 => '120 min'];
@endphp

<x-ui.modal name="create-appointment" title="Nueva cita" subtitle="Agenda · Clínica Centro"
    :show="$errors->createAppointment->any()">
    <form method="POST" action="{{ route('agenda.appointments.store') }}" class="flex flex-col gap-5" novalidate>
        @csrf

        <div class="flex flex-col gap-4">
            {{-- Select con buscador: escribe parte del nombre o del folio --}}
            <x-ui.combobox name="patient_id" label="Paciente" required :bag="$bag"
                :options="$patientOptions" placeholder="Busca un paciente por nombre o folio"
                empty="Ningún paciente activo coincide" />

            <x-ui.select name="doctor_member_id" label="Doctor" required :bag="$bag"
                :options="$doctorOptions" placeholder="Selecciona un doctor" />

            <div class="grid grid-cols-3 gap-4">
                <x-ui.input name="date" type="date" label="Fecha" required :bag="$bag"
                    :value="now()->toDateString()" :min="now()->toDateString()" />
                <x-ui.input name="start_time" type="time" label="Hora de inicio" required :bag="$bag"
                    value="09:00" min="08:00" max="20:00" step="900" />
                <x-ui.select name="duration_minutes" label="Duración" :bag="$bag"
                    :options="$durationOptions" value="30" />
            </div>

            <x-ui.input name="notes" label="Notas" required :bag="$bag" maxlength="500"
                placeholder="Motivo de la consulta (ej. limpieza, dolor de muela)" />
        </div>

        <x-ui.modal-footer />
    </form>
</x-ui.modal>