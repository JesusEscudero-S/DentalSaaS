{{-- Figma: Citas y agenda / Shell (calendario semanal) --}}
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

    {{-- Calendario semanal --}}
    <x-ui.card class="overflow-hidden">
        <div class="grid grid-cols-[56px_repeat(6,minmax(0,1fr))]">

            {{-- Fila 1: encabezados de los días --}}
            <div class="border-b border-slate-200"></div>
            @foreach ($days as $day)
                <div @class([
                    'border-b border-l border-slate-200 py-3 text-center',
                    'bg-primary-50 text-primary-600' => $day->isToday(),
                ])>
                    {{-- "lun." → "LUN" (Figma no lleva punto) --}}
                    <p class="text-[11px] uppercase">{{ rtrim($day->translatedFormat('D'), '.') }}</p>
                    <p class="text-[18px] font-semibold">{{ $day->day }}</p>
                </div>
            @endforeach

            {{-- Fila 2: columna de horas + una columna por día --}}
            <div>
                @foreach ($hours as $hour)
                    <div class="h-[60px] pr-2 text-right font-mono text-[10px] text-slate-400">{{ $hour }}:00</div>
                @endforeach
            </div>

            @foreach ($days as $day)
                <div class="relative border-l border-slate-200">
                    {{-- líneas de cada hora --}}
                    @foreach ($hours as $hour)
                        <div class="h-[60px] border-b border-slate-100"></div>
                    @endforeach

                    {{-- citas de este día --}}
                    @foreach ($appointmentsByDay[$day->toDateString()] ?? [] as $appointment)
                        <x-agenda.appointment-card :appointment="$appointment" />
                    @endforeach
                </div>
            @endforeach
        </div>
    </x-ui.card>
</x-layouts.app>
