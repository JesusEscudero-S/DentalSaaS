{{-- Overlay "¿Iniciar la cita?" (Figma). La cita pasa a "En curso". --}}
<x-ui.modal name="start-appointment" title="¿Iniciar la cita?" width="max-w-[440px]">
    <p class="text-body text-slate-600">
        La cita de <span class="font-semibold text-slate-900" x-text="`${payload.patientName} (${payload.time})`"></span> pasará a en curso.
    </p>

    <form method="POST" :action="payload.urls?.start">
        @csrf
        @method('PATCH')
        <x-ui.modal-footer cancel="Volver" submit="Iniciar" />
    </form>
</x-ui.modal>
