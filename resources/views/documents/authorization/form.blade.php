{{--
    Documentos › Autorización de Tratamiento (parte4.html).
    Variables: $patient, $doctors, $source, $quotes, $findings, $prefilledActivities, $form
--}}
@php
    use App\Enums\DocumentTemplate;
    use App\Support\Ui;

    $bag = 'treatmentAuthorization';
    $errorBag = $errors->getBag($bag);
    $rows = fn (string $key) => array_values((array) old($key, $form[$key] ?? []));
    $doctorList = $doctors->map(fn ($d) => ['id' => $d->id, 'name' => $d->displayName()])->values();
    $doctorOptions = $doctors->mapWithKeys(fn ($d) => [
        $d->id => $d->displayName().($d->doctorProfile?->professional_license ? ' · Céd. '.$d->doctorProfile->professional_license : ''),
    ])->all();
    $quoteOptions = $quotes->mapWithKeys(fn ($q) => [
        $q->id => $q->folio.' · '.Ui::date($q->quote_date).' · '.Ui::money($q->total).' ('.($q->status === 'aprobado' ? 'Aprobado' : 'Enviado').')',
    ])->all();
    $quoteData = $quotes->mapWithKeys(fn ($q) => [$q->id => [
        'total' => Ui::money($q->total),
        'items' => $q->items->map(fn ($i) => [
            'description' => $i->description_snapshot,
            'tooth' => $i->tooth_fdi,
            'quantity' => rtrim(rtrim((string) $i->quantity, '0'), '.'),
            'total' => Ui::money($i->line_total),
        ])->values(),
    ]]);

    $input = 'w-full rounded-lg border border-slate-200 bg-surface px-2.5 py-1.5 text-body-md text-slate-900 placeholder:text-slate-400 hover:border-slate-300 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/15';
    $invalid = 'border-danger-500';
    $section = 'text-overline uppercase text-slate-400';
    $addButton = 'text-body-md font-semibold text-primary-600 hover:underline focus:outline-none focus-visible:underline';
    $removeButton = 'flex size-8 shrink-0 items-center justify-center rounded-md text-slate-400 hover:bg-danger-50 hover:text-danger-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500/50';
@endphp

