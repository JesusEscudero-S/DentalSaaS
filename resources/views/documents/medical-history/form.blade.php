{{-- Documentos › Historia Clínica Dental (parte3.html). Variables: $patient, $doctors, $source, $form --}}
@php
    use App\Enums\ExtractionComplication;
    use App\Enums\MedicalCondition;
    use App\Enums\PatientSex;
    use App\Support\Ui;

    $bag = 'medicalHistory';
    $hasErrors = $errors->getBag($bag)->any();
    $val = fn (string $key) => old($key, $form[$key] ?? null);
    $yes = fn (string $key) => filter_var(old($key, $form[$key] ?? false), FILTER_VALIDATE_BOOLEAN);
    $list = fn (string $key) => array_values((array) old($key, $form[$key] ?? []));
    $conditions = array_filter(MedicalCondition::cases(), fn ($c) => $c !== MedicalCondition::Pregnancy || $patient->sex === PatientSex::Female);
    $doctorOptions = $doctors->mapWithKeys(fn ($d) => [
        $d->id => $d->displayName().($d->doctorProfile?->professional_license ? ' · Céd. '.$d->doctorProfile->professional_license : ''),
    ])->all();

    $textarea = 'w-full rounded-lg border border-slate-200 bg-surface px-[13px] py-2 text-body text-slate-900 placeholder:text-slate-400 transition-colors hover:border-slate-300 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/15 aria-[invalid=true]:border-danger-500';
    $choice = 'size-4 shrink-0 border-slate-300 text-primary-600 accent-primary-600 focus:ring-2 focus:ring-primary-600/20';
    $choiceLabel = 'inline-flex cursor-pointer items-center gap-2 text-body-md text-slate-900';
    $fieldLabel = 'text-body-md font-medium text-black';
    $section = 'text-overline uppercase text-slate-400';
@endphp

