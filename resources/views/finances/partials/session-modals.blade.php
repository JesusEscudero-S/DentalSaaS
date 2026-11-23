{{-- Modales de sesión de caja: corte (RF-08) y ajuste (RF-09). --}}
@php
    $closeErrors = $errors->closeSession->any();
    $closeForm = [
        'action' => $closeErrors && old('_session_id') ? route('finances.cash.close', old('_session_id')) : '',
        'id' => $closeErrors ? old('_session_id', '') : '',
        'label' => $closeErrors ? old('_label', '') : '',
        'expected' => $closeErrors ? old('_expected', '0') : '0',
        'counted' => $closeErrors ? old('counted_cash', '') : '',
    ];

    $adjustmentErrors = $errors->createAdjustment->any();
    $adjustmentAction = $adjustmentErrors && old('_session_id') ? route('finances.cash.adjustments.store', old('_session_id')) : '';

    $textarea = 'w-full rounded-lg border border-slate-200 bg-surface px-[13px] py-2 text-body text-slate-900 placeholder:text-slate-400 transition-colors hover:border-slate-300 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/15 aria-[invalid=true]:border-danger-500';
@endphp

<x-ui.modal name="close-session" title="Corte de caja" :show="$closeErrors" width="max-w-[480px]">
    <x-slot:sub><span x-text="payload.label ?? @js($closeForm['label'])"></span></x-slot:sub>

    <form method="POST" :action="action" class="flex flex-col gap-5" novalidate
        x-data="{
            ...@js($closeForm),
            cents(value) { const n = parseFloat(String(value ?? '').replace(/[\s$,]/g, '')); return isNaN(n) ? null : Math.round(n * 100) },
            money(cents) { return (cents < 0 ? '−' : '') + '$' + (Math.abs(cents) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
            get difference() { const counted = this.cents(this.counted); return counted === null ? null : counted - this.cents(this.expected) },
        }"
        x-on:open-modal.window="if ($event.detail.name === 'close-session') { action = $event.detail.action; id = $event.detail.id; label = $event.detail.label; expected = $event.detail.expected; counted = '' }">
        @csrf
        <input type="hidden" name="_session_id" x-model="id">
        <input type="hidden" name="_label" x-model="label">
        <input type="hidden" name="_expected" x-model="expected">

        <div class="rounded-lg bg-slate-50 px-4 py-3">
            <p class="text-overline uppercase text-slate-400">Efectivo esperado</p>
            <p class="mt-0.5 font-mono text-[22px] font-bold leading-8 text-slate-900" x-text="money(cents(expected))"></p>
            <p class="text-xs text-slate-500">Apertura + ingresos − egresos en efectivo de la sesión, sin movimientos revertidos.</p>
        </div>

        <x-ui.input name="counted_cash" label="Efectivo contado" required bag="closeSession" inputmode="decimal" placeholder="0.00" autocomplete="off" x-model="counted" />

        <div class="flex items-center justify-between rounded-lg border px-4 py-3"
            :class="difference === null ? 'border-slate-200 text-slate-500' : (difference === 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : (difference > 0 ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-danger-500/30 bg-danger-50 text-danger-700'))">
            <span class="text-body-md font-semibold">Diferencia</span>
            <span class="font-mono text-body font-bold"
                x-text="difference === null ? '—' : (difference === 0 ? 'Sin diferencia' : (difference > 0 ? 'Sobrante ' : 'Faltante ') + money(Math.abs(difference)))"></span>
        </div>

        <div class="flex flex-col gap-2">
            <label for="notes-closeSession" class="text-body-md font-medium text-black">
                Notas<span x-show="difference !== null && difference !== 0" class="text-danger-500">*</span>
            </label>
            <textarea id="notes-closeSession" name="notes" rows="3" maxlength="1000" placeholder="Explica cualquier diferencia"
                @error('notes', 'closeSession') aria-invalid="true" @enderror class="{{ $textarea }}">{{ $closeErrors ? old('notes') : '' }}</textarea>
            @error('notes', 'closeSession')
                <p class="text-xs text-danger-600">{{ $message }}</p>
            @enderror
        </div>

        <x-ui.modal-footer submit="Hacer corte" />
    </form>
</x-ui.modal>

<x-ui.modal name="create-adjustment" title="Registrar ajuste" subtitle="Corrige el resultado de una caja ya cortada" :show="$adjustmentErrors" width="max-w-[460px]">
    <form method="POST" :action="action" class="flex flex-col gap-5" novalidate
        x-data="{ action: @js($adjustmentAction), id: @js($adjustmentErrors ? old('_session_id', '') : '') }"
        x-on:open-modal.window="if ($event.detail.name === 'create-adjustment') { action = $event.detail.action; id = $event.detail.id }">
        @csrf
        <input type="hidden" name="_session_id" x-model="id">
        <div class="flex flex-col gap-4">
            <x-ui.input name="amount" label="Monto" required bag="createAdjustment" inputmode="decimal" placeholder="Ej. 50.00 o -50.00" autocomplete="off"
                hint="Positivo si apareció efectivo; negativo si faltó." />
            <div class="flex flex-col gap-2">
                <label for="reason-createAdjustment" class="text-body-md font-medium text-black">Motivo<span class="text-danger-500">*</span></label>
                <textarea id="reason-createAdjustment" name="reason" rows="3" maxlength="500" placeholder="Ej. Se encontró un billete en el cajón de recetas"
                    @error('reason', 'createAdjustment') aria-invalid="true" @enderror class="{{ $textarea }}">{{ $adjustmentErrors ? old('reason') : '' }}</textarea>
                @error('reason', 'createAdjustment')
                    <p class="text-xs text-danger-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <x-ui.modal-footer submit="Registrar ajuste" />
    </form>
</x-ui.modal>
