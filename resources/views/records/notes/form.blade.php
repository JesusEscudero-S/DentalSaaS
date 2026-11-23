{{--
    Página de una nota clínica (Figma: "Expediente - Nota SOAP").
      - $note = null           → nueva nota SOAP (POST)
      - $note en borrador      → se edita (PUT, RF 6.5)
      - $note firmada          → solo lectura + firma + adendas
--}}
@php
    use App\Models\ClinicalNote;
    use App\Support\Ui;

    $isNew = $note === null;
    $signed = $note?->isSigned() ?? false;
    $soap = $isNew || $note->isSoap();
    $bag = $isNew ? 'createNote' : 'editNote';

    $doctorOptions = $doctors->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all();
    $treatmentOptions = $treatments->pluck('name', 'id')->all();
    $appointmentOptions = $appointments->mapWithKeys(fn ($a) => [
        $a->id => Ui::date($a->starts_at).' · '.$a->starts_at->format('H:i').' · '.$a->notes.' ('.$a->doctor->displayName().')',
    ])->all();

    $title = $isNew ? 'Nueva nota clínica' : ($soap ? 'Nota SOAP' : 'Nota clínica');
    $subtitle = collect([
        $soap ? 'Formato SOAP' : 'Nota simple',
        $note?->responsibleDoctor?->displayName(),
        $note ? Ui::date($note->encounter->occurred_at) : null,
        $appointment ? 'Cita relacionada: '.$appointment->notes : null,
    ])->filter()->join(' · ');

    // Secciones a mostrar: SOAP = 4; simple = solo la nota (columna objective).
    $sections = $soap ? ClinicalNote::SOAP_SECTIONS : ['objective' => ['Nota', 'Procedimiento y hallazgos.']];
@endphp

