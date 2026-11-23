{{-- Overlay "Detalle de cita" (Figma). Los datos llegan en "payload" desde la tarjeta
     (Appointment::toDetailArray). Los botones aparecen según lo que permite el estado actual. --}}
<x-ui.modal name="appointment-detail" title="Detalle de cita" subtitle="Agenda · Clínica Centro" width="max-w-[440px]">
    {{-- RN-C5: aviso de retraso --}}
    <p x-show="payload.late" x-cloak class="rounded-lg border border-danger-500/30 bg-danger-50 px-3 py-2 text-body-md text-danger-700">
        Pasaron más de 30 minutos de la hora de la cita y el paciente no ha llegado.
    </p>

    <dl class="grid grid-cols-[100px_1fr] gap-x-4 gap-y-3 text-body-md">
        <dt class="font-semibold text-slate-500">Paciente</dt>
        <dd class="text-slate-900" x-text="payload.patient"></dd>

        <dt class="font-semibold text-slate-500">Motivo</dt>
        <dd class="text-slate-900" x-text="payload.reason"></dd>

        <dt class="font-semibold text-slate-500">Doctor</dt>
        <dd class="text-slate-900" x-text="payload.doctor"></dd>

        <dt class="font-semibold text-slate-500">Fecha</dt>
        <dd class="text-slate-900 first-letter:uppercase" x-text="payload.date"></dd>

        <dt class="font-semibold text-slate-500">Duración</dt>
        <dd class="text-slate-900" x-text="payload.duration"></dd>

        <dt class="font-semibold text-slate-500">Estado</dt>
        <dd class="text-slate-900" x-text="payload.status"></dd>
    </dl>

    {{-- Módulo 6: ir al expediente (con la cita ya relacionada si la consulta empezó o terminó) --}}
    <a :href="payload.urls?.record" class="-mt-2 self-start text-body-md font-semibold text-primary-600 hover:underline">Abrir expediente →</a>

    {{-- Acciones. Cada botón cierra este modal y abre el de su acción con el mismo payload. --}}
    <div class="flex flex-wrap items-center justify-between gap-3"
        x-show="payload.canCancel || payload.canConfirm || payload.canReschedule || payload.canStart || payload.canFinish || payload.canNoShow">
        <div class="flex gap-3">
            <span x-show="payload.canCancel"
                x-on:click="$dispatch('close-modal', 'appointment-detail'); $dispatch('open-modal', { ...payload, name: 'cancel-appointment' })">
                <x-ui.button variant="danger">Cancelar cita</x-ui.button>
            </span>

            {{-- RN-C5: "No asistió" solo con retraso --}}
            <form method="POST" :action="payload.urls?.noShow" x-show="payload.canNoShow">
                @csrf
                @method('PATCH')
                <x-ui.button type="submit" variant="secondary">No asistió</x-ui.button>
            </form>
        </div>

        <div class="flex gap-3">
            {{-- pendiente → confirmada (el paciente avisó que sí viene) --}}
            <form method="POST" :action="payload.urls?.confirm" x-show="payload.canConfirm">
                @csrf
                @method('PATCH')
                <x-ui.button type="submit" variant="secondary">Confirmar</x-ui.button>
            </form>

            <span x-show="payload.canReschedule"
                x-on:click="$dispatch('close-modal', 'appointment-detail'); $dispatch('open-modal', { ...payload, name: 'reschedule-appointment' })">
                <x-ui.button variant="secondary">Reagendar</x-ui.button>
            </span>

            <span x-show="payload.canStart"
                x-on:click="$dispatch('close-modal', 'appointment-detail'); $dispatch('open-modal', { ...payload, name: 'start-appointment' })">
                <x-ui.button>Iniciar</x-ui.button>
            </span>

            <form method="POST" :action="payload.urls?.finish" x-show="payload.canFinish">
                @csrf
                @method('PATCH')
                <x-ui.button type="submit">Finalizar</x-ui.button>
            </form>
        </div>
    </div>
</x-ui.modal>
