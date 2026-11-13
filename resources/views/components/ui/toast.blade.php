{{-- Toast de Figma (success / error / info). Lee el flash de sesión "toast". --}}
@php
    $toast = session('toast');
@endphp

@if ($toast)
    @php
        $type = $toast['type'] ?? 'success';
        $iconBg = ['success' => 'bg-emerald-500', 'error' => 'bg-danger-500', 'info' => 'bg-primary-600'][$type] ?? 'bg-primary-600';
    @endphp
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-cloak
        x-transition.opacity.duration.300ms role="status" aria-live="polite"
        class="fixed right-6 top-[76px] z-[60] flex w-[360px] items-start gap-3 rounded-[10px] border border-slate-200 bg-white px-4 py-3 shadow-[0_8px_24px_rgba(15,23,42,0.10)]">
        <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full text-white {{ $iconBg }}">
            @if ($type === 'success')
                <svg class="size-3" viewBox="0 0 12 12" fill="none"><path d="M2.5 6.2 4.8 8.5 9.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
            @elseif ($type === 'error')
                <span class="text-[12px] font-bold leading-none">!</span>
            @else
                <span class="text-[12px] font-bold leading-none">i</span>
            @endif
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-body-md font-bold text-slate-900">{{ $toast['title'] }}</p>
            @if (! empty($toast['message']))
                <p class="truncate text-body-md text-slate-500">{{ $toast['message'] }}</p>
            @endif
        </div>
        <button type="button" x-on:click="show = false" class="text-slate-400 hover:text-slate-600" aria-label="Cerrar notificación">
            <x-ui.icon name="close" :size="16" />
        </button>
    </div>
@endif