<x-records.shell :patient="$patient" :record="$record" :doctors="$doctors" active="notas" :section="$title">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-h1 text-slate-900">{{ $title }}</h2>
            <p class="mt-1 text-body-md text-slate-500">{{ $subtitle }}</p>
        </div>
        @if ($note)
            @if ($signed)
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-[12px] font-semibold text-emerald-700">Firmada</span>
            @else
                <span class="rounded-full bg-amber-50 px-3 py-1 text-[12px] font-semibold text-amber-700">Borrador</span>
            @endif
        @endif
    </div>

    @if (! $signed)
        {{-- ───── Formulario (nueva o borrador) ───── --}}
        <form method="POST" novalidate class="mt-5 grid grid-cols-[minmax(0,1fr)_320px] items-start gap-4"
            action="{{ $isNew ? route('records.notes.store', $patient) : route('records.notes.update', [$patient, $note]) }}">
            @csrf
            @unless ($isNew)
                @method('PUT')
            @endunless
            @if ($isNew)
                <input type="hidden" name="note_type" value="soap">
            @endif

            <x-ui.card class="flex flex-col gap-5 px-5 py-5">
                @foreach ($sections as $field => [$label, $help])
                    <x-ui.textarea :name="$field" :label="$label" :bag="$bag" rows="5" maxlength="5000"
                        :value="$note?->{$field}" :hint="$help" />
                @endforeach
            </x-ui.card>

            <div class="flex flex-col gap-4">
                <x-ui.card class="flex flex-col gap-4 px-5 py-5">
                    <x-ui.select name="doctor_member_id" label="Doctor responsable" required :bag="$bag"
                        :options="$doctorOptions" :value="$defaultDoctorId" placeholder="Selecciona un doctor" />

                    @if ($isNew)
                        <x-ui.input name="occurred_on" type="date" label="Fecha" required :bag="$bag"
                            :value="($appointment?->starts_at ?? today())->toDateString()" :max="today()->toDateString()" />
                        <x-ui.select name="appointment_id" label="Cita relacionada" :bag="$bag" :options="$appointmentOptions"
                            :value="$appointment?->id" placeholder="Sin cita" />
                    @endif

                    @if ($treatmentOptions)
                        <x-ui.select name="treatment_id" label="Tratamiento" :bag="$bag" :options="$treatmentOptions"
                            :value="$note?->treatment_id" placeholder="Selecciona un tratamiento" />
                    @endif

                    <x-ui.input name="tooth_fdi" type="number" label="Pieza dental" :bag="$bag" :value="$note?->tooth_fdi"
                        placeholder="Ej. 26" min="11" max="85" />
                </x-ui.card>

                <div class="flex flex-col gap-2">
                    <x-ui.button type="submit" name="action" value="sign">Firmar nota</x-ui.button>
                    <x-ui.button variant="secondary" type="submit" name="action" value="draft">Guardar borrador</x-ui.button>
                    <x-ui.button variant="ghost" :href="route('records.notes.index', $patient)">Cerrar</x-ui.button>
                    <p class="text-[12px] text-slate-500">Solo el doctor responsable puede firmar. Una nota firmada ya no se modifica: las correcciones se agregan como adenda.</p>
                </div>
            </div>
        </form>
    @else
        {{-- ───── Nota firmada: solo lectura ───── --}}
        <div class="mt-5 grid grid-cols-[minmax(0,1fr)_320px] items-start gap-4">
            <div class="flex flex-col gap-4">
                <x-ui.card class="flex flex-col gap-5 px-5 py-5">
                    @foreach ($sections as $field => [$label])
                        <div>
                            <h3 class="text-body-md font-semibold text-slate-900">{{ $label }}</h3>
                            <p class="mt-1 whitespace-pre-line text-body text-slate-700">{{ $note->{$field} ?: '—' }}</p>
                        </div>
                    @endforeach
                </x-ui.card>

                {{-- Adendas: la nota no cambia; se agregan versiones con su motivo --}}
                <x-ui.card class="px-5 py-5">
                    <h3 class="text-[16px] font-semibold text-slate-900">Adendas</h3>
                    @forelse ($note->addenda as $addendum)
                        <div class="mt-3 border-l-[3px] border-primary-600 pl-3">
                            <p class="text-[12px] text-slate-500">
                                Versión {{ $addendum->version_number }} · {{ Ui::date($addendum->created_at) }} {{ $addendum->created_at->format('H:i') }}
                                · {{ $addendum->author->displayName() }}
                            </p>
                            <p class="mt-1 whitespace-pre-line text-body text-slate-900">{{ $addendum->content }}</p>
                            <p class="mt-1 text-body-md text-slate-500"><span class="font-semibold">Motivo:</span> {{ $addendum->reason }}</p>
                        </div>
                    @empty
                        <p class="mt-2 text-body-md text-slate-500">Sin adendas.</p>
                    @endforelse

                    <form method="POST" action="{{ route('records.notes.addendum', [$patient, $note]) }}" class="mt-5 flex flex-col gap-4 border-t border-slate-200 pt-4" novalidate>
                        @csrf
                        <x-ui.textarea name="content" label="Agregar adenda" required bag="addendum" rows="3" maxlength="5000"
                            placeholder="Texto que complementa o corrige la nota" />
                        <x-ui.input name="reason" label="Motivo" required bag="addendum" maxlength="500"
                            placeholder="Ej. Se omitió la pieza tratada" />
                        <div class="flex justify-end">
                            <x-ui.button type="submit">Agregar adenda</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            </div>

            <x-ui.card class="px-5 py-5">
                <h3 class="text-body-md font-semibold text-slate-900">Firma</h3>
                <dl class="mt-3 grid grid-cols-[100px_1fr] gap-x-3 gap-y-2 text-body-md">
                    <dt class="text-slate-500">Firmó</dt>
                    <dd class="text-slate-900">{{ $note->signedBy->displayName() }}</dd>
                    <dt class="text-slate-500">Fecha</dt>
                    <dd class="text-slate-900">{{ Ui::date($note->signed_at) }} {{ $note->signed_at->format('H:i') }}</dd>
                    <dt class="text-slate-500">Escribió</dt>
                    <dd class="text-slate-900">{{ $note->author->displayName() }}</dd>
                    @if ($note->treatment)
                        <dt class="text-slate-500">Tratamiento</dt>
                        <dd class="text-slate-900">{{ $note->treatment->name }}</dd>
                    @endif
                    @if ($note->tooth_fdi)
                        <dt class="text-slate-500">Pieza</dt>
                        <dd class="text-slate-900">{{ $note->tooth_fdi }}</dd>
                    @endif
                    <dt class="text-slate-500">Huella</dt>
                    <dd class="truncate font-mono text-[11px] text-slate-500" title="{{ $note->content_hash }}">{{ substr($note->content_hash, 0, 16) }}…</dd>
                    <dt class="text-slate-500">Integridad</dt>
                    <dd @class(['font-semibold', 'text-emerald-700' => $note->verifyIntegrity(), 'text-danger-600' => ! $note->verifyIntegrity()])>
                        {{ $note->verifyIntegrity() ? 'Verificada' : 'El contenido no coincide con la firma' }}
                    </dd>
                </dl>
                <div class="mt-5">
                    <x-ui.button variant="secondary" class="w-full" :href="route('records.notes.index', $patient)">Volver a notas</x-ui.button>
                </div>
            </x-ui.card>
        </div>
    @endif
</x-records.shell>
