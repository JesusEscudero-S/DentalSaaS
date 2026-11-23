{{--
    Modal "Seleccionar paciente": busca pacientes activos (Patient::search vía documents.patients) y
    continúa al formulario de la plantilla con ?paciente=. Variables: $preselected (array|null)
--}}
<x-ui.modal name="select-patient" title="Seleccionar paciente" width="max-w-[520px]">
    <x-slot:sub><span x-text="payload.template"></span></x-slot:sub>

    <div class="flex flex-col gap-4"
        x-data="{
            endpoint: @js(route('documents.patients')),
            q: '',
            results: [],
            loading: false,
            searched: false,
            selected: @js($preselected),
            url: '',
            colors: { blue: 'bg-primary-600', sky: 'bg-sky-500', green: 'bg-emerald-500', violet: 'bg-violet-500', amber: 'bg-amber-500', slate: 'bg-slate-500' },
            async search() {
                this.loading = true;
                try {
                    const response = await fetch(this.endpoint + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
                    this.results = response.ok ? await response.json() : [];
                } finally {
                    this.loading = false;
                    this.searched = true;
                }
            },
            go() {
                if (this.selected) window.location = this.url + '?paciente=' + encodeURIComponent(this.selected.id);
            },
        }"
        x-on:open-modal.window="if ($event.detail.name === 'select-patient') { url = $event.detail.url; if (! searched) search() }">

        <label class="flex h-10 items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3.5 focus-within:border-primary-600 focus-within:ring-2 focus-within:ring-primary-600/15">
            <x-ui.icon name="search" :size="24" />
            <span class="sr-only">Buscar paciente</span>
            <input type="search" x-model="q" x-on:input.debounce.300ms="search()" placeholder="Buscar por nombre, folio o teléfono..."
                class="h-full w-full border-0 bg-transparent px-2 text-body-md text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">
        </label>

        <ul class="max-h-72 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-200" role="listbox" aria-label="Pacientes">
            <template x-for="patient in results" :key="patient.id">
                <li>
                    <button type="button" role="option" :aria-selected="selected?.id === patient.id" x-on:click="selected = patient"
                        class="flex w-full items-center gap-3 px-3 py-2.5 text-left transition-colors hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
                        :class="selected?.id === patient.id && '!bg-primary-50'">
                        <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full text-[12px] font-bold text-white"
                            :class="colors[patient.color] ?? colors.blue" x-text="patient.initials"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-body-md font-semibold text-slate-900" x-text="patient.name"></span>
                            <span class="block font-mono text-[11.5px] font-bold text-slate-400" x-text="patient.folio + (patient.age !== null ? ' · ' + patient.age + ' años' : '')"></span>
                        </span>
                        <span x-show="selected?.id === patient.id" class="text-body-md font-bold text-primary-600">✓</span>
                    </button>
                </li>
            </template>
            <li x-show="loading" class="px-3 py-6 text-center text-body-md text-slate-500">Buscando…</li>
            <li x-show="! loading && searched && results.length === 0" class="px-3 py-6 text-center text-body-md text-slate-500">
                No se encontraron pacientes activos.
            </li>
        </ul>

        <p x-show="selected" class="text-body-md text-slate-600">
            Paciente seleccionado: <span class="font-semibold text-slate-900" x-text="selected?.name + ' (' + selected?.folio + ')'"></span>
        </p>

        <div class="flex items-center justify-end gap-3">
            <x-ui.button variant="secondary" x-on:click="open = false">Cancelar</x-ui.button>
            <x-ui.button x-on:click="go()" x-bind:disabled="! selected">Continuar</x-ui.button>
        </div>
    </div>
</x-ui.modal>
