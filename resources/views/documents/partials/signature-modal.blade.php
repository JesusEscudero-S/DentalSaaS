{{--
    Modal "Registrar firma" de una Autorización de tratamiento pendiente.
    Variables: $document (con patient.guardians), $witnesses (miembros activos)
--}}
@php
    use App\Enums\ConsentMethod;

    $bag = 'registerSignature';
    $patient = $document->patient;
    $people = collect([$patient?->full_name => 'Paciente'])
        ->merge($patient?->guardians->mapWithKeys(fn ($g) => [$g->full_name => $g->relationship?->label() ?? 'Contacto']) ?? [])
        ->filter(fn ($role, $name) => filled($name));
    $initialName = (string) old('accepted_by_name', $patient?->full_name);
    $initialChoice = $people->has($initialName) ? $initialName : '__other__';
    $witnessOptions = $witnesses->mapWithKeys(fn ($m) => [$m->id => $m->displayName()])->all();
    $selectClass = 'w-full appearance-none rounded-lg border border-slate-200 bg-surface py-2 pl-[13px] pr-10 text-body text-slate-900 transition-colors hover:border-slate-300 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600/15';
@endphp

<x-ui.modal name="register-signature" title="Registrar firma" subtitle="Aceptación de la Autorización de Tratamiento"
    :show="$errors->registerSignature->any()" width="max-w-[520px]">
    <form method="POST" action="{{ route('documents.consent.store', $document) }}" enctype="multipart/form-data" class="flex flex-col gap-5" novalidate
        x-data="{ method: @js(old('method', ConsentMethod::ScannedSignature->value)), choice: @js($initialChoice), name: @js($initialName) }">
        @csrf
        <div class="flex flex-col gap-4">
            <x-ui.select name="method" label="Método" required :bag="$bag" :options="ConsentMethod::options()"
                :value="old('method', ConsentMethod::ScannedSignature->value)" x-model="method" />

            <div class="flex flex-col gap-2">
                <label for="accepted_by_choice" class="text-body-md font-medium text-black">Firma o acepta<span class="text-danger-500">*</span></label>
                <div class="relative">
                    <select id="accepted_by_choice" x-model="choice" x-on:change="name = choice === '__other__' ? '' : choice" class="{{ $selectClass }}">
                        @foreach ($people as $name => $role)
                            <option value="{{ $name }}" @selected($initialChoice === $name)>{{ $name }} ({{ $role }})</option>
                        @endforeach
                        <option value="__other__" @selected($initialChoice === '__other__')>Otra persona…</option>
                    </select>
                    <x-ui.icon name="expand-down" :size="24" class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2" />
                </div>
                <div x-show="choice === '__other__'" x-cloak>
                    <x-ui.input name="accepted_by_name" :bag="$bag" maxlength="250" x-model="name" placeholder="Nombre completo de quien firma"
                        aria-label="Nombre de quien firma" class="uppercase placeholder:normal-case" />
                </div>
                <p x-show="choice !== '__other__'" class="text-xs text-slate-500">Si es un menor de edad, firma su padre, madre o tutor.</p>
            </div>

            <x-ui.input name="accepted_at" type="datetime-local" label="Fecha y hora de la firma" required :bag="$bag"
                :value="now()->format('Y-m-d\TH:i')" :max="now()->format('Y-m-d\TH:i')" />

            <div class="flex flex-col gap-1">
                <x-ui.select name="witness_member_id" label="Testigo" :bag="$bag" :options="$witnessOptions" placeholder="Sin testigo" />
                <p class="text-xs text-slate-500" x-show="method === '{{ ConsentMethod::Verbal->value }}'">Obligatorio para la aceptación verbal.</p>
            </div>

            <div class="flex flex-col gap-2">
                <label for="signed_file" class="text-body-md font-medium text-black">
                    Documento firmado (escaneo)<span x-show="method === '{{ ConsentMethod::ScannedSignature->value }}'" class="text-danger-500">*</span>
                </label>
                <input id="signed_file" type="file" name="signed_file" accept=".pdf,.jpg,.jpeg,.png"
                    @error('signed_file', $bag) aria-invalid="true" @enderror
                    class="block w-full rounded-lg border border-slate-200 text-body-md text-slate-600 file:mr-3 file:h-9 file:border-0 file:border-r file:border-slate-200 file:bg-slate-50 file:px-3 file:text-body-md file:font-semibold file:text-slate-700 hover:file:bg-slate-100 aria-[invalid=true]:border-danger-500">
                @error('signed_file', $bag)
                    <p class="text-xs text-danger-600">{{ $message }}</p>
                @else
                    <p class="text-xs text-slate-500">PDF, JPG o PNG de hasta 10 MB.</p>
                @enderror
            </div>

            <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Se guardará la huella SHA-256 del PDF ({{ substr((string) $document->sha256, 0, 12) }}…) como prueba del contenido aceptado.
                Después de registrar la firma, el documento ya no se puede eliminar.
            </p>
        </div>

        <x-ui.modal-footer submit="Registrar firma" />
    </form>
</x-ui.modal>
