
<x-layouts.app title="Agenda" active="agenda" :breadcrumbs="['Agenda' => null]">
    {{-- Encabezado --}}
    <div class="flex items-start justify-between gap-6 pb-5">
        <div class="py-[3px]">
            <h1 class="text-h1 text-slate-900">Agenda</h1>
            <p class="pt-[3px] text-body font-semibold text-slate-400">
               Semana del {{ $weekStart->format('j') }} - {{ $weekEnd->translatedFormat('j \d\e F Y') }}
            </p>
        </div>
        <div class="flex items-center gap-2.5 p-2.5">
                <x-ui.button size="lg">
                    <x-ui.icon name="add" :size="16" />
                    Nueva cita
                </x-ui.button>
        </div>
    </div>

    <div class="overflow-hidden rounded-[10px] bg-white">
       <x-ui.card class="p-6">
        @forelse ($appointments as $appointment)
    <p class="text-body">
        {{ $appointment->starts_at->translatedFormat('D j, H:i') }} ·
        {{ $appointment->patient->full_name }} ·
        {{ $appointment->doctor->displayName() }} ·
        {{ $appointment->notes }} ·
        {{ $appointment->status->label() }}
    </p>
@empty
    <p class="text-body text-slate-500">No hay citas esta semana.</p>
@endforelse
        </x-ui.card>

    </div>

   
</x-layouts.app>
