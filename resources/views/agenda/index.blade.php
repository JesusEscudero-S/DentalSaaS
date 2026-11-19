
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
        <p class="text-body text-slate-500">Aquí irá el calendario semanal.</p>
        </x-ui.card>

    </div>

   
</x-layouts.app>
