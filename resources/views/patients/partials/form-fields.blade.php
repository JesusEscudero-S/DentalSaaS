{{--
    Campos del modal "Nuevo paciente" / "Editar paciente" (Figma), adaptados a la BD:
    - Nombre completo (patients.full_name), Sexo y Doctor asignado.
    - Datos fiscales: patient_fiscal_profiles exige RFC, razón social, régimen y CP fiscal.
    Variables: $bag (error bag), $edit (bool: enlaza con el estado Alpine `form`), $doctors.
--}}
@php
    $m = fn (string $field) => $edit ? "form.{$field}" : null;
    $doctorOptions = $doctors->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all();
@endphp

<x-ui.input name="full_name" label="Nombre completo" required :bag="$bag" placeholder="Escribe el nombre completo"
    autocomplete="off" maxlength="250" class="uppercase placeholder:normal-case" :x-model="$m('full_name')" />

<div class="grid grid-cols-2 gap-4">
    <x-ui.input name="birth_date" type="date" label="Fecha de nacimiento" required :bag="$bag" :max="now()->toDateString()"
        :x-model="$m('birth_date')" />
    <x-ui.select name="sex" label="Sexo" :bag="$bag" :options="\App\Enums\PatientSex::options()" placeholder="Selecciona una opción"
        :x-model="$m('sex')" />
</div>

<div class="grid grid-cols-2 gap-4">
    <x-ui.input name="phone" type="tel" label="Teléfono" required :bag="$bag" placeholder="55 0000 0000" inputmode="numeric"
        maxlength="14" :x-model="$m('phone')" />
    <x-ui.input name="email" type="email" label="Email" :bag="$bag" placeholder="correo@ejemplo.com" maxlength="254"
        :x-model="$m('email')" />
</div>

<x-ui.select name="assigned_doctor_member_id" label="Doctor asignado" :bag="$bag" :options="$doctorOptions" placeholder="Sin asignar"
    :x-model="$m('assigned_doctor_member_id')" />

<p class="text-body-md font-semibold text-slate-700">Datos fiscales (opcional)</p>

<x-ui.input name="rfc" label="RFC" :bag="$bag" placeholder="XAXX010101000" maxlength="13" class="uppercase placeholder:normal-case"
    :x-model="$m('rfc')" />
<x-ui.input name="legal_name" label="Razón social" :bag="$bag" placeholder="Nombre o razón social" maxlength="300"
    class="uppercase placeholder:normal-case" :x-model="$m('legal_name')" />
<div class="grid grid-cols-[1fr_140px] gap-4">
    <x-ui.select name="tax_regime_code" label="Régimen fiscal" :bag="$bag" :options="\App\Enums\TaxRegime::options()"
        placeholder="Selecciona una opción" :x-model="$m('tax_regime_code')" />
    <x-ui.input name="fiscal_postal_code" label="CP fiscal" :bag="$bag" placeholder="00000" inputmode="numeric" maxlength="5"
        :x-model="$m('fiscal_postal_code')" />
</div>
