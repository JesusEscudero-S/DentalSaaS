{{-- StatusBadge de Figma: status=active | inactive. --}}
@props(['status'])

@php
    $active = ($status instanceof \App\Enums\PatientStatus ? $status->value : $status) === 'active';
@endphp

<span {{ $attributes->class([
    'inline-flex h-[22px] items-center gap-[5px] rounded-full px-[9px] text-caption whitespace-nowrap',
    'bg-emerald-50 text-emerald-700' => $active,
    'bg-slate-100 text-slate-600' => ! $active,
]) }}>
    <span @class(['size-1.5 rounded-full', 'bg-emerald-500' => $active, 'bg-slate-400' => ! $active])></span>
    {{ $active ? 'Activo' : 'Inactivo' }}
</span>
