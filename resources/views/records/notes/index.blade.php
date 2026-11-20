{{-- Pestaña "Notas" (Figma: Expediente - Notas). Cada nota es un diagnóstico dental (RF 6.4). --}}
<x-records.shell :patient="$patient" :record="$record" :doctors="$doctors" active="notas" section="Notas">
    <x-ui.card class="px-5 py-5">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-[16px] font-semibold text-slate-900">Notas clínicas</h2>
            <div class="flex items-center gap-2" x-data>
                <x-ui.button variant="secondary" :href="route('records.notes.create', $patient)">Nota SOAP</x-ui.button>
                <x-ui.button x-on:click="$dispatch('open-modal', { name: 'create-note' })">Nueva nota</x-ui.button>
            </div>
        </div>

        @forelse ($notes as $note)
            <a href="{{ route('records.notes.show', [$patient, $note]) }}"
                class="-mx-2 mt-3 block rounded-lg px-2 py-2 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <p class="flex flex-wrap items-center gap-2 text-[12px] text-slate-500">
                    <span>{{ $note->heading() }}</span>
                    @if ($note->isSoap())
                        <span class="rounded bg-slate-100 px-1.5 text-[11px] font-semibold text-slate-600">SOAP</span>
                    @endif
                    @if ($note->tooth_fdi)
                        <span class="rounded bg-slate-100 px-1.5 text-[11px] font-semibold text-slate-600">Pieza {{ $note->tooth_fdi }}</span>
                    @endif
                    @if ($note->treatment)
                        <span class="rounded bg-slate-100 px-1.5 text-[11px] font-semibold text-slate-600">{{ $note->treatment->name }}</span>
                    @endif
                    @if ($note->isSigned())
                        <span class="rounded bg-emerald-50 px-1.5 text-[11px] font-semibold text-emerald-700">Firmada</span>
                    @else
                        <span class="rounded bg-amber-50 px-1.5 text-[11px] font-semibold text-amber-700">Borrador</span>
                    @endif
                    @if ($note->addenda->isNotEmpty())
                        <span class="text-[11px] font-semibold text-slate-500">
                            {{ $note->addenda->count() }} {{ $note->addenda->count() === 1 ? 'adenda' : 'adendas' }}
                        </span>
                    @endif
                </p>
                <p class="mt-1 text-body text-slate-900">{{ $note->summary() ?: 'Nota sin contenido todavía' }}</p>
            </a>
        @empty
            <div class="mt-4 rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center">
                <p class="text-body font-semibold text-slate-900">El paciente no tiene notas clínicas</p>
                <p class="mt-1 text-body-md text-slate-500">Registra el primer diagnóstico con "Nueva nota".</p>
            </div>
        @endforelse
    </x-ui.card>

    @push('modals')
        @include('records.notes.create-modal')
    @endpush
</x-records.shell>
