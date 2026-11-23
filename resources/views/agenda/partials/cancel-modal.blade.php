{{-- Overlay "¿Cancelar la cita?" (Figma). RF 4.4 / RN-C3: se agrega el campo "Motivo de cancelación",
     obligatorio según el PDF (Figma no lo tenía). Se guarda en appointment_status_history.reason. --}}
@php
    $bag = 'cancelAppointment';
    $hasErrors = $errors->getBag($bag)->any();
    $error = $errors->getBag($bag)->first('reason');
@endphp

<x-ui.modal name="cancel-appointment" title="¿Cancelar la cita?" width="max-w-[440px]" :show="$hasErrors">
    <form method="POST" :action="action" class="flex flex-col gap-5" novalidate
        x-data="{ action: @js($hasErrors ? old('_action') : ''), label: @js($hasErrors ? old('_label') : '') }"
        x-on:open-modal.window="if ($event.detail.name === 'cancel-appointment') { action = $event.detail.urls.cancel; label = `${$event.detail.patientName} (${$event.detail.time})` }">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_action" :value="action">
        <input type="hidden" name="_label" :value="label">

        <p class="text-body text-slate-600">
            La cita de <span class="font-semibold text-slate-900" x-text="label"></span> se marcará como cancelada
            y su horario quedará libre en la agenda.
        </p>

        <div class="flex flex-col gap-2">
            <label for="cancel-reason" class="text-body-md font-medium text-black">
                Motivo de cancelación<span class="text-danger-500">*</span>
            </label>
            <textarea id="cancel-reason" name="reason" rows="3" maxlength="500" placeholder="Ej. El paciente avisó que no podrá asistir"
                @if ($error) aria-invalid="true" @endif
                class="w-full rounded-lg border border-slate-200 bg-surface px-[13px] py-2 text-body text-slate-900 placeholder:text-slate-400 hover:border-slate-300 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/15 aria-[invalid=true]:border-danger-500">{{ old('reason') }}</textarea>
            @if ($error)
                <p class="text-xs text-danger-600">{{ $error }}</p>
            @endif
        </div>

        <x-ui.modal-footer type="danger" cancel="Volver" submit="Cancelar cita" />
    </form>
</x-ui.modal>