<x-layouts.app title="Historia Clínica Dental" active="documentos"
    :breadcrumbs="['Documentos' => route('documents.index'), 'Generar nuevo' => route('documents.create'), 'Historia clínica' => null]">
    <div class="max-w-4xl">
        <div class="flex items-start justify-between gap-6 pb-5">
            <div class="py-[3px]">
                <h1 class="text-h1 text-slate-900">Historia Clínica Dental</h1>
                <p class="pt-[3px] text-body text-slate-500">Complete los datos de identificación e historia médica del paciente.</p>
            </div>
            <div class="flex shrink-0 items-center gap-2.5 p-2.5">
                <x-ui.button variant="secondary" type="submit" form="medical-history-form"
                    :formaction="route('documents.medical-history.preview')" formtarget="_blank">Vista previa</x-ui.button>
                <x-ui.button size="lg" type="submit" form="medical-history-form">Guardar documento</x-ui.button>
            </div>
        </div>

        @if ($source)
            <div class="mb-4 rounded-lg border border-primary-100 bg-primary-50 px-4 py-3 text-body-md text-primary-800">
                <strong>Nueva versión</strong> de {{ $source->display_name }}. Los datos se precargaron del documento anterior; ese documento no se modifica.
            </div>
        @endif
        @if ($hasErrors)
            <div class="mb-4 rounded-lg border border-danger-500/30 bg-danger-50 px-4 py-3 text-body-md text-danger-700" role="alert">
                Revisa los campos marcados en rojo.
            </div>
        @endif

        <form id="medical-history-form" method="POST" action="{{ route('documents.medical-history.store') }}" class="flex flex-col gap-4" novalidate
            x-data="{ badExperience: @js($yes('bad_previous_experience')), toothbrush: @js($yes('uses_toothbrush')), conditions: @js($list('conditions')) }">
            @csrf
            <input type="hidden" name="patient_id" value="{{ $patient->id }}">
            <input type="hidden" name="previous_document_id" value="{{ $source?->id }}">

            {{-- 1. Datos de identificación --}}
            <x-ui.card class="flex flex-col gap-4 p-6">
                <div class="flex items-center justify-between">
                    <h2 class="{{ $section }}">Datos de identificación</h2>
                    <a href="{{ route('patients.show', $patient) }}" target="_blank" class="text-body-md font-semibold text-primary-600 hover:underline">Editar en Pacientes</a>
                </div>
                <dl class="grid grid-cols-4 gap-3 rounded-lg bg-slate-50 p-4 text-body-md">
                    <div class="col-span-2">
                        <dt class="text-slate-500">Nombre del paciente</dt>
                        <dd class="font-semibold text-slate-900">{{ $patient->full_name }} <span class="font-mono text-[11.5px] text-slate-400">{{ $patient->folio }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Fecha de nacimiento</dt>
                        <dd class="text-slate-900">{{ Ui::date($patient->birth_date) }}@if ($patient->age !== null) · {{ $patient->age }} años @endif</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Sexo · Teléfono</dt>
                        <dd class="text-slate-900">{{ $patient->sex?->label() }} · {{ $patient->formatted_phone }}</dd>
                    </div>
                </dl>
                <div class="grid grid-cols-2 gap-4">
                    <x-ui.input name="address" label="Domicilio" :bag="$bag" maxlength="300" :value="$form['address']" placeholder="Calle, número, colonia" />
                    <x-ui.input name="occupation" label="Ocupación" :bag="$bag" maxlength="120" :value="$form['occupation']" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="chief_complaint" class="{{ $fieldLabel }}">Motivo principal de la consulta<span class="text-danger-500">*</span></label>
                    <textarea id="chief_complaint" name="chief_complaint" rows="2" maxlength="1000" class="{{ $textarea }}"
                        @error('chief_complaint', $bag) aria-invalid="true" @enderror>{{ $val('chief_complaint') }}</textarea>
                    @error('chief_complaint', $bag) <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                </div>
            </x-ui.card>

            {{-- 2. Antecedentes y hábitos --}}
            <x-ui.card class="flex flex-col gap-5 p-6">
                <h2 class="{{ $section }}">Antecedentes y hábitos</h2>
                <div class="grid grid-cols-2 gap-6">
                    <div class="flex flex-col gap-2">
                        <p class="{{ $fieldLabel }}">¿Experiencia desagradable anterior?<span class="text-danger-500">*</span></p>
                        <div class="flex gap-5">
                            <label class="{{ $choiceLabel }}">
                                <input type="radio" name="bad_previous_experience" value="1" class="{{ $choice }}" @checked($yes('bad_previous_experience')) x-on:change="badExperience = true"> Sí
                            </label>
                            <label class="{{ $choiceLabel }}">
                                <input type="radio" name="bad_previous_experience" value="0" class="{{ $choice }}" @checked(! $yes('bad_previous_experience')) x-on:change="badExperience = false"> No
                            </label>
                        </div>
                        <div x-show="badExperience" x-cloak>
                            <x-ui.input name="bad_experience_reason" :bag="$bag" maxlength="500" :value="$form['bad_experience_reason']" placeholder="¿Por qué?" aria-label="¿Por qué fue desagradable?" />
                        </div>
                        @error('bad_previous_experience', $bag) <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-col gap-2">
                        <p class="{{ $fieldLabel }}">Higiene dental<span class="text-danger-500">*</span></p>
                        <div class="flex gap-5">
                            <label class="{{ $choiceLabel }}">
                                <input type="radio" name="uses_toothbrush" value="1" class="{{ $choice }}" @checked($yes('uses_toothbrush')) x-on:change="toothbrush = true"> Utiliza cepillo
                            </label>
                            <label class="{{ $choiceLabel }}">
                                <input type="radio" name="uses_toothbrush" value="0" class="{{ $choice }}" @checked(! $yes('uses_toothbrush')) x-on:change="toothbrush = false"> No utiliza
                            </label>
                        </div>
                        <div x-show="toothbrush" x-cloak class="flex items-start gap-2">
                            <div class="w-24">
                                <x-ui.input name="brushings_per_day" type="number" min="0" max="10" :bag="$bag" :value="$form['brushings_per_day']" aria-label="Veces al día" />
                            </div>
                            <span class="pt-2 text-body-md text-slate-500">veces al día</span>
                        </div>
                        @error('uses_toothbrush', $bag) <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <p class="{{ $fieldLabel }}">Extracciones pasadas (complicaciones)</p>
                    <div class="flex gap-6">
                        @foreach (ExtractionComplication::cases() as $complication)
                            <label class="{{ $choiceLabel }}">
                                <input type="checkbox" name="extraction_complications[]" value="{{ $complication->value }}" class="{{ $choice }} rounded"
                                    @checked(in_array($complication->value, $list('extraction_complications'), true))>
                                {{ $complication->label() }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </x-ui.card>

            {{-- 3. Historia médica --}}
            <x-ui.card class="flex flex-col gap-5 p-6">
                <h2 class="{{ $section }}">Historia médica</h2>
                <div class="flex flex-col gap-2">
                    <label for="under_medical_treatment" class="{{ $fieldLabel }}">¿Está actualmente bajo tratamiento médico?</label>
                    <textarea id="under_medical_treatment" name="under_medical_treatment" rows="2" maxlength="1000" class="{{ $textarea }}"
                        placeholder="Especifique nombre del médico, problemas que padece y medicamentos..."
                        @error('under_medical_treatment', $bag) aria-invalid="true" @enderror>{{ $val('under_medical_treatment') }}</textarea>
                    @error('under_medical_treatment', $bag) <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <p class="{{ $fieldLabel }}">Padecimientos (marque si aplica)</p>
                    <div class="grid grid-cols-5 gap-x-4 gap-y-3 rounded-lg border border-slate-200 p-4">
                        @foreach ($conditions as $condition)
                            <label class="{{ $choiceLabel }}">
                                <input type="checkbox" name="conditions[]" value="{{ $condition->value }}" class="{{ $choice }} rounded" x-model="conditions"
                                    @checked(in_array($condition->value, $list('conditions'), true))>
                                {{ $condition->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('conditions', $bag) <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label for="allergies" class="{{ $fieldLabel }}">
                        Alergias<span x-show="conditions.includes('{{ MedicalCondition::AllergicReactions->value }}')" class="text-danger-500">*</span>
                    </label>
                    <textarea id="allergies" name="allergies" rows="2" maxlength="1000" class="{{ $textarea }}" placeholder="Medicamentos, materiales o alimentos"
                        @error('allergies', $bag) aria-invalid="true" @enderror>{{ $val('allergies') }}</textarea>
                    @error('allergies', $bag)
                        <p class="text-xs text-danger-600">{{ $message }}</p>
                    @else
                        <p class="text-xs text-slate-500">Se precarga del expediente; al guardar, el expediente del paciente se actualiza.</p>
                    @enderror
                </div>
            </x-ui.card>

            {{-- 4. Signos vitales --}}
            <x-ui.card class="flex flex-col gap-4 p-6">
                <h2 class="{{ $section }}">Signos vitales</h2>
                <div class="grid grid-cols-3 gap-4">
                    <x-ui.input name="blood_pressure" label="Presión arterial (mmHg)" :bag="$bag" :value="$form['blood_pressure']" placeholder="Ej. 120/80" />
                    <x-ui.input name="pulse" type="number" label="Pulso (lpm)" :bag="$bag" :value="$form['pulse']" min="30" max="220" placeholder="Ej. 75" />
                    <x-ui.input name="respiration" type="number" label="Respiración (rpm)" :bag="$bag" :value="$form['respiration']" min="5" max="60" placeholder="Ej. 16" />
                </div>
            </x-ui.card>

            {{-- 5. Doctor responsable --}}
            <x-ui.card class="flex flex-col gap-4 p-6">
                <h2 class="{{ $section }}">Doctor responsable</h2>
                <div class="max-w-md">
                    <x-ui.select name="doctor_member_id" label="Doctor" required :bag="$bag" :options="$doctorOptions" :value="$form['doctor_member_id']" placeholder="Selecciona un doctor" />
                </div>
            </x-ui.card>

            <div class="flex items-center justify-end gap-3 pb-6">
                <x-ui.button variant="ghost" :href="route('documents.create', ['paciente' => $patient->id])">Cancelar</x-ui.button>
                <x-ui.button variant="secondary" type="submit" :formaction="route('documents.medical-history.preview')" formtarget="_blank">Vista previa</x-ui.button>
                <x-ui.button size="lg" type="submit">Guardar documento</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.app>
