{{--
    PDF "Historia Clínica Dental". Sin $patient ni $answers sale el formato en blanco (vista previa).
    Variables: $patient, $doctor (OrganizationMember), $answers (respuestas.json), $date
--}}
@extends('documents.pdf.layout')

@php
    use App\Enums\ExtractionComplication;
    use App\Enums\MedicalCondition;

    $a = $answers ?? [];
    $mark = fn (bool $checked) => $checked ? '☒' : '☐';
    $yesNo = fn ($value) => $mark($value === true).' Sí   '.$mark($value === false).' No';
    $conditions = $a['conditions'] ?? [];
    $complications = $a['extraction_complications'] ?? [];
    $license = $doctor?->doctorProfile?->professional_license;
@endphp

@section('content')
    <div class="section">
        <div class="section-title">Datos de identificación</div>
        <table class="fields">
            <tr>
                <td class="label">Nombre del paciente</td>
                <td class="line" colspan="3">{{ $patient?->full_name }}</td>
            </tr>
        </table>
        <table class="fields">
            <tr>
                <td class="label">Fecha de nacimiento</td>
                <td class="line">{{ $patient?->birth_date?->format('d/m/Y') }}</td>
                <td class="label">Edad</td>
                <td class="line">{{ $patient?->age !== null ? $patient->age.' años' : '' }}</td>
                <td class="label">Sexo</td>
                <td class="line">{{ $patient?->sex?->label() }}</td>
            </tr>
        </table>
        <table class="fields">
            <tr>
                <td class="label">Teléfono (casa / móvil)</td>
                <td class="line">{{ $patient?->formatted_phone }}</td>
                <td class="label">Ocupación</td>
                <td class="line">{{ $a['occupation'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">Domicilio</td>
                <td class="line" colspan="3">{{ $a['address'] ?? '' }}</td>
            </tr>
        </table>
        <p class="label" style="margin: 6pt 0 2pt;">Motivo principal de la consulta</p>
        <div class="box">{{ $a['chief_complaint'] ?? '' }}</div>
    </div>

    <div class="section">
        <div class="section-title">Antecedentes y hábitos</div>
        <table class="fields">
            <tr>
                <td class="label">¿Experiencia desagradable anterior?</td>
                <td class="check">{{ $yesNo($a['bad_previous_experience'] ?? null) }}</td>
                <td class="label">¿Por qué?</td>
                <td class="line">{{ $a['bad_experience_reason'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">Higiene dental</td>
                <td class="check">
                    {{ $mark(($a['uses_toothbrush'] ?? null) === true) }} Utiliza cepillo &nbsp;
                    {{ $mark(($a['uses_toothbrush'] ?? null) === false) }} No utiliza
                </td>
                <td class="label">Veces al día</td>
                <td class="line">{{ $a['brushings_per_day'] ?? '' }}</td>
            </tr>
        </table>
        <table class="fields">
            <tr>
                <td class="label">Extracciones pasadas (complicaciones)</td>
                @foreach (ExtractionComplication::cases() as $complication)
                    <td class="check">{{ $mark(in_array($complication->value, $complications, true)) }} {{ $complication->label() }}</td>
                @endforeach
                <td></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Historia médica</div>
        <table class="fields">
            <tr>
                <td class="label">¿Está actualmente bajo tratamiento médico? ¿Cuál?</td>
                <td class="line">{{ $a['under_medical_treatment'] ?? '' }}</td>
            </tr>
        </table>
        <p class="label" style="margin: 6pt 0 3pt;">Padecimientos (marque si aplica)</p>
        <table>
            @foreach (array_chunk(MedicalCondition::cases(), 5) as $row)
                <tr>
                    @foreach ($row as $condition)
                        <td class="check" style="padding-bottom: 3pt;">{{ $mark(in_array($condition->value, $conditions, true)) }} {{ $condition->label() }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
        <table class="fields">
            <tr>
                <td class="label">Alergias</td>
                <td class="line">{{ $a['allergies'] ?? '' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Signos vitales</div>
        <table class="fields">
            <tr>
                <td class="label">Presión arterial</td>
                <td class="line">{{ $a['blood_pressure'] ?? '' }}</td>
                <td class="label">mmHg</td>
                <td class="label">Pulso</td>
                <td class="line">{{ $a['pulse'] ?? '' }}</td>
                <td class="label">lpm</td>
                <td class="label">Respiración</td>
                <td class="line">{{ $a['respiration'] ?? '' }}</td>
                <td class="label">rpm</td>
            </tr>
        </table>
    </div>

    <table class="signatures">
        <tr>
            <td style="width: 50%;">
                <div class="signature-line">
                    Paciente o tutor<br>
                    <strong>{{ $patient?->full_name }}</strong>
                </div>
            </td>
            <td style="width: 50%;">
                <div class="signature-line">
                    Doctor responsable<br>
                    <strong>{{ $doctor?->displayName() }}</strong>
                    @if ($license)<br>Céd. Prof. {{ $license }}@endif
                </div>
            </td>
        </tr>
    </table>
@endsection
