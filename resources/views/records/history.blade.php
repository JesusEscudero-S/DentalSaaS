{{-- Pestaña "Historial" (Figma: Expediente - Historial): consultas realizadas y citas programadas. --}}
@php use App\Support\Ui; @endphp

<x-records.shell :patient="$patient" :record="$record" :doctors="$doctors" active="historial" section="Historial">
    <x-ui.card class="px-5 py-5">
        <h2 class="text-[16px] font-semibold text-slate-900">Historial de consultas</h2>

        @forelse ($timeline as $row)
            <div class="mt-4">
                <p class="text-body-md font-bold text-slate-900">
                    {{ Ui::date($row['date']) }} · {{ $row['doctor'] }}
                    @if ($row['scheduled'])
                        <span class="font-semibold text-primary-600">(programada · {{ $row['date']->format('H:i') }})</span>
                    @endif
                </p>
                <p class="mt-1 text-body-md text-slate-600">
                    @if ($row['note'])
                        <a href="{{ route('records.notes.show', [$patient, $row['note']]) }}" class="hover:text-primary-600 hover:underline">{{ $row['text'] }}</a>
                    @else
                        {{ $row['text'] }}
                    @endif
                </p>
            </div>
        @empty
            <div class="mt-4 rounded-lg border border-dashed border-slate-200 px-4 py-8 text-center">
                <p class="text-body font-semibold text-slate-900">Sin consultas registradas</p>
                <p class="mt-1 text-body-md text-slate-500">Las consultas aparecen aquí al registrar una nota clínica.</p>
            </div>
        @endforelse
    </x-ui.card>
</x-records.shell>
