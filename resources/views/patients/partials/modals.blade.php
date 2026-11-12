{{--
    Overlays de Figma: "Nuevo paciente", "Editar paciente" y "Eliminar paciente"
    (este último adaptado a baja lógica: RN-P3 → "Desactivar paciente").
    Variables: $doctors, $withCreate (bool)
--}}
@php
    $editErrors = $errors->editPatient->any();
    $editInitial = $editErrors
        ? collect(['id', 'folio', 'full_name', 'birth_date', 'sex', 'phone', 'email', 'assigned_doctor_member_id', 'rfc', 'legal_name', 'tax_regime_code', 'fiscal_postal_code'])
            ->mapWithKeys(fn ($f) => [$f => old(in_array($f, ['id', 'folio']) ? "_{$f}" : $f, '')])->all()
        : [];
    $editAction = $editErrors && old('_id') ? route('patients.update', old('_id')) : '';
@endphp

@if ($withCreate ?? false)
    @can('pacientes.crear')
        <x-ui.modal name="create-patient" title="Nuevo paciente" subtitle="Datos personales y de contacto" :show="$errors->createPatient->any()">
            <form method="POST" action="{{ route('patients.store') }}" class="flex flex-col gap-5" novalidate>
                @csrf
                <div class="flex flex-col gap-4">
                    @include('patients.partials.form-fields', ['bag' => 'createPatient', 'edit' => false])

                    {{-- RN-P4: responsiva obligatoria (consent_acceptances) --}}
                    <div class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <p class="text-body-md font-semibold text-slate-700">Responsiva</p>
                        <x-ui.checkbox name="consent_signed" bag="createPatient"
                            label="El paciente (o su tutor) firmó la carta de consentimiento / responsiva." />
                        <div class="grid grid-cols-[1fr_180px] gap-4">
                            <x-ui.input name="consent_signed_by" label="Firmó" bag="createPatient" maxlength="250"
                                placeholder="Nombre del paciente o tutor" class="uppercase placeholder:normal-case"
                                hint="Si se deja vacío, se registra el nombre del paciente." />
                            <x-ui.select name="consent_method" label="Método" required bag="createPatient"
                                :options="\App\Enums\ConsentMethod::options()" :value="\App\Enums\ConsentMethod::ScannedSignature->value" />
                        </div>
                    </div>
                </div>
                <x-ui.modal-footer />
            </form>
        </x-ui.modal>
    @endcan
@endif

@can('pacientes.editar')
    <x-ui.modal name="edit-patient" title="Editar paciente" :show="$editErrors">
        <x-slot:sub><span x-text="payload.patient?.folio ?? @js(old('_folio', ''))"></span></x-slot:sub>

        <form method="POST" :action="action" class="flex flex-col gap-5" novalidate
            x-data="{ form: @js((object) $editInitial), action: @js($editAction) }"
            x-on:open-modal.window="if ($event.detail.name === 'edit-patient') { form = { ...$event.detail.patient }; action = $event.detail.action }">
            @csrf
            @method('PUT')
            <input type="hidden" name="_id" x-model="form.id">
            <input type="hidden" name="_folio" x-model="form.folio">
            <div class="flex flex-col gap-4">
                @include('patients.partials.form-fields', ['bag' => 'editPatient', 'edit' => true])
            </div>
            <x-ui.modal-footer />
        </form>
    </x-ui.modal>
@endcan

@can('pacientes.desactivar')
    <x-ui.modal name="deactivate-patient" title="¿Desactivar paciente?" width="max-w-[420px]">
        <p class="text-body text-slate-600">
            <span class="font-semibold text-slate-900" x-text="payload.label"></span>
            dejará de aparecer en las búsquedas y en la Agenda. Su expediente, citas y pagos se conservan y podrás reactivarlo después.
        </p>
        <form method="POST" :action="payload.action">
            @csrf
            @method('PATCH')
            <x-ui.modal-footer type="danger" submit="Desactivar" />
        </form>
    </x-ui.modal>
@endcan
