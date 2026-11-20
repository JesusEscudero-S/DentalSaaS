{{-- Documentos › Generar nuevo: tarjetas de plantillas (parte2.html) --}}
<x-layouts.app title="Generar documento" active="documentos" :breadcrumbs="['Documentos' => route('documents.index'), 'Generar nuevo' => null]">
    <div class="max-w-5xl">
        <div class="pb-6">
            <h1 class="text-h1 text-slate-900">Generación de documentos</h1>
            <p class="pt-[3px] text-body text-slate-500">Selecciona una plantilla para iniciar el registro o autorización del paciente.</p>
            @if ($preselected)
                <p class="mt-3 inline-flex items-center gap-2 rounded-full bg-primary-50 px-3 py-1 text-body-md font-semibold text-primary-700">
                    Paciente: {{ $preselected['name'] }} ({{ $preselected['folio'] }})
                </p>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-6">
            @foreach ($templates as $template)
                @php $canCreate = Route::has($template->createRoute()); @endphp
                <x-ui.card class="group flex flex-col p-6 transition hover:border-primary-500/40 hover:shadow-[0_8px_24px_rgba(15,23,42,0.08)]">
                    @include('documents.partials.template-icon', ['template' => $template, 'size' => 'lg', 'hoverable' => true])
                    <h2 class="mt-5 text-[18px] font-semibold leading-[26px] text-slate-900">{{ $template->label() }}</h2>
                    <p class="mb-6 mt-2 flex-1 text-body leading-relaxed text-slate-500">{{ $template->description() }}</p>

                    <div class="flex items-center justify-between border-t border-slate-100 pt-4">
                        <span x-data x-on:click="$dispatch('open-modal', { name: 'preview-template', title: @js($template->label()), url: @js(route('documents.preview', $template)) })">
                            <x-ui.button variant="ghost">Vista previa</x-ui.button>
                        </span>
                        @if ($canCreate)
                            <span x-data x-on:click="$dispatch('open-modal', { name: 'select-patient', template: @js($template->label()), url: @js(route($template->createRoute())) })">
                                <x-ui.button>
                                    Crear documento
                                    <x-ui.icon name="expand-right" :size="18" class="invert" />
                                </x-ui.button>
                            </span>
                        @else
                            <x-ui.button disabled title="Disponible próximamente">Crear documento</x-ui.button>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    </div>

    @push('modals')
        <x-ui.modal name="preview-template" width="max-w-[960px]">
            <x-slot:heading><span x-text="'Vista previa · ' + (payload.title ?? '')"></span></x-slot:heading>
            <iframe :src="open ? payload.url : 'about:blank'" title="Vista previa de la plantilla" class="h-[70vh] w-full rounded-lg border border-slate-200 bg-slate-50"></iframe>
            <div class="flex items-center justify-end gap-3">
                <a :href="payload.url" target="_blank" rel="noopener" class="text-body-md font-semibold text-primary-600 hover:underline">Abrir en otra pestaña</a>
                <x-ui.button variant="secondary" x-on:click="open = false">Cerrar</x-ui.button>
            </div>
        </x-ui.modal>

        @include('documents.partials.patient-picker')
    @endpush
</x-layouts.app>
