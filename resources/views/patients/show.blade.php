{{--
    Perfil del paciente. Base: Figma "Expediente clínico" (Shell de Expediente).
    RF 3.2: perfil arriba y contactos de emergencia abajo. Las pestañas clínicas
    (Historial, Notas, Archivos, Odontograma) pertenecen al módulo 6.
--}}
@php
    use App\Support\Ui;
    $next = $summary['next_appointment'];
    $treatments = $summary['active_treatments'];
    $starts = $next ? \Illuminate\Support\Carbon::parse($next->starts_at) : null;
    $last = $summary['last_visit'] ? \Illuminate\Support\Carbon::parse($summary['last_visit']) : null;
    $fiscal = $patient->fiscalProfile;
@endphp

<x-layouts.app :title="$patient->full_name" active="pacientes"
    :breadcrumbs="['Pacientes' => route('patients.index'), $patient->full_name => null]">

    {{-- Cabecera del paciente --}}
    <x-ui.card class="flex items-center justify-between gap-6 px-6 py-5">
        <div class="flex min-w-0 items-center gap-4">
            <x-ui.avatar :initials="$patient->initials" :color="$patient->isActive() ? $patient->avatar_color : 'slate'" size="lg" />
            <div class="min-w-0">
                <div class="flex items-center gap-3">
                    <h1 class="truncate text-[20px] font-semibold leading-7 text-slate-900">{{ $patient->full_name }}</h1>
                    <x-ui.status-badge :status="$patient->status" />
                </div>
                <p class="mt-0.5 text-body-md text-slate-600">
                    {{ $patient->folio }}
                    @if ($patient->age !== null) · {{ $patient->age }} {{ $patient->age === 1 ? 'año' : 'años' }} @endif
                    · {{ $patient->formatted_phone }}
                    @if ($patient->email) · {{ $patient->email }} @endif
                </p>
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-2" x-data>
                @if ($patient->isActive())
                    {{-- Los @js van en una etiqueta HTML normal: dentro de un <x-…> rompen el parser de Blade --}}
                    <span x-on:click="$dispatch('open-modal', { name: 'deactivate-patient', label: @js($patient->full_name.' ('.$patient->folio.')'), action: @js(route('patients.deactivate', $patient)) })">
                        <x-ui.button variant="ghost-danger">Desactivar</x-ui.button>
                    </span>
                @else
                    <form method="POST" action="{{ route('patients.reactivate', $patient) }}">
                        @csrf
                        @method('PATCH')
                        <x-ui.button type="submit" variant="secondary">Reactivar</x-ui.button>
                    </form>
                @endif
                <span x-on:click="$dispatch('open-modal', { name: 'edit-patient', patient: @js($patient->toFormArray()), action: @js(route('patients.update', $patient)) })">
                    <x-ui.button variant="secondary">Editar datos</x-ui.button>
                </span>
        </div>
    </x-ui.card>

    @unless ($patient->isActive())
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-body-md text-amber-800" role="alert">
            Paciente inactivo: no aparece en las búsquedas estándar ni en la Agenda. Su historial se conserva.
        </div>
    @endunless

    {{-- Tabs (Figma: Tab active / inactive) --}}
    <nav class="mt-6 flex gap-1" aria-label="Secciones del paciente">
        <span class="rounded-lg bg-white px-2.5 py-1.5 text-body-md font-semibold text-slate-900 shadow-sm" aria-current="page">Resumen</span>
        @foreach (['Historial', 'Notas', 'Archivos', 'Odontograma'] as $tab)
            <span class="cursor-not-allowed rounded-lg px-2.5 py-1.5 text-body-md font-semibold text-slate-400"
                title="Disponible en el módulo de Expedientes" aria-disabled="true">{{ $tab }}</span>
        @endforeach
    </nav>

    {{-- Tarjetas de resumen --}}
    <div class="mt-4 grid grid-cols-4 gap-4">
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

    @push('modals')
        @include('patients.partials.modals', ['withCreate' => false])
        @include('patients.partials.guardian-modals')
    @endpush
</x-layouts.app>
