{{--
    Modales de Movimientos: registrar ingreso/egreso, detalle y revertir.
    Variables: $categories, $branches, $openSessions
--}}
@php
    use App\Enums\PaymentMethod;

    $activeBranches = $branches->where('status', 'active');
    $mainBranchId = ($activeBranches->firstWhere('is_main', true) ?? $activeBranches->first())?->id;
    $categoriesByDirection = $categories->filter->isActive()
        ->groupBy(fn ($c) => $c->direction->value)
        ->map(fn ($group) => $group->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values());
    $sessions = $openSessions->map(fn ($s) => [
        'id' => $s->id,
        'branch_id' => $s->register->branch_id,
        'label' => $s->register->name.' · abierta '.$s->opened_at->format('d/m H:i').' por '.($s->openedBy?->full_name ?? '—'),
    ])->values();
    $createForm = [
        'direction' => old('direction', 'ingreso'),
        'category_id' => old('category_id', ''),
        'method' => old('method', ''),
        'branch_id' => old('branch_id', $mainBranchId),
        'cash_session_id' => old('cash_session_id', ''),
    ];

    $reverseErrors = $errors->reverseMovement->any();
    $reverseAction = $reverseErrors && old('_movement_id') ? route('finances.movements.reverse', old('_movement_id')) : '';
    $textarea = 'w-full rounded-lg border border-slate-200 bg-surface px-[13px] py-2 text-body text-slate-900 placeholder:text-slate-400 transition-colors hover:border-slate-300 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/15 aria-[invalid=true]:border-danger-500';
@endphp

{{-- Registrar ingreso / egreso (RF-01, RF-02) --}}
<x-ui.modal name="create-movement" subtitle="Los movimientos no se editan: para corregir uno, revíertelo." :show="$errors->createMovement->any()">
    <x-slot:heading><span x-text="(payload.direction || @js($createForm['direction'])) === 'egreso' ? 'Registrar egreso' : 'Registrar ingreso'"></span></x-slot:heading>

    <form method="POST" action="{{ route('finances.movements.store') }}" enctype="multipart/form-data" class="flex flex-col gap-5" novalidate
        x-data="{
            ...@js($createForm),
            categories: @js($categoriesByDirection),
            sessions: @js($sessions),
            get directionCategories() { return this.categories[this.direction] || [] },
            get branchSessions() { return this.sessions.filter(s => s.branch_id === this.branch_id) },
            pickSession() { this.cash_session_id = (this.branchSessions[0] || {}).id || '' },
        }"
        x-init="if (! cash_session_id) pickSession()"
        x-on:open-modal.window="if ($event.detail.name === 'create-movement') { direction = $event.detail.direction; category_id = ''; pickSession() }">
        @csrf
        <input type="hidden" name="direction" :value="direction">

        <div class="flex flex-col gap-4">
            <div class="grid grid-cols-2 gap-4">
                <x-ui.input name="occurred_on" type="date" label="Fecha" required bag="createMovement"
                    :value="today()->toDateString()" :min="today()->subYear()->toDateString()" :max="today()->toDateString()" />
                <x-ui.input name="amount" label="Monto" required bag="createMovement" inputmode="decimal" placeholder="0.00" autocomplete="off" />
            </div>

            <x-ui.input name="concept" label="Concepto" required bag="createMovement" maxlength="300" placeholder="Ej. Compra de resinas" />

            <x-ui.select name="category_id" label="Categoría" required bag="createMovement" placeholder="Selecciona una categoría" x-model="category_id">
                <template x-for="c in directionCategories" :key="c.id">
                    <option :value="c.id" x-text="c.name" :selected="c.id === category_id"></option>
                </template>
            </x-ui.select>

            <div class="grid grid-cols-2 gap-4">
                <x-ui.select name="method" label="Método de pago" required bag="createMovement" placeholder="Selecciona"
                    :options="PaymentMethod::options()" :value="$createForm['method']" x-model="method" />
                <x-ui.select name="branch_id" label="Sucursal" required bag="createMovement"
                    :options="$activeBranches->pluck('name', 'id')->all()" :value="$createForm['branch_id']"
                    x-model="branch_id" x-on:change="pickSession()" />
            </div>

            {{-- RF-02: el efectivo entra o sale de una caja abierta de la sucursal --}}
            <div x-show="method === 'efectivo'" x-cloak class="flex flex-col gap-2">
                <x-ui.select name="cash_session_id" label="Caja" required bag="createMovement" placeholder="Selecciona la caja"
                    x-model="cash_session_id" x-bind:disabled="method !== 'efectivo' || branchSessions.length === 0">
                    <template x-for="s in branchSessions" :key="s.id">
                        <option :value="s.id" x-text="s.label" :selected="s.id === cash_session_id"></option>
                    </template>
                </x-ui.select>
                <p x-show="branchSessions.length === 0" class="text-xs font-semibold text-amber-700">
                    Abre una caja en esta sucursal para registrar movimientos en efectivo.
                    <a href="{{ route('finances.cash.index') }}" class="text-primary-600 underline">Ir a Caja</a>
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <label for="receipt-createMovement" class="text-body-md font-medium text-black">
                    Comprobante <span class="font-normal text-slate-400">(opcional)</span>
                </label>
                <input id="receipt-createMovement" type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf"
                    @error('receipt', 'createMovement') aria-invalid="true" @enderror
                    class="block w-full rounded-lg border border-slate-200 text-body-md text-slate-600 file:mr-3 file:h-9 file:border-0 file:border-r file:border-slate-200 file:bg-slate-50 file:px-3 file:text-body-md file:font-semibold file:text-slate-700 hover:file:bg-slate-100 aria-[invalid=true]:border-danger-500">
                @error('receipt', 'createMovement')
                    <p class="text-xs text-danger-600">{{ $message }}</p>
                @else
                    <p class="text-xs text-slate-500">JPG, PNG o PDF de hasta 5 MB.</p>
                @enderror
            </div>
        </div>

        <x-ui.modal-footer submit="Registrar" />
    </form>
