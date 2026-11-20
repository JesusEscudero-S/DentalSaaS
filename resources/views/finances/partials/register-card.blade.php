{{--
    Tarjeta de una caja en Finanzas › Caja.
    Variables: $register (con branch y openSession.openedBy), $expectedCash (efectivo esperado de la sesión abierta)
--}}
@php
    use App\Support\Ui;

    $session = $register->openSession;
    $active = $register->isActive();
    $menuItem = 'block w-full px-3 py-2 text-left text-body-md hover:bg-slate-50 focus:bg-slate-50 focus:outline-none';
@endphp

<x-ui.card :class="'flex flex-col gap-4 p-5'.($active ? '' : ' opacity-70')">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-body font-semibold text-slate-900">{{ $register->name }}</p>
            <div class="mt-1.5">
                @if (! $active)
                    <x-ui.badge>Inactiva</x-ui.badge>
                @elseif ($session)
                    <x-ui.badge color="green">Abierta</x-ui.badge>
                @else
                    <x-ui.badge>Cerrada</x-ui.badge>
                @endif
            </div>
        </div>

        {{-- Menú de la tarjeta --}}
        <div class="relative" x-data="{ menu: false }" x-on:click.outside="menu = false" x-on:keydown.escape="menu = false">
            <button type="button" x-on:click="menu = ! menu" :aria-expanded="menu" aria-label="Opciones de {{ $register->name }}"
                class="flex size-8 items-center justify-center rounded-md text-[18px] leading-none text-slate-500 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">⋯</button>
            <div x-show="menu" x-cloak x-transition.opacity.duration.100ms
                class="absolute right-0 z-10 mt-1 w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-[0_8px_24px_rgba(15,23,42,0.10)]">
                <button type="button" class="{{ $menuItem }} text-slate-700"
                    x-on:click="menu = false; $dispatch('open-modal', { name: 'edit-register', register: @js(['id' => $register->id, 'name' => $register->name, 'branch' => $register->branch?->name]), action: @js(route('finances.cash.registers.update', $register)) })">
                    Editar nombre
                </button>
                @if (! $active)
                    <form method="POST" action="{{ route('finances.cash.registers.reactivate', $register) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="{{ $menuItem }} text-primary-600">Reactivar</button>
                    </form>
                @elseif ($session)
                    <span class="block cursor-not-allowed px-3 py-2 text-body-md text-slate-300" title="Haz el corte antes de desactivarla">Desactivar</span>
                @else
                    <button type="button" class="{{ $menuItem }} text-danger-600"
                        x-on:click="menu = false; $dispatch('open-modal', { name: 'deactivate-register', label: @js($register->name), action: @js(route('finances.cash.registers.deactivate', $register)) })">
                        Desactivar
                    </button>
                @endif
            </div>
        </div>
    </div>

    @if ($session)
        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-body-md">
            <dt class="text-slate-500">Abrió</dt>
            <dd class="truncate text-right text-slate-900">{{ $session->openedBy?->full_name ?? '—' }}</dd>
            <dt class="text-slate-500">Desde</dt>
            <dd class="text-right text-slate-900">{{ Ui::date($session->opened_at) }}, {{ $session->opened_at->format('H:i') }}</dd>
            <dt class="text-slate-500">Apertura</dt>
            <dd class="text-right font-mono text-slate-900">{{ Ui::money($session->opening_amount) }}</dd>
        </dl>
        <div class="rounded-lg bg-slate-50 px-4 py-3">
            <p class="text-overline uppercase text-slate-400">Efectivo esperado</p>
            <p class="mt-0.5 font-mono text-[20px] font-bold leading-7 text-slate-900"><x-finances.money :amount="$expectedCash" /></p>
        </div>
        <div class="flex gap-2">
            <x-ui.button variant="secondary" size="sm" class="flex-1" :href="route('finances.cash.sessions.show', $session)">Ver sesión</x-ui.button>
            <span class="flex flex-1" x-data
                x-on:click="$dispatch('open-modal', { name: 'close-session', id: @js($session->id), label: @js($register->name), expected: @js($expectedCash), action: @js(route('finances.cash.close', $session)) })">
                <x-ui.button size="sm" class="w-full">Hacer corte</x-ui.button>
            </span>
        </div>
    @elseif ($active)
        <p class="text-body-md text-slate-500">Sin sesión abierta.</p>
        <span class="mt-auto flex" x-data
            x-on:click="$dispatch('open-modal', { name: 'open-session', id: @js($register->id), label: @js($register->name), action: @js(route('finances.cash.open', $register)) })">
            <x-ui.button size="sm" class="w-full">Abrir caja</x-ui.button>
        </span>
    @else
        <p class="text-body-md text-slate-500">Caja inactiva. Reactívala desde el menú para poder abrirla.</p>
    @endif
</x-ui.card>
