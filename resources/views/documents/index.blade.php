{{-- Documentos › Historial --}}
@php
    use App\Enums\DocumentTemplate;
    use App\Support\Ui;

    $estadoOptions = ['todos' => 'Estado: todos', 'pendiente' => 'Pendientes de firma', 'firmado' => 'Firmados'];
    $hasFilters = collect($filters)->except('estado')->contains(fn ($v) => $v !== '') || $filters['estado'] !== 'todos';
    $dateInput = 'h-9 rounded-lg border border-slate-300 bg-surface px-3 text-[13.5px] text-slate-900 hover:border-slate-400 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/15';
@endphp

<x-layouts.app title="Documentos" active="documentos" :breadcrumbs="['Documentos' => null]">
    <div class="flex items-start justify-between gap-6 pb-5">
        <div class="py-[3px]">
            <h1 class="text-h1 text-slate-900">Documentos</h1>
            <p class="pt-[3px] text-body font-semibold text-slate-400">Historias clínicas y autorizaciones de tratamiento</p>
        </div>
        <div class="flex items-center gap-2.5 p-2.5">
            <x-ui.button size="lg" :href="route('documents.create', $patient ? ['paciente' => $patient->id] : [])">
                <x-ui.icon name="add" :size="16" />
                Nuevo documento
            </x-ui.button>
        </div>
    </div>

    @if ($patient)
        <div class="mb-3 flex items-center gap-2 text-body-md text-slate-600">
            Documentos de
            <span class="inline-flex items-center gap-2 rounded-full bg-primary-50 py-1 pl-3 pr-1.5 font-semibold text-primary-700">
                {{ $patient->full_name }} ({{ $patient->folio }})
                <a href="{{ route('documents.index', collect($filters)->except('paciente')->filter(fn ($v) => $v !== '')->all()) }}"
                    class="flex size-5 items-center justify-center rounded-full hover:bg-primary-100" aria-label="Quitar filtro de paciente">×</a>
            </span>
        </div>
    @endif

    <x-ui.card class="overflow-hidden">
        <form method="GET" action="{{ route('documents.index') }}" class="flex flex-wrap items-center gap-2.5 px-4 py-3.5" x-data>
            @if ($filters['paciente'] !== '')
                <input type="hidden" name="paciente" value="{{ $filters['paciente'] }}">
            @endif
            <label class="flex h-9 w-[300px] items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3.5 focus-within:border-primary-600 focus-within:ring-2 focus-within:ring-primary-600/15">
                <x-ui.icon name="search" :size="24" />
                <span class="sr-only">Buscar</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por paciente o folio..."
                    class="h-full w-full border-0 bg-transparent px-2 text-body-md text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">
            </label>
            <x-ui.select name="plantilla" compact :options="DocumentTemplate::options()" placeholder="Todas las plantillas" :value="$filters['plantilla']"
                aria-label="Filtrar por plantilla" x-on:change="$el.form.submit()" />
            <x-ui.select name="estado" compact :options="$estadoOptions" :value="$filters['estado']"
                aria-label="Filtrar por estado" x-on:change="$el.form.submit()" />
            <label class="flex items-center gap-1.5 text-body-md text-slate-500">
                Desde
                <input type="date" name="desde" value="{{ $filters['desde'] }}" class="{{ $dateInput }}" x-on:change="$el.form.submit()">
            </label>
            <label class="flex items-center gap-1.5 text-body-md text-slate-500">
                Hasta
                <input type="date" name="hasta" value="{{ $filters['hasta'] }}" class="{{ $dateInput }}" x-on:change="$el.form.submit()">
            </label>
            @if ($hasFilters)
                <a href="{{ route('documents.index') }}" class="text-body-md font-semibold text-primary-600 hover:underline">Limpiar filtros</a>
            @endif
        </form>

        <table class="w-full table-fixed border-collapse">
            <colgroup>
                <col class="w-[27%]"><col class="w-[25%]"><col class="w-[13%]"><col class="w-[14%]"><col class="w-[11%]"><col class="w-[10%]">
            </colgroup>
            <thead>
                <tr class="border-y border-slate-200 bg-slate-50 text-left">
                    @foreach (['Documento', 'Paciente', 'Fecha', 'Generado por', 'Estado', 'Acciones'] as $heading)
                        <th scope="col" class="px-4 py-[11px] text-[12px] font-semibold uppercase text-slate-400">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $document)
                    @php
                        $template = $document->template();
                        $status = $document->documentStatus();
                        $showUrl = route('documents.show', $document);
                    @endphp
                    <tr class="group h-[57px] cursor-pointer border-b border-slate-200 bg-white transition-colors hover:bg-slate-50 focus-within:bg-slate-50"
                        x-data x-on:click="if (! $event.target.closest('a,button,form')) window.location = @js($showUrl)">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @include('documents.partials.template-icon', ['template' => $template, 'size' => 'sm'])
                                <a href="{{ $showUrl }}" class="truncate text-body-md font-semibold text-slate-900 hover:text-primary-600">{{ $template->label() }}</a>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <x-ui.avatar :initials="$document->patient?->initials ?? '?'" :color="$document->patient?->avatar_color ?? 'slate'" size="sm" />
                                <div class="min-w-0 leading-5">
                                    <p class="truncate text-body-md font-semibold text-black">{{ $document->patient?->full_name }}</p>
                                    <p class="font-mono text-[11.5px] font-bold text-slate-400">{{ $document->patient?->folio }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-[12.5px] text-slate-700">
                            {{ Ui::date($document->created_at) }}
                            <span class="block text-[11.5px] text-slate-400">{{ $document->created_at?->format('H:i') }}</span>
                        </td>
                        <td class="truncate px-4 py-3 text-body-md text-slate-700">{{ $document->uploadedBy?->displayName() ?? '—' }}</td>
                        <td class="px-4 py-3"><x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge></td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
                                <a href="{{ $showUrl }}" title="Ver" aria-label="Ver {{ $document->display_name }}"
                                    class="shrink-0 rounded-md hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    <x-ui.icon name="action-view" :size="30" />
                                </a>
                                <a href="{{ route('documents.download', $document) }}" title="Descargar" aria-label="Descargar {{ $document->display_name }}"
                                    class="flex size-[30px] shrink-0 items-center justify-center rounded-md hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    <x-ui.icon name="import" :size="20" />
                                </a>
                                @if ($status !== \App\Enums\DocumentStatus::Signed)
                                    <button type="button" title="Eliminar" aria-label="Eliminar {{ $document->display_name }}"
                                        class="shrink-0 rounded-md hover:bg-danger-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500/50"
                                        x-on:click="$dispatch('open-modal', { name: 'delete-document', label: @js($document->display_name), action: @js(route('documents.destroy', $document)) })">
                                        <x-ui.icon name="action-delete" :size="30" />
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-16 text-center">
                            <p class="text-body font-semibold text-slate-900">
                                {{ $hasFilters ? 'No hay documentos que coincidan con los filtros' : 'Aún no hay documentos generados' }}
                            </p>
                            <p class="mt-1 text-body-md text-slate-500">
                                {{ $hasFilters ? 'Ajusta o limpia los filtros.' : 'Genera una historia clínica o una autorización de tratamiento.' }}
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="flex items-center justify-between gap-4 px-3 py-4">
            <p class="text-[12px] text-slate-600">
                @if ($documents->total())
                    Mostrando {{ $documents->firstItem() }}-{{ $documents->lastItem() }} de {{ $documents->total() }} {{ $documents->total() === 1 ? 'documento' : 'documentos' }}
                @endif
            </p>
            {{ $documents->links() }}
        </div>
    </x-ui.card>

    @push('modals')
        @include('documents.partials.delete-modal')
    @endpush
</x-layouts.app>
