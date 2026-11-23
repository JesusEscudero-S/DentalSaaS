{{--
    Control de aplicaciones de flúor (RF 6.6). No existe en Figma: sigue el formato de la hoja de
    papel de la clínica (una columna por año de control con sus aplicaciones).
--}}
@php use App\Support\Ui; @endphp

<x-ui.card class="px-5 py-5" x-data>
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-[16px] font-semibold text-slate-900">Control de aplicaciones de flúor</h2>
        <x-ui.button size="sm" x-on:click="$dispatch('open-modal', { name: 'create-fluoride' })">Registrar aplicación</x-ui.button>
    </div>

    @if ($fluorideByYear->isEmpty())
        <p class="mt-3 text-body-md text-slate-500">Sin aplicaciones registradas.</p>
    @else
        <div class="mt-3 overflow-x-auto">
            <table class="w-full border-collapse text-body-md">
                <thead>
                    <tr>
                        <th class="w-[110px] border border-slate-200 bg-slate-50 px-3 py-1.5 text-left text-overline uppercase text-slate-500">Año</th>
                        @foreach ($fluorideByYear as $year => $applications)
                            <th class="border border-slate-200 bg-slate-50 px-3 py-1.5 text-center font-semibold text-slate-900">{{ $year }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th class="border border-slate-200 bg-slate-50 px-3 py-1.5 text-left align-top text-overline uppercase text-slate-500">Aplicaciones</th>
                        @foreach ($fluorideByYear as $year => $applications)
                            <td class="border border-slate-200 px-2 py-1.5 align-top">
                                @foreach ($applications as $application)
                                    {{-- Cada aplicación: fecha, quién la aplicó y sus observaciones (visibles, no solo en un tooltip) --}}
                                    <div class="group rounded px-1 py-1 hover:bg-slate-50 [&:not(:first-child)]:border-t [&:not(:first-child)]:border-slate-100">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-semibold text-slate-900">{{ Ui::date($application->applied_on) }}</span>
                                            <button type="button" aria-label="Eliminar aplicación del {{ Ui::date($application->applied_on) }}"
                                                x-on:click="$dispatch('open-modal', { name: 'delete-fluoride', label: @js(Ui::date($application->applied_on)), action: @js(route('records.fluoride.destroy', [$patient, $application])) })"
                                                class="rounded px-1 text-slate-400 opacity-0 hover:text-danger-600 focus-visible:opacity-100 group-hover:opacity-100">×</button>
                                        </div>
                                        <p class="text-[11px] leading-4 text-slate-500">{{ $application->appliedBy->displayName() }}</p>
                                        @if ($application->notes)
                                            <p class="mt-0.5 break-words text-[12px] leading-4 text-slate-700">{{ $application->notes }}</p>
                                        @endif
                                    </div>
                                @endforeach
                                <p class="mt-1 px-1 text-[11px] text-slate-400">{{ $applications->count() }} {{ $applications->count() === 1 ? 'aplicación' : 'aplicaciones' }}</p>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</x-ui.card>