<x-layouts.app title="Autorización de Tratamiento" active="documentos"
    :breadcrumbs="['Documentos' => route('documents.index'), 'Generar nuevo' => route('documents.create'), 'Autorización de tratamiento' => null]">
    <div class="flex items-start justify-between gap-6 pb-5">
        <div class="py-[3px]">
            <h1 class="text-h1 text-slate-900">Autorización de Tratamiento Dental</h1>
            <p class="pt-[3px] text-body text-slate-500">Plan de tratamiento, control y registro de actividades clínicas.</p>
        </div>
        <div class="flex shrink-0 items-center gap-2.5 p-2.5">
            <x-ui.button variant="secondary" type="submit" form="authorization-form"
                :formaction="route('documents.authorization.preview')" formtarget="_blank">Vista previa</x-ui.button>
            <x-ui.button size="lg" type="submit" form="authorization-form">Guardar documento</x-ui.button>
        </div>
    </div>

    @if ($source)
        <div class="mb-4 rounded-lg border border-primary-100 bg-primary-50 px-4 py-3 text-body-md text-primary-800">
            <strong>Nueva versión</strong> de {{ $source->display_name }}. Los datos se precargaron del documento anterior; ese documento no se modifica.
        </div>
    @endif
    @if ($errorBag->any())
        <div class="mb-4 rounded-lg border border-danger-500/30 bg-danger-50 px-4 py-3 text-body-md text-danger-700" role="alert">
            Revisa los campos marcados en rojo.
        </div>
    @endif

    {{-- Paciente --}}
    <x-ui.card class="mb-4 flex items-center gap-4 px-5 py-3">
        <x-ui.avatar :initials="$patient->initials" :color="$patient->avatar_color" />
        <div class="min-w-0 flex-1 text-body-md">
            <p class="font-semibold text-slate-900">{{ $patient->full_name }} <span class="font-mono text-[11.5px] text-slate-400">{{ $patient->folio }}</span></p>
            <p class="text-slate-500">
                @if ($patient->age !== null){{ $patient->age }} años · @endif{{ $patient->sex?->label() }} · {{ $patient->formatted_phone }}
            </p>
        </div>
        <a href="{{ route('patients.show', $patient) }}" target="_blank" class="text-body-md font-semibold text-primary-600 hover:underline">Ver en Pacientes</a>
    </x-ui.card>

    <form id="authorization-form" method="POST" action="{{ route('documents.authorization.store') }}" novalidate
        x-data="{
            fluoride: @js($rows('fluoride')),
            diagnoses: @js($rows('diagnoses')),
            activities: @js($rows('activities')),
            doctors: @js($doctorList),
            defaultDoctor: @js($form['doctor_member_id']),
            quotes: @js($quoteData),
            quoteId: @js((string) old('quote_id', $form['quote_id'] ?? '')),
            errors: @js($errorBag->toArray()),
            error(key) { return (this.errors[key] || [])[0] },
            get quote() { return this.quotes[this.quoteId] ?? null },
        }">
        @csrf
        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
        <input type="hidden" name="previous_document_id" value="{{ $source?->id }}">

        <div class="grid grid-cols-3 items-start gap-4">
            {{-- Columna izquierda --}}
            <div class="flex flex-col gap-4">
                <x-ui.card class="p-5">
                    <h2 class="{{ $section }} mb-3">Odontograma</h2>
                    @include('documents.partials.odontogram', ['findings' => $findings])
                </x-ui.card>

                <x-ui.card class="flex flex-col gap-3 p-5">
                    <h2 class="{{ $section }}">Aplicaciones de flúor</h2>
                    <template x-for="(row, i) in fluoride" :key="i">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <input type="number" min="1990" max="{{ now()->year }}" placeholder="Año" aria-label="Año"
                                    :name="`fluoride[${i}][year]`" x-model="row.year" class="{{ $input }} w-20" :class="error(`fluoride.${i}.year`) && '{{ $invalid }}'">
                                <input type="text" maxlength="200" placeholder="Aplicación / detalles" aria-label="Detalles"
                                    :name="`fluoride[${i}][details]`" x-model="row.details" class="{{ $input }}" :class="error(`fluoride.${i}.details`) && '{{ $invalid }}'">
                                <button type="button" class="{{ $removeButton }}" x-on:click="fluoride.splice(i, 1)" aria-label="Quitar fila">✕</button>
                            </div>
                            <p class="text-xs text-danger-600" x-show="error(`fluoride.${i}.year`) || error(`fluoride.${i}.details`)"
                                x-text="error(`fluoride.${i}.year`) || error(`fluoride.${i}.details`)"></p>
                        </div>
                    </template>
                    <p x-show="fluoride.length === 0" class="text-body-md text-slate-400">Sin aplicaciones registradas.</p>
                    <button type="button" class="{{ $addButton }} self-start" x-on:click="fluoride.push({ year: '', details: '' })">+ Agregar fila</button>
                </x-ui.card>

                <x-ui.card class="flex flex-col gap-3 p-5">
                    <h2 class="{{ $section }}">Diagnóstico dental</h2>
                    <template x-for="(row, i) in diagnoses" :key="i">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-start gap-2">
                                <div class="flex flex-1 flex-col gap-1.5">
                                    <input type="date" max="{{ today()->toDateString() }}" aria-label="Fecha del diagnóstico"
                                        :name="`diagnoses[${i}][date]`" x-model="row.date" class="{{ $input }}" :class="error(`diagnoses.${i}.date`) && '{{ $invalid }}'">
                                    <textarea rows="2" maxlength="300" placeholder="Diagnóstico" aria-label="Diagnóstico"
                                        :name="`diagnoses[${i}][text]`" x-model="row.text" class="{{ $input }}" :class="error(`diagnoses.${i}.text`) && '{{ $invalid }}'"></textarea>
                                </div>
                                <button type="button" class="{{ $removeButton }}" x-on:click="diagnoses.splice(i, 1)" aria-label="Quitar diagnóstico">✕</button>
                            </div>
                            <p class="text-xs text-danger-600" x-show="error(`diagnoses.${i}.text`) || error(`diagnoses.${i}.date`)"
                                x-text="error(`diagnoses.${i}.text`) || error(`diagnoses.${i}.date`)"></p>
                        </div>
                    </template>
                    <p x-show="diagnoses.length === 0" class="text-body-md text-slate-400">Sin diagnósticos.</p>
                    <button type="button" class="{{ $addButton }} self-start" x-on:click="diagnoses.push({ date: @js(today()->toDateString()), text: '' })">+ Añadir</button>
                </x-ui.card>
            </div>

            {{-- Columna derecha --}}
            <div class="col-span-2 flex flex-col gap-4">
                <x-ui.card class="flex flex-col gap-3 p-5">
                    <div>
                        <h2 class="{{ $section }}">Desarrollo de tratamiento</h2>
                        <p class="text-xs text-slate-500">
                            Bitácora de actividades realizadas.
                            @if ($prefilledActivities) Se prellenó con los tratamientos en progreso o realizados del paciente. @endif
                        </p>
                    </div>
                    <div class="grid grid-cols-[150px_1fr_220px_32px] gap-2 text-[12px] font-semibold uppercase text-slate-400" x-show="activities.length">
                        <span>Fecha</span><span>Actividades realizadas</span><span>Estomatólogo</span><span></span>
                    </div>
                    <template x-for="(row, i) in activities" :key="i">
                        <div class="flex flex-col gap-1">
                            <div class="grid grid-cols-[150px_1fr_220px_32px] items-center gap-2">
                                <input type="date" max="{{ today()->toDateString() }}" aria-label="Fecha"
                                    :name="`activities[${i}][date]`" x-model="row.date" class="{{ $input }}" :class="error(`activities.${i}.date`) && '{{ $invalid }}'">
                                <input type="text" maxlength="300" placeholder="Actividad realizada" aria-label="Actividad"
                                    :name="`activities[${i}][description]`" x-model="row.description" class="{{ $input }}" :class="error(`activities.${i}.description`) && '{{ $invalid }}'">
                                <select :name="`activities[${i}][doctor_member_id]`" x-model="row.doctor_member_id" aria-label="Estomatólogo" class="{{ $input }}"
                                    :class="error(`activities.${i}.doctor_member_id`) && '{{ $invalid }}'">
                                    <option value="">—</option>
                                    <template x-for="doctor in doctors" :key="doctor.id">
                                        <option :value="doctor.id" x-text="doctor.name" :selected="doctor.id === row.doctor_member_id"></option>
                                    </template>
                                </select>
                                <button type="button" class="{{ $removeButton }}" x-on:click="activities.splice(i, 1)" aria-label="Quitar actividad">✕</button>
                            </div>
                            <p class="text-xs text-danger-600"
                                x-show="error(`activities.${i}.description`) || error(`activities.${i}.date`) || error(`activities.${i}.doctor_member_id`)"
                                x-text="error(`activities.${i}.description`) || error(`activities.${i}.date`) || error(`activities.${i}.doctor_member_id`)"></p>
                        </div>
                    </template>
                    <p x-show="activities.length === 0" class="text-body-md text-slate-400">Sin actividades registradas.</p>
                    <button type="button" class="{{ $addButton }} self-start"
                        x-on:click="activities.push({ date: @js(today()->toDateString()), description: '', doctor_member_id: defaultDoctor })">+ Agregar fila</button>
                </x-ui.card>

                <x-ui.card class="flex flex-col gap-3 p-5">
                    <h2 class="{{ $section }}">Presupuesto autorizado <span class="normal-case tracking-normal text-slate-400">(opcional)</span></h2>
                    @if ($quotes->isEmpty())
                        <p class="text-body-md text-slate-500">El paciente no tiene presupuestos enviados o aprobados.</p>
                    @else
                        <div class="max-w-xl">
                            <x-ui.select name="quote_id" :bag="$bag" :options="$quoteOptions" placeholder="Sin presupuesto" :value="$form['quote_id']"
                                aria-label="Presupuesto" x-model="quoteId" />
                        </div>
                        <table class="w-full table-fixed border-collapse text-body-md" x-show="quote" x-cloak>
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-[12px] font-semibold uppercase text-slate-400">
                                    <th class="py-2">Concepto</th><th class="w-20 py-2">Diente</th><th class="w-16 py-2 text-right">Cant.</th><th class="w-32 py-2 text-right">Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(item, i) in quote?.items ?? []" :key="i">
                                    <tr class="border-b border-slate-100">
                                        <td class="truncate py-2 text-slate-900" x-text="item.description"></td>
                                        <td class="py-2 text-slate-500" x-text="item.tooth ?? '—'"></td>
                                        <td class="py-2 text-right font-mono text-slate-700" x-text="item.quantity"></td>
                                        <td class="py-2 text-right font-mono text-slate-900" x-text="item.total"></td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot>
                                <tr><td colspan="3" class="py-2 text-right font-semibold text-slate-700">Total</td><td class="py-2 text-right font-mono font-bold text-slate-900" x-text="quote?.total"></td></tr>
                            </tfoot>
                        </table>
                    @endif
                </x-ui.card>

                <x-ui.card class="flex flex-col gap-3 p-5">
                    <h2 class="{{ $section }}">Declaración de consentimiento</h2>
                    <p class="rounded-lg bg-slate-50 px-4 py-3 text-body-md leading-relaxed text-slate-700">{{ DocumentTemplate::CONSENT_DECLARATION }}</p>
                    <p class="text-xs text-slate-500">El documento se guarda como “Pendiente de firma”. La firma se registra desde el detalle del documento.</p>
                </x-ui.card>

                <x-ui.card class="flex flex-col gap-3 p-5">
                    <h2 class="{{ $section }}">Doctor responsable</h2>
                    <div class="max-w-md">
                        <x-ui.select name="doctor_member_id" label="Doctor" required :bag="$bag" :options="$doctorOptions" :value="$form['doctor_member_id']"
                            placeholder="Selecciona un doctor" />
                    </div>
                </x-ui.card>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 py-6">
            <x-ui.button variant="ghost" :href="route('documents.create', ['paciente' => $patient->id])">Cancelar</x-ui.button>
            <x-ui.button variant="secondary" type="submit" :formaction="route('documents.authorization.preview')" formtarget="_blank">Vista previa</x-ui.button>
            <x-ui.button size="lg" type="submit">Guardar documento</x-ui.button>
        </div>
    </form>
</x-layouts.app>
