<?php

namespace Database\Seeders;

use App\Enums\NoteType;
use App\Enums\ToothCondition;
use App\Models\ClinicalNote;
use App\Models\MedicalRecord;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Services\Records\NoteService;
use App\Services\Records\OdontogramService;
use App\Support\CurrentOrganization;
use Illuminate\Database\Seeder;

/**
 * Datos de demo del Expediente (módulo 6) con el ejemplo de Figma: Valeria Dominguez Soto.
 * Usa los servicios reales, así las notas quedan firmadas con su hash y el odontograma con su historia.
 */
class RecordSeeder extends Seeder
{
    public function run(): void
    {
        if (ClinicalNote::withoutGlobalScopes()->exists()) {
            return;
        }

        $doctors = OrganizationMember::doctors()->with('user')->get()->keyBy(fn ($m) => $m->user->full_name);
        $mariana = $doctors['Dra. Mariana Reyes'];
        $lucia = $doctors['Dra. Lucía Fonseca'];
        $andres = $doctors['Dr. Andrés Salazar'];

        $valeria = Patient::where('folio', 'PAC-0001')->firstOrFail();
        $roberto = Patient::where('folio', 'PAC-0002')->firstOrFail();

        $notes = app(NoteService::class);
        $odontogram = app(OdontogramService::class);

        // Los modelos toman la clínica del contexto (como en una petición web).
        $context = app(CurrentOrganization::class);
        $context->set($lucia);

        // RN-E3: Valeria tiene alergia → alerta roja en su expediente.
        MedicalRecord::where('patient_id', $valeria->id)->update([
            'allergies' => 'Penicilina',
            'medical_history' => 'Sin enfermedades crónicas. Bruxismo nocturno.',
        ]);

        // Notas de Figma (firmadas: cada doctor firma la suya)
        $notes->create($valeria, [
            'note_type' => NoteType::Simple->value,
            'doctor_member_id' => $lucia->id,
            'occurred_on' => today()->subDays(28)->toDateString(),
            'tooth_fdi' => 26,
            'objective' => 'Valoración inicial. Caries interproximal en la pieza 26; se planea resina.',
        ], $lucia, sign: true, errorBag: 'seed');

        $context->set($mariana);
        $notes->create($valeria, [
            'note_type' => NoteType::Simple->value,
            'doctor_member_id' => $mariana->id,
            'occurred_on' => today()->subDays(20)->toDateString(),
            'objective' => 'Limpieza dental y revisión general. Sin hallazgos relevantes. Se programa resina en la pieza 26.',
        ], $mariana, sign: true, errorBag: 'seed');

        // Odontograma de Figma: 26 caries, 14 ausente, 47 restauración, 36 endodoncia
        $odontogram->record($valeria, 26, ToothCondition::Caries, ['M', 'O'], $mariana);
        $odontogram->record($valeria, 14, ToothCondition::Ausente, [], $mariana);
        $odontogram->record($valeria, 47, ToothCondition::Restauracion, ['O'], $mariana);
        $odontogram->record($valeria, 36, ToothCondition::Endodoncia, [], $mariana);

        // Flúor: dos aplicaciones el año pasado y una este año
        foreach ([today()->subYear()->setMonth(3)->setDay(10), today()->subYear()->setMonth(9)->setDay(15), today()->startOfYear()->addDays(40)] as $date) {
            if ($date->lte(today())) {
                $odontogram->addFluoride($valeria, ['applied_on' => $date->toDateString()], $mariana);
            }
        }

        // Un borrador SOAP de otro doctor, para ver el flujo de firma
        $context->set($andres);
        $notes->create($roberto, [
            'note_type' => NoteType::Soap->value,
            'doctor_member_id' => $andres->id,
            'occurred_on' => today()->subDays(3)->toDateString(),
            'subjective' => 'Refiere molestia al masticar del lado derecho.',
            'objective' => 'Brackets en buen estado; arco superior con ligadura suelta en 15.',
            'analysis' => null,
            'plan' => null,
        ], $andres, sign: false, errorBag: 'seed');

        $context->clear();
    }
}
