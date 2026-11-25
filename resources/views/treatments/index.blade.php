{{-- Módulo 7: pestaña "Tratamientos" del expediente. Lista de planes del paciente. --}}
@php use App\Support\Ui; @endphp

<x-records.shell :patient="$patient" :record="$record" :doctors="$doctors" active="tratamientos" section="Tratamientos">
    <x-ui.card class="overflow-hidden">
        <div class="flex items-center justify-between gap-4 px-5 py-4">
            <div>
                <h2 class="text-[16px] font-semibold text-slate-900">Tratamientos</h2>
                <p class="mt-1 text-body-md text-slate-500">Plan, autorización del paciente, sesiones y cierre de cada tratamiento.</p>
            </div>
            @if ($patient->isActive())
                <x-ui.button :href="route('records.treatments.create', $patient)">
                    <x-ui.icon name="add" :size="16" />
                    Nuevo tratamiento
                </x-ui.button>
            @endif
        </div>

        <table class="w-full table-fixed border-collapse">
            <colgroup><col class="w-[14%]"><col class="w-[30%]"><col class="w-[18%]"><col class="w-[12%]"><col class="w-[12%]"><col class="w-[14%]"></colgroup>
            <thead>
                <tr class="border-y border-slate-200 bg-slate-50 text-left">
                    @foreach (['Folio', 'Diagnóstico', 'Odontólogo', 'Fecha', 'Costo estimado', 'Estatus'] as $heading)
                        <th scope="col" class="px-4 py-[11px] text-[12px] font-semibold uppercase text-slate-400">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($plans as $plan)
                    @php $url = route('records.treatments.show', [$patient, $plan]); @endphp
                    <tr class="h-[53px] cursor-pointer border-b border-slate-200 bg-white transition-colors hover:bg-slate-50"
                        x-data x-on:click="window.location = @js($url)">
                        <td class="px-4 py-3">
                            <a href="{{ $url }}" class="font-mono text-body-md font-semibold text-primary-600 hover:underline">{{ $plan->quote->folio }}</a>
                            @if ($plan->version > 1)
                                <span class="block text-[11px] text-slate-400">Versión {{ $plan->version }}</span>
                            @endif
                        </td>
                        <td class="truncate px-4 py-3 text-body text-slate-900" title="{{ $plan->diagnosis }}">{{ $plan->diagnosis }}</td>
                        <td class="truncate px-4 py-3 text-body text-slate-700">{{ $plan->doctor?->displayName() }}</td>
                        <td class="px-4 py-3 text-[12.5px] text-slate-700">{{ Ui::date($plan->diagnosed_on) }}</td>
                        <td class="px-4 py-3 font-mono text-body text-slate-900">{{ Ui::money($plan->quote->total) }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge :color="$plan->status->color()">{{ $plan->status->label() }}</x-ui.badge>
                            @if ($plan->requires_reauthorization)
                                <span class="block pt-0.5 text-[11px] font-semibold text-amber-700">Requiere nueva autorización</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-16 text-center">
                            <p class="text-body font-semibold text-slate-900">Sin tratamientos</p>
                            <p class="mt-1 text-body-md text-slate-500">Registra el diagnóstico y los procedimientos con “Nuevo tratamiento”.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</x-records.shell>
