{{-- Modales de flúor: registrar (RF 6.6) y confirmar eliminación. --}}
<x-ui.modal name="create-fluoride" title="Registrar aplicación de flúor" subtitle="{{ $patient->full_name }} · {{ $patient->folio }}"
    width="max-w-[440px]" :show="$errors->fluoride->any()">
    <form method="POST" action="{{ route('records.fluoride.store', $patient) }}" class="flex flex-col gap-5" novalidate>
        @csrf
        <div class="flex flex-col gap-4">
            <x-ui.input name="applied_on" type="date" label="Fecha de aplicación" required bag="fluoride"
                :value="today()->toDateString()" :max="today()->toDateString()"
                hint="El año de control se toma de esta fecha." />
            <x-ui.input name="notes" label="Observaciones" bag="fluoride" maxlength="255" placeholder="Ej. Barniz de flúor al 5%" />
        </div>
        <x-ui.modal-footer />
    </form>
</x-ui.modal>

<x-ui.modal name="delete-fluoride" title="¿Eliminar aplicación?" width="max-w-[440px]">
    <p class="text-body text-slate-600">
        Se quitará la aplicación del <span class="font-semibold text-slate-900" x-text="payload.label"></span> del control de flúor.
    </p>
    <form method="POST" :action="payload.action">
        @csrf
        @method('DELETE')
        <x-ui.modal-footer type="danger" submit="Eliminar" />
    </form>
</x-ui.modal>
