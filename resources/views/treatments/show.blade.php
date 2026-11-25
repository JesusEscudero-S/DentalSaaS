{{--
    Módulo 7: detalle del tratamiento. Procedimientos por pieza, autorización (diagrama 1),
    sesiones con sus correcciones (diagrama 2), cancelación (diagrama 3), versiones y PDF.
    Variables: $patient, $record, $doctors, $plan, $procedures, $realCost, $consentMethods
--}}
@php
    use App\Support\Ui;

    $status = $plan->status;
    $ptLabels = ['planeado' => ['Pendiente', 'amber'], 'en_progreso' => ['En proceso', 'violet'], 'realizado' => ['Realizado', 'green'], 'cancelado' => ['Cancelado', 'slate']];
    $pending = $procedures->where('status', 'planeado');
    $planDoctors = \App\Services\Treatments\TreatmentPlanService::doctors();
    $doctorOptions = $planDoctors->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all();
@endphp

<x-records.shell :patient="$patient" :record="$record" :doctors="$doctors" active="tratamientos" :section="$plan->quote->folio">
    <div class="flex flex-col gap-4">
        {{-- Encabezado y acciones --}}
        <x-ui.card class="flex flex-wrap items-start justify-between gap-4 px-5 py-5">
            <div class="min-w-0">
                <div class="flex items-center gap-3">
                    <h2 class="font-mono text-[18px] font-semibold text-slate-900">{{ $plan->quote->folio }}</h2>
                    <x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge>
                    @if ($plan->version > 1)
                        <span class="text-[12px] text-slate-400">Versión {{ $plan->version }}</span>
                    @endif
                </div>
                <p class="mt-1 text-body-md text-slate-600">
                    {{ $plan->doctor?->displayName() }} · Diagnóstico del {{ Ui::date($plan->diagnosed_on) }}
                    @if ($plan->authorized_at) · Autorizado el {{ $plan->authorized_at->format('d/m/Y') }} @endif
                    @if ($plan->completed_at) · Concluido el {{ $plan->completed_at->format('d/m/Y') }} @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2" x-data>
                <x-ui.button variant="secondary" :href="route('records.treatments.pdf', [$patient, $plan])">
                    <x-ui.icon name="import" :size="20" />
                    PDF
                </x-ui.button>
                @if ($status->isEditable())
                    <x-ui.button variant="secondary" :href="route('records.treatments.edit', [$patient, $plan])">Modificar</x-ui.button>
                @endif
                @unless ($status->isClosed())
                    <span x-on:click="$dispatch('open-modal', { name: 'cancel-plan' })"><x-ui.button variant="ghost-danger">Cancelar tratamiento</x-ui.button></span>
                @endunless
                @if ($status->value === 'propuesto')
                    <span x-on:click="$dispatch('open-modal', { name: 'authorize-plan' })"><x-ui.button>Registrar autorización</x-ui.button></span>
                @elseif ($status->acceptsSessions())
                    <span x-on:click="$dispatch('open-modal', { name: 'record-session' })"><x-ui.button>Registrar sesión</x-ui.button></span>
                @endif
            </div>
        </x-ui.card>

        {{-- Avisos de estado --}}
        @if ($plan->requires_reauthorization)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-body-md text-amber-800" role="alert">
                Se modificaron procedimientos o costos de un plan autorizado: requiere nueva autorización del paciente.
            </div>
        @elseif ($status->value === 'propuesto')
            <div class="rounded-lg border border-primary-600/20 bg-primary-50 px-4 py-3 text-body-md text-primary-700">
                Imprime la autorización (PDF o módulo Documentos), que el paciente la revise y firme, y regístrala aquí.
                No se pueden registrar sesiones hasta que esté autorizado.
            </div>
        @elseif ($status->value === 'cancelado')
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-body-md text-slate-700">
                <span class="font-semibold">Cancelado el {{ $plan->cancelled_at?->format('d/m/Y') }}.</span> Motivo: {{ $plan->cancel_reason }}
            </div>
        @elseif ($status->value === 'concluido')
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-body-md text-emerald-800">
                Tratamiento concluido (solo lectura). Costo real disponible para Facturación y pagos: <span class="font-mono font-bold">{{ Ui::money($realCost) }}</span>.
            </div>
        @endif

        <div class="grid grid-cols-[1fr_300px] gap-4">
            {{-- Diagnóstico y procedimientos --}}
            <x-ui.card class="overflow-hidden">
                <div class="px-5 pt-5">
                    <p class="text-overline uppercase text-slate-400">Diagnóstico dental</p>
                    <p class="mt-1 whitespace-pre-line text-body text-slate-900">{{ $plan->diagnosis }}</p>
                </div>
                <table class="mt-4 w-full table-fixed border-collapse">
                    <colgroup><col class="w-[8%]"><col><col class="w-[10%]"><col class="w-[18%]"><col class="w-[16%]"></colgroup>
                    <thead>
                        <tr class="border-y border-slate-200 bg-slate-50 text-left">
                            @foreach (['#', 'Procedimiento', 'Pieza', 'Costo', 'Estatus'] as $heading)
                                <th scope="col" class="px-4 py-2 text-[12px] font-semibold uppercase text-slate-400">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($procedures as $pt)
                            @php [$ptLabel, $ptColor] = $ptLabels[$pt->status] ?? [$pt->status, 'slate']; @endphp
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-2.5 text-body text-slate-400">{{ $pt->quoteItem->line_number }}</td>
                                <td class="px-4 py-2.5 text-body text-slate-900">{{ $pt->quoteItem->description_snapshot }}</td>
                                <td class="px-4 py-2.5 font-mono text-body text-slate-700">{{ $pt->tooth_fdi ?? '—' }}</td>
                                <td class="px-4 py-2.5 font-mono text-body text-slate-900">{{ Ui::money($pt->quoteItem->line_total) }}</td>
                                <td class="px-4 py-2.5"><x-ui.badge :color="$ptColor">{{ $ptLabel }}</x-ui.badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.card>

            {{-- Resumen de costos y autorización --}}
            <div class="flex flex-col gap-4">
                <x-ui.card class="flex flex-col gap-3 px-5 py-5">
                    <div class="flex justify-between text-body-md"><span class="text-slate-500">Costo estimado</span><span class="font-mono font-semibold text-slate-900">{{ Ui::money($plan->quote->total) }}</span></div>
                    <div class="flex justify-between text-body-md"><span class="text-slate-500">Realizado (costo real)</span><span class="font-mono font-semibold text-emerald-700">{{ Ui::money($realCost) }}</span></div>
                    <div class="flex justify-between text-body-md"><span class="text-slate-500">Procedimientos</span><span class="font-semibold text-slate-900">{{ $procedures->where('status', 'realizado')->count() }} de {{ $procedures->count() }}</span></div>
                </x-ui.card>
                @if ($plan->consent)
                    <x-ui.card class="px-5 py-5">
                        <p class="text-overline uppercase text-slate-400">Autorización</p>
                        <p class="mt-1 text-body-md text-slate-900">{{ $plan->consent->accepted_by_name }}</p>
                        <p class="text-[12px] text-slate-500">{{ $plan->consent->method->label() }} · {{ $plan->consent->accepted_at->format('d/m/Y H:i') }}</p>
                    </x-ui.card>
                @endif
                @if ($plan->versions->count() > 1)
                    <x-ui.card class="px-5 py-5">
                        <p class="text-overline uppercase text-slate-400">Versiones</p>
                        <ul class="mt-2 flex flex-col gap-2">
                            @foreach ($plan->versions as $version)
                                <li class="text-[12.5px] text-slate-700">
                                    <span class="font-semibold">v{{ $version->version }}</span> · {{ $version->quote->folio }} · {{ Ui::money($version->quote->total) }}
                                    <span class="block text-[11px] text-slate-400">{{ $version->created_at->format('d/m/Y H:i') }} · {{ $version->author?->full_name }}</span>
                                    @if ($version->change_reason)<span class="block text-[11px] text-slate-500">{{ $version->change_reason }}</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif
            </div>
        </div>

        {{-- Sesiones (Desarrollo de tratamiento) --}}
        <x-ui.card class="px-5 py-5">
            <h2 class="text-[16px] font-semibold text-slate-900">Sesiones</h2>
            @forelse ($plan->sessions as $session)
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-body-md font-semibold text-slate-900">{{ Ui::date($session->performed_on) }} · {{ $session->doctor?->displayName() }}</p>
                            <p class="mt-1 whitespace-pre-line text-body text-slate-700">{{ $session->activities }}</p>
                            @if ($session->procedures->isNotEmpty())
                                <p class="mt-1 text-[12px] text-slate-500">Realizado: {{ $session->procedures->map(fn ($p) => $p->quoteItem->description_snapshot.($p->tooth_fdi ? " ({$p->tooth_fdi})" : ''))->join(', ') }}</p>
                            @endif
                        </div>
                        <div x-data>
                            <button type="button" class="whitespace-nowrap rounded-md px-2 py-1 text-[12px] font-semibold text-primary-600 hover:bg-primary-50"
                                x-on:click="$dispatch('open-modal', { name: 'session-note', action: @js(route('records.treatments.sessions.notes.store', [$patient, $plan, $session])) })">
                                Agregar corrección
                            </button>
                        </div>
                    </div>
                    @foreach ($session->notes as $note)
                        <div class="ml-4 mt-2 rounded-md bg-amber-50 px-3 py-2 text-[12.5px] text-amber-900">
                            <span class="font-semibold">Corrección</span> · {{ $note->created_at->format('d/m/Y H:i') }} · {{ $note->author?->full_name }}:
                            {{ $note->note }}
                        </div>
                    @endforeach
                </div>
            @empty
                <p class="mt-2 text-body-md text-slate-500">
                    {{ $status->acceptsSessions() ? 'Aún no hay sesiones. Usa “Registrar sesión” al atender al paciente.' : 'Las sesiones se registran cuando el tratamiento está autorizado.' }}
                </p>
            @endforelse
        </x-ui.card>
    </div>

    @push('modals')
        {{-- Autorización del paciente --}}
        <x-ui.modal name="authorize-plan" title="Registrar autorización" subtitle="El paciente revisó y firmó el plan" :show="$errors->authorizePlan->any()" width="max-w-[480px]">
            <form method="POST" action="{{ route('records.treatments.authorize', [$patient, $plan]) }}" class="flex flex-col gap-4" novalidate>
                @csrf
                @method('PATCH')
                <x-ui.checkbox name="patient_agrees" bag="authorizePlan" label="El paciente (o su tutor) revisó el plan y firmó la autorización." />
                <x-ui.input name="accepted_by_name" label="Nombre de quien autoriza" required bag="authorizePlan" :value="$patient->full_name"
                    maxlength="250" class="uppercase" />
                <x-ui.select name="method" label="Método de firma" required bag="authorizePlan" :options="$consentMethods"
                    :value="\App\Enums\ConsentMethod::ScannedSignature->value" />
                <x-ui.modal-footer submit="Autorizar" />
            </form>
        </x-ui.modal>

        {{-- Sesión --}}
        <x-ui.modal name="record-session" title="Registrar sesión" subtitle="Desarrollo de tratamiento" :show="$errors->treatmentSession->any()">
            <form method="POST" action="{{ route('records.treatments.sessions.store', [$patient, $plan]) }}" class="flex flex-col gap-4" novalidate>
                @csrf
                <div class="grid grid-cols-[170px_1fr] gap-4">
                    <x-ui.input name="performed_on" type="date" label="Fecha" required bag="treatmentSession" :value="now()->toDateString()" :max="now()->toDateString()" />
                    <x-ui.select name="doctor_member_id" label="Odontólogo responsable" required bag="treatmentSession" :options="$doctorOptions" :value="$plan->doctor_member_id" />
                </div>
                <x-ui.textarea name="activities" label="Actividades realizadas" required bag="treatmentSession" rows="3" maxlength="2000"
                    placeholder="Ej. Anestesia local, apertura cameral y conductometría en 36." />
                <fieldset class="flex flex-col gap-2">
                    <legend class="pb-1 text-body-md font-medium text-black">Procedimientos realizados en esta sesión</legend>
                    @forelse ($pending as $pt)
                        <label class="flex items-center gap-2 text-body text-slate-800">
                            <input type="checkbox" name="procedures[]" value="{{ $pt->id }}" @checked(in_array($pt->id, old('procedures', [])))
                                class="size-4 rounded border-slate-300 text-primary-600 focus:ring-primary-600/30">
                            {{ $pt->quoteItem->description_snapshot }} @if ($pt->tooth_fdi)<span class="font-mono text-slate-500">· {{ $pt->tooth_fdi }}</span>@endif
                        </label>
                    @empty
                        <p class="text-body-md text-slate-500">No hay procedimientos pendientes.</p>
                    @endforelse
                    <p class="text-[12px] text-slate-500">Si marcas todos los pendientes, el tratamiento se concluye. Actualiza el odontograma en su pestaña.</p>
                </fieldset>
                <x-ui.modal-footer submit="Guardar sesión" />
            </form>
        </x-ui.modal>

        {{-- Corrección de sesión (RN-T4) --}}
        <x-ui.modal name="session-note" title="Agregar corrección" subtitle="Las sesiones no se borran: se corrigen con una nota" :show="$errors->sessionNote->any()" width="max-w-[480px]">
            <form method="POST" :action="payload.action" class="flex flex-col gap-4" novalidate>
                @csrf
                <x-ui.textarea name="note" label="Corrección" required bag="sessionNote" rows="3" maxlength="1000" />
                <x-ui.modal-footer submit="Agregar" />
            </form>
        </x-ui.modal>

        {{-- Cancelación --}}
        <x-ui.modal name="cancel-plan" title="¿Cancelar tratamiento?" :show="$errors->cancelPlan->any()" width="max-w-[480px]">
            <form method="POST" action="{{ route('records.treatments.cancel', [$patient, $plan]) }}" class="flex flex-col gap-4" novalidate>
                @csrf
                @method('PATCH')
                <p class="text-body text-slate-600">
                    Los procedimientos pendientes se cancelan, el tratamiento queda en solo lectura y se avisa al jefe de odontología.
                    Lo ya realizado se conserva.
                </p>
                <x-ui.textarea name="reason" label="Motivo de la cancelación" required bag="cancelPlan" rows="3" maxlength="1000" />
                <x-ui.modal-footer type="danger" submit="Cancelar tratamiento" cancel="Volver" />
            </form>
        </x-ui.modal>
    @endpush
</x-records.shell>
