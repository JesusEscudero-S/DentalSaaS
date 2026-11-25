{{--
    Módulo 7: "Nuevo tratamiento" y "Modificar tratamiento" (diagrama 1 pasos 4-8 y diagrama 3).
    Los procedimientos son filas dinámicas (Alpine): servicio del catálogo, pieza FDI, costo y observaciones.
    Variables: $patient, $record, $doctors, $catalog, $planDoctors, $plan (null al crear), $initial
--}}
@php
    use App\Support\Ui;

    $bag = 'treatmentPlan';
    $errors_ = $errors->getBag($bag);
    $editing = $plan !== null;
    $rows = old('procedures', $initial['procedures'] ?? [['treatment_id' => '', 'tooth_fdi' => '', 'unit_price' => '', 'notes' => '']]);
    $prices = $catalog->mapWithKeys(fn ($t) => [$t->id => number_format((float) $t->price, 2, '.', '')]);
    $field = 'w-full rounded-lg border border-slate-200 bg-surface px-[11px] py-2 text-body text-slate-900 placeholder:text-slate-400 hover:border-slate-300 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/15';
    $doctorOptions = $planDoctors->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all();
    $defaultDoctor = $initial['doctor_member_id'] ?? (app(\App\Support\CurrentOrganization::class)->member()?->doctorProfile ? app(\App\Support\CurrentOrganization::class)->member()->id : '');
@endphp

