{{-- Configuración → Tratamientos (Figma: "Catálogo de tratamientos"). Solo consulta; el alta es del módulo 5. --}}
@php use App\Support\Ui; @endphp

<x-settings.shell active="tratamientos" section="Tratamientos">
    <div class="overflow-hidden rounded-[10px] border border-slate-200 bg-white">
        <div class="flex items-center justify-between gap-4 px-4 py-3.5">
            <h2 class="text-[16px] font-semibold text-slate-900">Catálogo de tratamientos</h2>
            <div class="flex items-center gap-2.5">
                <form method="GET" action="{{ route('settings.treatments') }}">
                    <label class="flex h-9 w-[260px] items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3 focus-within:border-primary-600 focus-within:ring-2 focus-within:ring-primary-600/15">
                        <x-ui.icon name="search" :size="22" />
                        <span class="sr-only">Buscar</span>
                        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar tratamiento o categoría..."
                            class="h-full w-full border-0 bg-transparent px-2 text-body-md text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">
                    </label>
                </form>
                <span title="El alta del catálogo es del módulo 5 (Servicios odontológicos)">
                    <x-ui.button size="sm" disabled>
                        <x-ui.icon name="add" :size="16" />
                        Nuevo tratamiento
                    </x-ui.button>
                </span>
            </div>
        </div>

        <table class="w-full table-fixed border-collapse">
            <colgroup><col class="w-[34%]"><col class="w-[20%]"><col class="w-[14%]"><col class="w-[16%]"><col class="w-[16%]"></colgroup>
            <thead>
                <tr class="border-y border-slate-200 bg-slate-50 text-left">
                    @foreach (['Tratamiento', 'Categoría', 'Duración', 'Clave SAT'] as $heading)
                        <th scope="col" class="px-4 py-[11px] text-[12px] font-semibold uppercase text-slate-400">{{ $heading }}</th>
                    @endforeach
                    <th scope="col" class="px-4 py-[11px] text-right text-[12px] font-semibold uppercase text-slate-400">Precio</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($treatments as $treatment)
                    <tr class="h-[49px] border-b border-slate-200 bg-white">
                        <td class="truncate px-4 py-3 text-body-md font-semibold text-slate-900">{{ $treatment->name }}</td>
                        <td class="px-4 py-3">
                            @if ($treatment->category_name)
                                <x-ui.badge color="slate">{{ $treatment->category_name }}</x-ui.badge>
                            @else
                                <span class="text-body text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-body text-slate-700">{{ $treatment->duration_minutes }} min</td>
                        <td class="px-4 py-3 font-mono text-body text-slate-700">{{ $treatment->sat_product_key ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-mono text-body text-slate-900">{{ Ui::money($treatment->price) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-16 text-center">
                            <p class="text-body font-semibold text-slate-900">{{ $q !== '' ? 'No se encontró ningún tratamiento' : 'El catálogo está vacío' }}</p>
                            <p class="mt-1 text-body-md text-slate-500">Los tratamientos se dan de alta en Servicios odontológicos (módulo 5).</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="flex items-center justify-between gap-4 px-3 py-4">
            <p class="text-[12px] text-slate-600">
                @if ($treatments->total())
                    Mostrando {{ $treatments->firstItem() }}-{{ $treatments->lastItem() }} de {{ $treatments->total() }} {{ $treatments->total() === 1 ? 'tratamiento' : 'tratamientos' }}
                @endif
            </p>
            {{ $treatments->links() }}
        </div>
    </div>
</x-settings.shell>
