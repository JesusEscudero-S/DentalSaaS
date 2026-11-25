{{--
    Campos de "Nuevo usuario" / "Editar usuario" (módulo 1).
    Variables: $bag (error bag), $edit (bool: enlaza con el estado Alpine `form`), $roles.
    La cédula y la especialidad solo aparecen para roles clínicos (necesitan perfil de doctor).
--}}
@php
    $m = fn (string $field) => $edit ? "form.{$field}" : null;
    $roleOptions = $roles->mapWithKeys(fn ($r) => [$r->id => $r->name])->all();
    $doctorRoleIds = $roles->whereIn('code', \App\Services\Users\UserService::DOCTOR_ROLES)->pluck('id')->values();
    $roleModel = $edit ? 'form.role_id' : 'role';
@endphp

<x-ui.input name="full_name" label="Nombre completo" required :bag="$bag" placeholder="Escribe el nombre completo"
    autocomplete="off" maxlength="200" class="uppercase placeholder:normal-case" :x-model="$m('full_name')" />

<div class="grid grid-cols-2 gap-4">
    <x-ui.input name="email" type="email" label="Correo" required :bag="$bag" placeholder="correo@ejemplo.com" maxlength="254"
        autocomplete="off" hint="Con este correo inicia sesión." :x-model="$m('email')" />
    <x-ui.input name="phone" type="tel" label="Teléfono" required :bag="$bag" placeholder="55 0000 0000" inputmode="numeric"
        maxlength="14" :x-model="$m('phone')" />
</div>

<x-ui.select name="role_id" label="Rol" required :bag="$bag" :options="$roleOptions" placeholder="Selecciona un rol"
    :x-model="$roleModel" />

<div class="grid grid-cols-2 gap-4" x-show="@js($doctorRoleIds).includes({{ $roleModel }})" x-cloak>
    <x-ui.input name="professional_license" label="Cédula profesional" :bag="$bag" placeholder="00000000" maxlength="30"
        :x-model="$m('professional_license')" />
    <x-ui.input name="specialty" label="Especialidad" :bag="$bag" placeholder="Odontología general" maxlength="120"
        :x-model="$m('specialty')" />
</div>
