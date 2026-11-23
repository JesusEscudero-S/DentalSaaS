{{-- Modal "Antecedentes clínicos" (RF 6.2). Los dos campos pueden quedar vacíos. --}}
<x-ui.modal name="medical-history" title="Antecedentes clínicos" subtitle="{{ $patient->full_name }} · {{ $patient->folio }}"
    :show="$errors->medicalHistory->any()">
    <form method="POST" action="{{ route('records.medical-history.update', $patient) }}" class="flex flex-col gap-5" novalidate>
        @csrf
        @method('PUT')

        <div class="flex flex-col gap-4">
            <x-ui.textarea name="allergies" label="Alergias" bag="medicalHistory" rows="2" maxlength="2000"
                :value="$record->allergies" placeholder="Ej. Penicilina, látex"
                hint="Si hay algo escrito aquí, el expediente mostrará una alerta roja." />
            <x-ui.textarea name="medical_history" label="Antecedentes y enfermedades crónicas" bag="medicalHistory" rows="4" maxlength="5000"
                :value="$record->medical_history" placeholder="Ej. Diabetes tipo 2 controlada, hipertensión" />
        </div>

        <x-ui.modal-footer />
    </form>
</x-ui.modal>