</x-ui.modal>

{{-- Detalle --}}
<x-ui.modal name="show-movement" title="Detalle del movimiento" width="max-w-[480px]">
    <x-slot:sub><span x-text="payload.movement?.date"></span></x-slot:sub>

    <dl class="grid grid-cols-[130px_1fr] gap-x-4 gap-y-2.5 text-body-md">
        <dt class="text-slate-500">Tipo</dt>
        <dd class="text-slate-900" x-text="payload.movement?.direction"></dd>
        <dt class="text-slate-500">Monto</dt>
        <dd class="font-mono font-bold text-slate-900" x-text="payload.movement?.amount"></dd>
        <dt class="text-slate-500">Concepto</dt>
        <dd class="break-words text-slate-900" x-text="payload.movement?.concept"></dd>
        <dt class="text-slate-500">Categoría</dt>
        <dd class="text-slate-900" x-text="payload.movement?.category"></dd>
        <dt class="text-slate-500">Sucursal</dt>
        <dd class="text-slate-900" x-text="payload.movement?.branch"></dd>
        <dt class="text-slate-500">Método</dt>
        <dd class="text-slate-900" x-text="payload.movement?.method + (payload.movement?.cash_register ? ' · ' + payload.movement.cash_register : '')"></dd>
        <dt class="text-slate-500">Estado</dt>
        <dd class="text-slate-900" x-text="payload.movement?.status"></dd>
        <dt class="text-slate-500">Origen</dt>
        <dd class="text-slate-900" x-text="payload.movement?.from_payment ? 'Pago de paciente (se revierte desde Pagos)' : 'Registro manual'"></dd>
        <dt class="text-slate-500">Registrado por</dt>
        <dd class="text-slate-900" x-text="payload.movement?.created_by"></dd>
        <dt class="text-slate-500">Registrado el</dt>
        <dd class="text-slate-900" x-text="payload.movement?.created_at"></dd>
        <dt class="text-slate-500">Comprobante</dt>
        <dd>
            <a x-show="payload.movement?.receipt_url" :href="payload.movement?.receipt_url" class="font-semibold text-primary-600 hover:underline">Descargar</a>
            <span x-show="! payload.movement?.receipt_url" class="text-slate-400">Sin comprobante</span>
        </dd>
    </dl>

    <div class="flex justify-end">
        <x-ui.button variant="secondary" x-on:click="open = false">Cerrar</x-ui.button>
    </div>
</x-ui.modal>

{{-- Revertir (RF-03) --}}
<x-ui.modal name="reverse-movement" title="¿Revertir movimiento?" width="max-w-[460px]" :show="$reverseErrors">
    <form method="POST" :action="action" class="flex flex-col gap-4" novalidate
        x-data="{ action: @js($reverseAction), id: @js(old('_movement_id', '')), label: @js(old('_label', '')) }"
        x-on:open-modal.window="if ($event.detail.name === 'reverse-movement') { action = $event.detail.action; id = $event.detail.id; label = $event.detail.label }">
        @csrf
        <input type="hidden" name="_movement_id" x-model="id">
        <input type="hidden" name="_label" x-model="label">

        <p class="text-body text-slate-600">
            Se registrará una reversión de <span class="font-semibold text-slate-900" x-text="label"></span>.
            Ninguno de los dos movimientos contará en los totales. Esta acción no se puede deshacer.
        </p>

        <div class="flex flex-col gap-2">
            <label for="reason-reverseMovement" class="text-body-md font-medium text-black">Motivo<span class="text-danger-500">*</span></label>
            <textarea id="reason-reverseMovement" name="reason" rows="3" maxlength="250" placeholder="Ej. Se capturó un monto equivocado"
                @error('reason', 'reverseMovement') aria-invalid="true" @enderror class="{{ $textarea }}">{{ old('reason') }}</textarea>
            @error('reason', 'reverseMovement')
                <p class="text-xs text-danger-600">{{ $message }}</p>
            @enderror
        </div>

        <x-ui.modal-footer type="danger" submit="Revertir" />
    </form>
</x-ui.modal>
