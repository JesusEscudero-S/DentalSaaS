{{-- Figma: Pacientes / Shell (listado) --}}
@php
    use App\Support\Ui;
    $estadoOptions = ['activos' => 'Estado: activos', 'inactivos' => 'Estado: inactivos', 'todos' => 'Estado: todos'];
    $doctorOptions = $doctors->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all();
    $hasFilters = $filters['q'] !== '' || $filters['doctor'] !== '' || $filters['estado'] !== 'activos';
@endphp

<x-layouts.app title="Pacientes" active="pacientes" :breadcrumbs="['Pacientes' => null]">
    {{-- Encabezado --}}
    <div class="flex items-start justify-between gap-6 pb-5">
        <div class="py-[3px]">
            <h1 class="text-h1 text-slate-900">Pacientes</h1>
            <p class="pt-[3px] text-body font-semibold text-slate-400">
                {{ $registeredCount }} {{ $registeredCount === 1 ? 'paciente registrado' : 'pacientes registrados' }}
            </p>
        </div>
        <div class="flex items-center gap-2.5 p-2.5">
                <x-ui.button variant="outline" size="sm" :href="route('patients.export', request()->query())">
                    <x-ui.icon name="import" :size="24" />
                    Exportar
                </x-ui.button>
                <x-ui.button size="lg" x-data x-on:click="$dispatch('open-modal', { name: 'create-patient' })">
                    <x-ui.icon name="add" :size="16" />
                    Nuevo Paciente
                </x-ui.button>
        </div>
    </div>

    <div class="overflow-hidden rounded-[10px] bg-white">
        {{-- Barra de búsqueda y filtros --}}
        <form method="GET" action="{{ route('patients.index') }}" class="flex flex-wrap items-center gap-2.5 px-4 py-3.5" x-data>
            <label class="flex h-9 w-[349px] items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3.5 focus-within:border-primary-600 focus-within:ring-2 focus-within:ring-primary-600/15">
                <x-ui.icon name="search" :size="24" />
                <span class="sr-only">Buscar</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por nombre, folio, teléfono o email..."
                    class="h-full w-full border-0 bg-transparent px-2 text-body-md text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">
            </label>
            <x-ui.select name="doctor" compact :options="$doctorOptions" placeholder="Todos los doctores" :value="$filters['doctor']"
                aria-label="Filtrar por doctor" x-on:change="$el.form.submit()" />
            <x-ui.select name="estado" compact :options="$estadoOptions" :value="$filters['estado']"
                aria-label="Filtrar por estado" x-on:change="$el.form.submit()" />
            @if ($hasFilters)
                <a href="{{ route('patients.index') }}" class="text-body-md font-semibold text-primary-600 hover:underline">Limpiar filtros</a>
            @endif
        </form>

        {{-- Tabla --}}
        <table class="w-full table-fixed border-collapse">
            <colgroup>
                <col class="w-[26%]"><col class="w-[13%]"><col class="w-[11%]"><col class="w-[17%]">
                <col class="w-[10%]"><col class="w-[10%]"><col class="w-[13%]">
            </colgroup>
            <thead>
                <tr class="border-y border-slate-200 bg-slate-50 text-left">
                    @foreach (['Paciente', 'Teléfono', 'Última visita', 'Doctor', 'Balance', 'Estado', 'Acciones'] as $heading)
                        <th scope="col" class="px-4 py-[11px] text-[12px] font-semibold uppercase text-slate-400">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($patients as $patient)
                    @php $showUrl = route('patients.show', $patient); @endphp
                    <tr class="group h-[53px] cursor-pointer border-b border-slate-200 bg-white transition-colors hover:bg-slate-50 focus-within:bg-slate-50"
                        x-data x-on:click="if (! $event.target.closest('a,button,form')) window.location = @js($showUrl)">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <x-ui.avatar :initials="$patient->initials" :color="$patient->isActive() ? $patient->avatar_color : 'slate'" />
                                <div class="min-w-0 leading-5">
                                    <a href="{{ $showUrl }}" class="block truncate text-body-md font-semibold text-black hover:text-primary-600 focus:outline-none focus-visible:underline">{{ $patient->full_name }}</a>
                                    <p class="flex gap-1.5 whitespace-nowrap font-mono text-[11.5px] font-bold text-slate-400">
                                        <span>{{ $patient->folio }}</span>
                                        @if ($patient->age !== null)
                                            <span>·</span><span>{{ $patient->age }} {{ $patient->age === 1 ? 'año' : 'años' }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-body text-black">{{ $patient->formatted_phone }}</td>
                        <td class="px-4 py-3 text-[12.5px] text-black">{{ Ui::date($patient->last_visit_at) }}</td>
                        <td class="px-4 py-3">
                            @if ($patient->assignedDoctor)
                                <span class="flex items-center gap-3.5 text-body text-black">
                                    <x-ui.color-dot :color="Ui::colorFor($patient->assignedDoctor->id)" />
                                    <span class="truncate">{{ $patient->assignedDoctor->displayName() }}</span>
                                </span>
                            @else
                                <span class="text-body text-slate-400">Sin asignar</span>
                            @endif
                        </td>
                        <td @class(['px-4 py-3 font-mono text-body', 'font-bold text-danger-500' => $patient->balance > 0, 'text-slate-400' => $patient->balance <= 0])>
                            {{ Ui::money($patient->balance) }}
                        </td>
                        <td class="px-4 py-3"><x-ui.status-badge :status="$patient->status" /></td>
                        <td class="px-4 py-2">
                            {{-- TableRow state=hover: las acciones aparecen al pasar el mouse --}}
                            <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
                                <a href="{{ $showUrl }}" class="shrink-0 rounded-md hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                    title="Ver perfil" aria-label="Ver perfil de {{ $patient->full_name }}">
                                    <x-ui.icon name="action-view" :size="30" />
                                </a>
                                    <button type="button" title="Editar" aria-label="Editar a {{ $patient->full_name }}"
                                        class="shrink-0 rounded-md hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                        x-on:click="$dispatch('open-modal', { name: 'edit-patient', patient: @js($patient->toFormArray()), action: @js(route('patients.update', $patient)) })">
                                        <x-ui.icon name="action-edit" :size="30" />
                                    </button>
                                    @if ($patient->isActive())
                                        <button type="button" title="Desactivar" aria-label="Desactivar a {{ $patient->full_name }}"
                                            class="shrink-0 rounded-md hover:bg-danger-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500/50"
                                            x-on:click="$dispatch('open-modal', { name: 'deactivate-patient', label: @js("{$patient->full_name} ({$patient->folio})"), action: @js(route('patients.deactivate', $patient)) })">
                                            <x-ui.icon name="action-delete" :size="30" />
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('patients.reactivate', $patient) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-ui.button type="submit" variant="ghost" size="sm" class="!px-2 text-primary-600">Reactivar</x-ui.button>
                                        </form>
                                    @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center">
                            <p class="text-body font-semibold text-slate-900">
                                {{ $filters['q'] !== '' ? 'No se encontró ningún paciente' : 'No hay pacientes para mostrar' }}
                            </p>
                            <p class="mt-1 text-body-md text-slate-500">
                                @if ($filters['q'] !== '')
                                    No existe un paciente que coincida con “{{ $filters['q'] }}”{{ $filters['estado'] === 'activos' ? ' entre los activos' : '' }}.
                                @else
                                    Ajusta los filtros o registra un nuevo paciente.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pie --}}
        <div class="flex items-center justify-between gap-4 px-3 py-4">
            <p class="text-[12px] text-slate-600">
                @if ($patients->total())
                    Mostrando {{ $patients->firstItem() }}-{{ $patients->lastItem() }} de {{ $patients->total() }} {{ $patients->total() === 1 ? 'paciente' : 'pacientes' }}
                @endif
            </p>
            {{ $patients->links() }}
        </div>
    </div>

    @push('modals')
        @include('patients.partials.modals', ['withCreate' => true])
    @endpush
</x-layouts.app>
