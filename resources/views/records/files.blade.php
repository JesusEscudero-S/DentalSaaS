{{-- Pestaña "Archivos" (Figma: Expediente - Archivos). RF 6.7 Subir / 6.8 Eliminar. --}}
@php
    use App\Support\Ui;
    $bag = 'uploadFile';
@endphp

<x-records.shell :patient="$patient" :record="$record" :doctors="$doctors" active="archivos" section="Archivos">
    <x-ui.card class="px-5 py-5" x-data>
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-[16px] font-semibold text-slate-900">Archivos del paciente</h2>
            <x-ui.button x-on:click="$dispatch('open-modal', { name: 'upload-file' })">
                <x-ui.icon name="import" :size="16" />
                Subir archivos
            </x-ui.button>
        </div>

        @forelse ($files as $file)
            <div class="group -mx-2 mt-3 flex items-center justify-between gap-4 rounded-lg px-2 py-2 hover:bg-slate-50">
                <div class="min-w-0">
                    <a href="{{ route('records.files.show', [$patient, $file, 'ver' => 1]) }}" target="_blank" rel="noopener"
                        class="text-body-md font-bold text-slate-900 hover:text-primary-600 hover:underline">{{ $file->display_name }}</a>
                    <p class="mt-1 text-body-md text-slate-600">
                        {{ $file->extensionLabel() }} · {{ Ui::date($file->created_at) }} · {{ $file->uploadedBy->displayName() }}
                        <span class="text-slate-400">· {{ $file->file_type->label() }} · {{ $file->humanSize() }}</span>
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-1 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100">
                    <x-ui.button variant="ghost" size="sm" :href="route('records.files.show', [$patient, $file])">Descargar</x-ui.button>
                    @if ($file->isTemplateDocument())
                        {{-- Generado por el módulo Documentos: se gestiona (y se elimina) allá --}}
                        <x-ui.button variant="ghost" size="sm" :href="$file->template() ? route('documents.show', $file) : route('documents.index', ['paciente' => $patient->id])">Ver en Documentos</x-ui.button>
                    @else
                        <span x-on:click="$dispatch('open-modal', { name: 'delete-file', label: @js($file->display_name), action: @js(route('records.files.destroy', [$patient, $file])) })">
                            <x-ui.button variant="ghost-danger" size="sm">Eliminar</x-ui.button>
                        </span>
                    @endif
                </div>
            </div>
        @empty
            <div class="mt-4 rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center">
                <p class="text-body font-semibold text-slate-900">Sin archivos</p>
                <p class="mt-1 text-body-md text-slate-500">Sube radiografías, fotografías intraorales o estudios (JPG, PNG o PDF).</p>
            </div>
        @endforelse
    </x-ui.card>

    @push('modals')
        {{-- Subir archivos (pantalla propuesta con los componentes de Figma) --}}
        <x-ui.modal name="upload-file" title="Subir archivos" subtitle="{{ $patient->full_name }} · {{ $patient->folio }}"
            :show="$errors->uploadFile->any()">
            <form method="POST" action="{{ route('records.files.store', $patient) }}" enctype="multipart/form-data" class="flex flex-col gap-5" novalidate>
                @csrf
                <div class="flex flex-col gap-4">
                    @php
                        // Los errores llegan como files, files.0, files.1…: se muestra el primero.
                        $fileError = collect($errors->getBag($bag)->getMessages())->filter(fn ($m, $key) => str_starts_with($key, 'files'))->flatten()->first();
                    @endphp
                    <div class="flex flex-col gap-2">
                        <label for="files-upload" class="text-body-md font-medium text-black">Archivos<span class="text-danger-500">*</span></label>
                        <input id="files-upload" type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                            @if ($fileError) aria-invalid="true" @endif
                            class="w-full rounded-lg border border-slate-200 bg-surface text-body text-slate-700 file:mr-3 file:border-0 file:border-r file:border-slate-200 file:bg-slate-50 file:px-3 file:py-2 file:font-semibold file:text-slate-700 hover:file:bg-slate-100 aria-[invalid=true]:border-danger-500">
                        @if ($fileError)
                            <p class="text-xs text-danger-600">{{ $fileError }}</p>
                        @else
                            <p class="text-xs text-slate-500">JPG, PNG o PDF. Máximo 10 MB por archivo y 10 archivos a la vez.</p>
                        @endif
                    </div>
                    <x-ui.select name="file_type" label="Tipo de archivo" required :bag="$bag" :options="$fileTypes" value="radiografia" />
                    <x-ui.input name="description" label="Descripción corta" :bag="$bag" maxlength="200"
                        placeholder="Ej. Radiografía panorámica" hint="Si se deja vacío, se usa el nombre del archivo." />
                </div>
                <x-ui.modal-footer submit="Subir" />
            </form>
        </x-ui.modal>

        {{-- "¿Borrar archivo?" (RF 6.8) --}}
        <x-ui.modal name="delete-file" title="¿Borrar archivo?" width="max-w-[440px]">
            <p class="text-body text-slate-600">
                Se quitará <span class="font-semibold text-slate-900" x-text="payload.label"></span> del expediente del paciente.
            </p>
            <form method="POST" :action="payload.action">
                @csrf
                @method('DELETE')
                <x-ui.modal-footer type="danger" submit="Borrar" />
            </form>
        </x-ui.modal>
    @endpush
</x-records.shell>
