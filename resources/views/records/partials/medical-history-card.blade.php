{{-- RF 6.2 Antecedentes clínicos (pestaña Resumen). Pantalla propuesta con el estilo de las tarjetas de Figma. --}}
<x-ui.card class="px-5 py-5">
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-[16px] font-semibold text-slate-900">Antecedentes clínicos</h2>
        <span x-data x-on:click="$dispatch('open-modal', { name: 'medical-history' })">
            <x-ui.button variant="secondary" size="sm">Editar</x-ui.button>
        </span>
    </div>

    <dl class="mt-4 flex flex-col gap-3 text-body-md">
        <div>
            <dt class="text-slate-500">Alergias</dt>
            <dd @class(['mt-0.5 whitespace-pre-line', 'font-semibold text-danger-700' => filled($record->allergies), 'text-slate-400' => blank($record->allergies)])>{{ $record->allergies ?: 'Sin alergias registradas' }}</dd>
        </div>
        <div>
            <dt class="text-slate-500">Antecedentes y enfermedades crónicas</dt>
            <dd @class(['mt-0.5 whitespace-pre-line', 'text-slate-900' => filled($record->medical_history), 'text-slate-400' => blank($record->medical_history)])>{{ $record->medical_history ?: 'Sin antecedentes registrados' }}</dd>
        </div>
    </dl>
</x-ui.card>
