{{--
    Perfil del paciente = pestaña "Resumen" del Expediente clínico (Figma).
    RF 3.2: perfil arriba y contactos de emergencia abajo. El encabezado y las pestañas
    vienen de <x-records.shell> (módulo 6); las demás pestañas están en resources/views/records.
--}}
@php
    use App\Support\Ui;
    $next = $summary['next_appointment'];
    $treatments = $summary['active_treatments'];
    $starts = $next ? \Illuminate\Support\Carbon::parse($next->starts_at) : null;
    $last = $summary['last_visit'] ? \Illuminate\Support\Carbon::parse($summary['last_visit']) : null;
    $fiscal = $patient->fiscalProfile;
@endphp

<x-records.shell :patient="$patient" :record="$record" :doctors="$doctors" active="resumen">
    {{-- Tarjetas de resumen --}}
    <div class="grid grid-cols-4 gap-4">
        <x-ui.card class="px-5 py-5">
            <p class="text-[12px] text-slate-500">Próxima cita</p>
            @if ($next)
                <p class="mt-1 text-[20px] font-semibold text-slate-900">{{ $starts->translatedFormat('j M, H:i') }}</p>
                <p class="mt-1 text-[12px] text-slate-600">{{ collect([$next->treatment, $next->doctor])->filter()->join(' · ') }}</p>
            @else
                <p class="mt-1 text-[20px] font-semibold text-slate-300">—</p>
                <p class="mt-1 text-[12px] text-slate-500">Sin citas agendadas</p>
            @endif
        </x-ui.card>
        <x-ui.card class="px-5 py-5">
            <p class="text-[12px] text-slate-500">Última visita</p>
            @if ($last)
                <p class="mt-1 text-[20px] font-semibold text-slate-900">{{ Ui::date($last) }}</p>
                <p class="mt-1 text-[12px] text-slate-600">{{ ucfirst($last->diffForHumans()) }}</p>
            @else
                <p class="mt-1 text-[20px] font-semibold text-slate-300">—</p>
                <p class="mt-1 text-[12px] text-slate-500">Sin visitas registradas</p>
            @endif
        </x-ui.card>
        <x-ui.card class="px-5 py-5">
            <p class="text-[12px] text-slate-500">Balance pendiente</p>
            <p @class(['mt-1 text-[20px] font-semibold', 'text-slate-900' => $summary['balance'] > 0, 'text-slate-300' => $summary['balance'] <= 0])>{{ Ui::money($summary['balance']) }}</p>
            <p class="mt-1 text-[12px] text-slate-600">
                {{ $summary['pending_invoices'] }} {{ $summary['pending_invoices'] === 1 ? 'factura por cobrar' : 'facturas por cobrar' }}
            </p>
        </x-ui.card>
        <x-ui.card class="px-5 py-5">
            <p class="text-[12px] text-slate-500">Tratamientos activos</p>
            <p class="mt-1 text-[20px] font-semibold text-slate-900">{{ $treatments->count() }}</p>
            <p class="mt-1 truncate text-[12px] text-slate-600">{{ $treatments->isNotEmpty() ? $treatments->join(' · ') : 'Sin tratamientos en curso' }}</p>
        </x-ui.card>
    </div>

    <div class="mt-4 grid grid-cols-[1fr_380px] items-start gap-4">
        @include('patients.partials.guardians')

        <div class="flex flex-col gap-4">
            {{-- RF 6.2 Antecedentes clínicos (módulo 6). Pantalla propuesta: no existe en Figma. --}}
            @include('records.partials.medical-history-card')

            {{-- Datos del paciente --}}
            <x-ui.card class="px-5 py-5">
                <h2 class="text-[16px] font-semibold text-slate-900">Datos del paciente</h2>
                <dl class="mt-4 grid grid-cols-[130px_1fr] gap-x-3 gap-y-2.5 text-body-md">
                    <dt class="text-slate-500">Nacimiento</dt>
                    <dd class="text-slate-900">{{ $patient->birth_date ? Ui::date($patient->birth_date) : '—' }}</dd>
                    <dt class="text-slate-500">Sexo</dt>
                    <dd class="text-slate-900">{{ $patient->sex->label() }}</dd>
                    <dt class="text-slate-500">Doctor asignado</dt>
                    <dd class="text-slate-900">{{ $patient->assignedDoctor?->displayName() ?? 'Sin asignar' }}</dd>
                    <dt class="text-slate-500">Alta</dt>
                    <dd class="text-slate-900">{{ Ui::date($patient->created_at) }}</dd>
                </dl>

                <h3 class="mt-5 border-t border-slate-200 pt-4 text-body-md font-semibold text-slate-700">Datos fiscales</h3>
                @if ($fiscal)
                    <dl class="mt-3 grid grid-cols-[130px_1fr] gap-x-3 gap-y-2.5 text-body-md">
                        <dt class="text-slate-500">RFC</dt>
                        <dd class="font-mono text-slate-900">{{ $fiscal->rfc }}</dd>
                        <dt class="text-slate-500">Razón social</dt>
                        <dd class="text-slate-900">{{ $fiscal->legal_name }}</dd>
                        <dt class="text-slate-500">Régimen</dt>
                        <dd class="text-slate-900">{{ \App\Enums\TaxRegime::tryFrom($fiscal->tax_regime_code)?->label() ?? $fiscal->tax_regime_code }}</dd>
                        <dt class="text-slate-500">CP fiscal</dt>
                        <dd class="font-mono text-slate-900">{{ $fiscal->fiscal_postal_code }}</dd>
                    </dl>
                @else
                    <p class="mt-2 text-body-md text-slate-500">Sin datos fiscales registrados.</p>
                @endif
            </x-ui.card>
        </div>
    </div>

    @push('modals')
        @include('patients.partials.guardian-modals')
        @include('records.partials.medical-history-modal')
    @endpush
</x-records.shell>