<x-records.shell :patient="$patient" :record="$record" :doctors="$doctors" active="tratamientos" :section="$editing ? 'Modificar tratamiento' : 'Nuevo tratamiento'">
    <form method="POST" novalidate
        action="{{ $editing ? route('records.treatments.update', [$patient, $plan->quote]) : route('records.treatments.store', $patient) }}"
        x-data="{
            rows: @js(array_values($rows)),
            prices: @js($prices),
            add() { this.rows.push({ treatment_id: '', tooth_fdi: '', unit_price: '', notes: '' }) },
            remove(i) { this.rows.splice(i, 1); if (! this.rows.length) this.add() },
            pick(row) { if (row.treatment_id && (row.unit_price === '' || row.unit_price === null)) row.unit_price = this.prices[row.treatment_id] ?? '' },
            get total() { return this.rows.reduce((s, r) => s + (parseFloat(r.unit_price) || 0), 0) },
            money(n) { return n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' }) },
        }"
        class="flex flex-col gap-4">
        @csrf
        @if ($editing) @method('PUT') @endif

        @if ($errors_->any())
            <div class="rounded-lg border border-danger-500/40 bg-danger-50 px-4 py-3 text-body-md text-danger-700" role="alert">
                <p class="font-semibold">Revisa los datos del tratamiento</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach (array_unique($errors_->all()) as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($editing && $plan->status->value === 'autorizado')
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-body-md text-amber-800" role="alert">
                Este tratamiento ya está autorizado. Si cambias procedimientos o costos se crea un presupuesto nuevo que reemplaza a {{ $plan->quote->folio }},
                el paciente tendrá que autorizarlo otra vez y se avisará al jefe de odontología.
            </div>
        @endif

        <x-ui.card class="flex flex-col gap-4 px-5 py-5">
            <h2 class="text-[16px] font-semibold text-slate-900">Diagnóstico</h2>
            <div class="grid grid-cols-[1fr_200px] gap-4">
                <x-ui.select name="doctor_member_id" label="Odontólogo responsable" required :bag="$bag" :options="$doctorOptions"
                    placeholder="Selecciona un odontólogo" :value="$defaultDoctor" />
                <x-ui.input name="diagnosed_on" type="date" label="Fecha del diagnóstico" required :bag="$bag"
                    :value="$initial['diagnosed_on'] ?? now()->toDateString()" :max="now()->toDateString()" />
            </div>
            <x-ui.textarea name="diagnosis" label="Diagnóstico dental" required :bag="$bag" rows="3" maxlength="2000"
                placeholder="Ej. Caries profunda en 36 con sintomatología pulpar; gingivitis generalizada." :value="$initial['diagnosis'] ?? null" />
        </x-ui.card>

        <x-ui.card class="flex flex-col gap-3 px-5 py-5">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-[16px] font-semibold text-slate-900">Procedimientos</h2>
                    <p class="mt-1 text-body-md text-slate-500">Servicio del catálogo, pieza (FDI: 11-48, o 51-85 temporal) y costo estimado.</p>
                </div>
                <x-ui.button variant="secondary" size="sm" x-on:click="add()">
                    <x-ui.icon name="add" :size="16" />
                    Agregar procedimiento
                </x-ui.button>
            </div>

            @if ($catalog->isEmpty())
                <p class="rounded-lg bg-amber-50 px-4 py-3 text-body-md text-amber-800">
                    El catálogo de servicios está vacío. Da de alta los servicios en Servicios odontológicos (módulo 5).
                </p>
            @endif

            <table class="w-full table-fixed border-collapse">
                <colgroup><col class="w-[36%]"><col class="w-[12%]"><col class="w-[16%]"><col><col class="w-[44px]"></colgroup>
                <thead>
                    <tr class="border-y border-slate-200 bg-slate-50 text-left">
                        @foreach (['Servicio', 'Pieza', 'Costo', 'Observaciones', ''] as $heading)
                            <th scope="col" class="px-2 py-2 text-[12px] font-semibold uppercase text-slate-400">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, i) in rows" :key="i">
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-2 py-2">
                                <select :name="`procedures[${i}][treatment_id]`" x-model="row.treatment_id" x-on:change="pick(row)" class="{{ $field }}" aria-label="Servicio">
                                    <option value="">Selecciona un servicio</option>
                                    @foreach ($catalog as $service)
                                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-2 py-2">
                                <input type="number" :name="`procedures[${i}][tooth_fdi]`" x-model="row.tooth_fdi" min="11" max="85" placeholder="—"
                                    class="{{ $field }}" aria-label="Pieza dental">
                            </td>
                            <td class="px-2 py-2">
                                <input type="number" step="0.01" min="0" :name="`procedures[${i}][unit_price]`" x-model="row.unit_price" placeholder="0.00"
                                    class="{{ $field }} font-mono" aria-label="Costo">
                            </td>
                            <td class="px-2 py-2">
                                <input type="text" maxlength="120" :name="`procedures[${i}][notes]`" x-model="row.notes" placeholder="Opcional"
                                    class="{{ $field }}" aria-label="Observaciones">
                            </td>
                            <td class="px-1 py-2 text-center">
                                <button type="button" x-on:click="remove(i)" title="Quitar" aria-label="Quitar procedimiento"
                                    class="rounded-md p-1 hover:bg-danger-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500/50">
                                    <x-ui.icon name="action-delete" :size="26" />
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="px-2 pt-3 text-right text-body-md font-semibold text-slate-500">Total estimado</td>
                        <td class="px-2 pt-3 font-mono text-[15px] font-bold text-slate-900" x-text="money(total)"></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </x-ui.card>

        @if ($editing)
            <x-ui.card class="px-5 py-5">
                <x-ui.textarea name="change_reason" label="Motivo del cambio" :bag="$bag" rows="2" maxlength="500"
                    placeholder="Ej. Se agrega resina en 26 después de la radiografía." />
            </x-ui.card>
        @endif

        <div class="flex items-center justify-end gap-3">
            <x-ui.button variant="secondary" :href="$editing ? route('records.treatments.show', [$patient, $plan->quote]) : route('records.treatments.index', $patient)">Cancelar</x-ui.button>
            <x-ui.button type="submit">{{ $editing ? 'Guardar cambios' : 'Guardar como propuesto' }}</x-ui.button>
        </div>
    </form>
</x-records.shell>
