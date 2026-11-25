{{-- Módulo 1. Gestión de usuarios: listado (mismo patrón que Pacientes / Shell) --}}
@php
    use App\Support\Ui;
    $estadoOptions = ['activos' => 'Estado: activos', 'inactivos' => 'Estado: inactivos', 'todos' => 'Estado: todos'];
    $roleOptions = $roles->mapWithKeys(fn ($r) => [$r->id => $r->name])->all();
    $hasFilters = $filters['q'] !== '' || $filters['rol'] !== '' || $filters['estado'] !== 'activos';
    $roleColors = ['admin' => 'violet', 'jefe_odontologia' => 'blue', 'odontologo' => 'sky', 'secretaria' => 'amber'];
    $currentId = app(\App\Support\CurrentOrganization::class)->member()?->id;
@endphp

<x-layouts.app title="Usuarios" active="usuarios" :breadcrumbs="['Usuarios' => null]">
    {{-- Encabezado --}}
    <div class="flex items-start justify-between gap-6 pb-5">
        <div class="py-[3px]">
            <h1 class="text-h1 text-slate-900">Gestión de usuarios</h1>
            <p class="pt-[3px] text-body font-semibold text-slate-400">
                {{ $activeCount }} {{ $activeCount === 1 ? 'usuario activo' : 'usuarios activos' }}
            </p>
        </div>
        <div class="flex items-center gap-2.5 p-2.5">
            <x-ui.button variant="outline" size="sm" :href="route('users.pdf', request()->query())">
                <x-ui.icon name="import" :size="24" />
                Descargar PDF
            </x-ui.button>
            <x-ui.button size="lg" x-data x-on:click="$dispatch('open-modal', { name: 'create-user' })">
                <x-ui.icon name="add" :size="16" />
                Nuevo usuario
            </x-ui.button>
        </div>
    </div>

    <div class="overflow-hidden rounded-[10px] bg-white">
        {{-- Barra de búsqueda y filtros --}}
        <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-center gap-2.5 px-4 py-3.5" x-data>
            <label class="flex h-9 w-[349px] items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3.5 focus-within:border-primary-600 focus-within:ring-2 focus-within:ring-primary-600/15">
                <x-ui.icon name="search" :size="24" />
                <span class="sr-only">Buscar</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por nombre o correo..."
                    class="h-full w-full border-0 bg-transparent px-2 text-body-md text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">
            </label>
            <x-ui.select name="rol" compact :options="$roleOptions" placeholder="Todos los roles" :value="$filters['rol']"
                aria-label="Filtrar por rol" x-on:change="$el.form.submit()" />
            <x-ui.select name="estado" compact :options="$estadoOptions" :value="$filters['estado']"
                aria-label="Filtrar por estado" x-on:change="$el.form.submit()" />
            @if ($hasFilters)
                <a href="{{ route('users.index') }}" class="text-body-md font-semibold text-primary-600 hover:underline">Limpiar filtros</a>
            @endif
        </form>

        {{-- Tabla --}}
        <table class="w-full table-fixed border-collapse">
            <colgroup>
                <col class="w-[36%]"><col class="w-[20%]"><col class="w-[17%]"><col class="w-[11%]"><col class="w-[16%]">
            </colgroup>
            <thead>
                <tr class="border-y border-slate-200 bg-slate-50 text-left">
                    @foreach (['Usuario', 'Rol', 'Último acceso', 'Estado', 'Acciones'] as $heading)
                        <th scope="col" class="px-4 py-[11px] text-[12px] font-semibold uppercase text-slate-400">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $member)
                    @php $data = $member->toUserArray(); @endphp
                    <tr class="group h-[53px] border-b border-slate-200 bg-white transition-colors hover:bg-slate-50 focus-within:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <x-ui.avatar :initials="Ui::initials($member->user->full_name)" :color="$member->is_active ? Ui::colorFor($member->id) : 'slate'" />
                                <div class="min-w-0 leading-5">
                                    <p class="truncate text-body-md font-semibold text-black">
                                        {{ $member->user->full_name }}
                                        @if ($member->id === $currentId)
                                            <span class="text-[11px] font-semibold text-slate-400">(tú)</span>
                                        @endif
                                    </p>
                                    <p class="truncate text-[12px] text-slate-500">{{ $member->user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($member->role)
                                <x-ui.badge :color="$roleColors[$member->role->code] ?? 'slate'">{{ $member->role->name }}</x-ui.badge>
                            @else
                                <span class="text-body text-slate-400">Sin rol</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-[12.5px] text-black">
                            {{ $member->user->last_login_at ? $member->user->last_login_at->format('d/m/Y H:i') : 'Nunca' }}
                        </td>
                        <td class="px-4 py-3"><x-ui.status-badge :status="$member->is_active ? 'active' : 'inactive'" /></td>
                        <td class="px-4 py-2">
                            {{-- TableRow state=hover: las acciones aparecen al pasar el mouse --}}
                            <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100" x-data>
                                <button type="button" title="Editar" aria-label="Editar a {{ $member->user->full_name }}"
                                    class="shrink-0 rounded-md hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                    x-on:click="$dispatch('open-modal', { name: 'edit-user', user: @js($data) })">
                                    <x-ui.icon name="action-edit" :size="30" />
                                </button>
                                @if ($data['can_reset'])
                                    <button type="button" title="Restablecer contraseña" aria-label="Restablecer la contraseña de {{ $member->user->full_name }}"
                                        class="shrink-0 rounded-md px-1.5 py-1 text-[12px] font-semibold text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                        x-on:click="$dispatch('open-modal', { name: 'reset-password', user: @js($data) })">
                                        Contraseña
                                    </button>
                                @endif
                                @if ($member->is_active)
                                    @if ($data['can_deactivate'])
                                        <button type="button" title="Desactivar" aria-label="Desactivar a {{ $member->user->full_name }}"
                                            class="shrink-0 rounded-md hover:bg-danger-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-danger-500/50"
                                            x-on:click="$dispatch('open-modal', { name: 'deactivate-user', user: @js($data) })">
                                            <x-ui.icon name="action-delete" :size="30" />
                                        </button>
                                    @endif
                                @else
                                    <button type="button" class="shrink-0 rounded-md px-2 py-1 text-[12px] font-semibold text-primary-600 hover:bg-primary-50"
                                        x-on:click="$dispatch('open-modal', { name: 'reactivate-user', user: @js($data) })">
                                        Reactivar
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-16 text-center">
                            <p class="text-body font-semibold text-slate-900">
                                {{ $filters['q'] !== '' ? 'No se encontró ningún usuario' : 'No hay usuarios para mostrar' }}
                            </p>
                            <p class="mt-1 text-body-md text-slate-500">Ajusta los filtros o registra un nuevo usuario.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pie --}}
        <div class="flex items-center justify-between gap-4 px-3 py-4">
            <p class="text-[12px] text-slate-600">
                @if ($members->total())
                    Mostrando {{ $members->firstItem() }}-{{ $members->lastItem() }} de {{ $members->total() }} {{ $members->total() === 1 ? 'usuario' : 'usuarios' }}
                @endif
            </p>
            {{ $members->links() }}
        </div>
    </div>

    @push('modals')
        @include('users.partials.modals')
    @endpush
</x-layouts.app>
