{{-- Documentos › Detalle: visor PDF y panel lateral --}}
@php
    use App\Enums\DocumentStatus;
    use App\Support\Ui;

    $status = $document->documentStatus();
    $acceptance = $document->consentAcceptance;
    $signed = $status === DocumentStatus::Signed;
    $streamUrl = route('documents.stream', $document);
    $size = $document->size_bytes >= 1048576
        ? number_format($document->size_bytes / 1048576, 1).' MB'
        : number_format(max(1, $document->size_bytes / 1024), 0).' KB';
    $newVersionUrl = Route::has($template->createRoute())
        ? route($template->createRoute(), ['paciente' => $document->patient_id, 'desde' => $document->id])
        : null;
@endphp

<x-layouts.app :title="$template->label()" active="documentos" :breadcrumbs="['Documentos' => route('documents.index'), $template->label() => null]">
    <div x-data="{
        print() {
            try {
                this.$refs.viewer.contentWindow.focus();
                this.$refs.viewer.contentWindow.print();
            } catch (e) {
                window.open(@js($streamUrl), '_blank');
            }
        },
    }">
        <div class="flex items-start justify-between gap-6 pb-5">
            <div class="flex min-w-0 items-center gap-3 py-[3px]">
                @include('documents.partials.template-icon', ['template' => $template, 'size' => 'lg'])
                <div class="min-w-0">
                    <h1 class="text-h1 text-slate-900">{{ $template->label() }}</h1>
                    <p class="truncate pt-[3px] text-body font-semibold text-slate-400">{{ $document->display_name }}</p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2.5 p-2.5">
                @if ($signed)
                    <x-ui.button variant="ghost" disabled title="No se puede eliminar un consentimiento firmado">Eliminar</x-ui.button>
                @else
                    <span x-on:click="$dispatch('open-modal', { name: 'delete-document', label: @js($document->display_name), action: @js(route('documents.destroy', $document)) })">
                        <x-ui.button variant="ghost-danger">Eliminar</x-ui.button>
                    </span>
                @endif
                @if ($newVersionUrl)
                    <x-ui.button variant="secondary" :href="$newVersionUrl">Nueva versión</x-ui.button>
                @endif
                <span x-on:click="print()">
                    <x-ui.button variant="secondary">Imprimir</x-ui.button>
                </span>
                <x-ui.button size="lg" :href="route('documents.download', $document)">
                    <x-ui.icon name="import" :size="20" class="invert" />
                    Descargar
                </x-ui.button>
            </div>
        </div>

        <div class="grid grid-cols-[1fr_320px] items-start gap-4">
            <x-ui.card class="overflow-hidden">
                <iframe x-ref="viewer" src="{{ $streamUrl }}" title="{{ $document->display_name }}" class="h-[78vh] w-full bg-slate-100"></iframe>
            </x-ui.card>

            <div class="flex flex-col gap-4">
                <x-ui.card class="p-5">
                    <h2 class="text-overline uppercase text-slate-400">Detalles</h2>
                    <dl class="mt-3 flex flex-col gap-3 text-body-md">
                        <div>
                            <dt class="text-slate-500">Estado</dt>
                            <dd class="mt-1"><x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge></dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Paciente</dt>
                            <dd class="mt-0.5">
                                @if ($document->patient)
                                    <a href="{{ route('patients.show', $document->patient) }}" class="font-semibold text-primary-600 hover:underline">{{ $document->patient->full_name }}</a>
                                    <span class="font-mono text-[11.5px] font-bold text-slate-400">{{ $document->patient->folio }}</span>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Generado</dt>
                            <dd class="mt-0.5 text-slate-900">{{ Ui::date($document->created_at) }}, {{ $document->created_at?->format('H:i') }} · {{ $document->uploadedBy?->displayName() ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Archivo</dt>
                            <dd class="mt-0.5 text-slate-900">PDF · {{ $size }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Huella SHA-256</dt>
                            <dd class="mt-0.5 font-mono text-[12px] text-slate-700" title="{{ $document->sha256 }}">{{ substr((string) $document->sha256, 0, 16) }}…</dd>
                        </div>
                    </dl>
                </x-ui.card>

                @if ($template->requiresSignature())
                    <x-ui.card class="p-5">
                        <h2 class="text-overline uppercase text-slate-400">Firma del consentimiento</h2>
                        @if ($acceptance)
                            <dl class="mt-3 flex flex-col gap-3 text-body-md">
                                <div>
                                    <dt class="text-slate-500">Aceptó</dt>
                                    <dd class="mt-0.5 font-semibold text-slate-900">{{ $acceptance->accepted_by_name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Método</dt>
                                    <dd class="mt-0.5 text-slate-900">{{ $acceptance->method?->label() }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Fecha</dt>
                                    <dd class="mt-0.5 text-slate-900">{{ Ui::date($acceptance->accepted_at) }}, {{ $acceptance->accepted_at?->format('H:i') }}</dd>
                                </div>
                                @if ($acceptance->witness)
                                    <div>
                                        <dt class="text-slate-500">Testigo</dt>
                                        <dd class="mt-0.5 text-slate-900">{{ $acceptance->witness->displayName() }}</dd>
                                    </div>
                                @endif
                                @if ($signedScan)
                                    <div>
                                        <dt class="text-slate-500">Documento firmado</dt>
                                        <dd class="mt-0.5">
                                            <a href="{{ route('documents.consent.scan', $document) }}" target="_blank" class="font-semibold text-primary-600 hover:underline">Ver escaneo</a>
                                        </dd>
                                    </div>
                                @endif
                                <div>
                                    <dt class="text-slate-500">Huella del contenido aceptado</dt>
                                    <dd class="mt-0.5 font-mono text-[12px] text-slate-700" title="{{ $acceptance->content_hash }}">{{ substr((string) $acceptance->content_hash, 0, 16) }}…</dd>
                                </div>
                            </dl>
                        @else
                            <p class="mt-3 text-body-md text-slate-600">El paciente o su tutor aún no firma esta autorización.</p>
                            @if (Route::has('documents.consent.store'))
                                <span class="mt-4 flex" x-on:click="$dispatch('open-modal', { name: 'register-signature' })">
                                    <x-ui.button class="w-full">Registrar firma</x-ui.button>
                                </span>
                            @endif
                        @endif
                    </x-ui.card>
                @endif
            </div>
        </div>
    </div>

    @push('modals')
        @include('documents.partials.delete-modal')
        @if ($template->requiresSignature() && ! $acceptance)
            @include('documents.partials.signature-modal')
        @endif
    @endpush
</x-layouts.app>
