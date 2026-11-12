{{--
    Contactos de emergencia (RF 3.5 – 3.7). Pantalla propuesta: no existe en Figma;
    sigue el estilo de la tarjeta "Notas clínicas" del Expediente y del TableRow (acciones en hover).
--}}
<x-ui.card class="px-5 py-5" x-data>
    <div class="flex items-center justify-between gap-4">
        <div>
            <h2 class="text-[16px] font-semibold text-slate-900">Contactos de emergencia</h2>
            <p class="text-[12px] text-slate-500">Personas a quienes llamar en caso necesario</p>
        </div>
        @can('pacientes.editar')
            <x-ui.button x-on:click="$dispatch('open-modal', { name: 'create-guardian' })">
                <x-ui.icon name="add" :size="16" />
                Nuevo contacto
            </x-ui.button>
        @endcan
    </div>

    @if ($patient->guardians->isEmpty())
        <div class="mt-4 rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center">
            <p class="text-body font-semibold text-slate-900">No hay contactos registrados</p>
            <p class="mt-1 text-body-md text-slate-500">Agrega al menos un contacto de emergencia para este paciente.</p>
        </div>
    @else
        <ul class="mt-4 divide-y divide-slate-200 border-y border-slate-200">
            @foreach ($patient->guardians as $guardian)
                <li class="group flex items-center gap-3 px-1 py-3 transition-colors hover:bg-slate-50">
                    <x-ui.avatar :initials="\App\Support\Ui::initials($guardian->full_name)" :color="\App\Support\Ui::colorFor($guardian->id)" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 text-body-md font-semibold text-slate-900">
                            <span class="truncate">{{ $guardian->full_name }}</span>
                            @if ($guardian->is_primary)
                                <span class="rounded-full bg-primary-50 px-2 py-0.5 text-[11px] font-semibold text-primary-600">Principal</span>
                            @endif
                        </p>
                        <p class="text-[12px] text-slate-500">
                            {{ $guardian->relationship?->label() ?? 'Sin parentesco' }} ·
                            <span class="font-mono">{{ \App\Support\Ui::phone($guardian->phone) }}</span>
                            @if ($guardian->email) · {{ $guardian->email }} @endif
                        </p>
                    </div>
                    @can('pacientes.editar')
                        <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
                            <button type="button" title="Editar contacto" aria-label="Editar contacto {{ $guardian->full_name }}"
                                class="shrink-0 rounded-md hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                x-on:click="$dispatch('open-modal', { name: 'edit-guardian', guardian: @js(['full_name' => $guardian->full_name, 'relationship' => $guardian->relationship?->value ?? '', 'phone' => \App\Support\Ui::phone($guardian->phone), 'email' => $guardian->email]), action: @js(route('patients.guardians.update', [$patient, $guardian])) })">
                                <x-ui.icon name="action-edit" :size="30" />
                            </button>
                            <button type="button" title="Borrar contacto" aria-label="Borrar contacto {{ $guardian->full_name }}"
                                class="shrink-0 rounded-md hover:bg-danger-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500/50"
                                x-on:click="$dispatch('open-modal', { name: 'delete-guardian', label: @js($guardian->full_name), action: @js(route('patients.guardians.destroy', [$patient, $guardian])) })">
                                <x-ui.icon name="action-delete" :size="30" />
                            </button>
                        </div>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif
</x-ui.card>
