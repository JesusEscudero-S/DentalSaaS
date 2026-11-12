<?php

namespace Database\Seeders;

use App\Enums\ConsentMethod;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Models\PatientGuardian;
use App\Services\Patients\PatientService;
use App\Support\CurrentOrganization;
use Illuminate\Database\Seeder;

/** Pacientes de demostración (los de la pantalla de Figma). */
class PatientSeeder extends Seeder
{
    /** [nombre, nacimiento, sexo, teléfono, email, doctor, estado] */
    private const PATIENTS = [
        ['Valeria Dominguez Soto', '1992-03-14', 'female', '5518472093', 'valeria.dominguez@correo.mx', 'Dra. Mariana Reyes', 'active'],
        ['Roberto Mendoza Lira', '1985-07-02', 'male', '5522907741', 'roberto.mendoza@correo.mx', 'Dr. Andrés Salazar', 'active'],
        ['Camila Ortega Ríos', '2018-01-20', 'female', '5566331188', null, 'Dra. Lucía Fonseca', 'active'],
        ['Jorge Salinas Cano', '1969-05-11', 'male', '5540129856', 'jorge.salinas@correo.mx', 'Dr. Hugo Beltrán', 'active'],
        ['Daniela Vargas Pineda', '1997-02-28', 'female', '5577815520', 'daniela.vargas@correo.mx', 'Dra. Mariana Reyes', 'active'],
        ['Emilio Cárdenas Núñez', '1981-09-09', 'male', '5533984471', null, 'Dra. Paola Ríos', 'inactive'],
        ['Fernanda Lozano Cruz', '1988-04-17', 'female', '5555672014', 'fernanda.lozano@correo.mx', 'Dr. Andrés Salazar', 'active'],
        ['Patricio Guerra Méndez', '1974-12-03', 'male', '5511208834', 'patricio.guerra@correo.mx', 'Dr. Hugo Beltrán', 'active'],
        ['Sofía Navarro Trejo', '2001-06-22', 'female', '5521436587', 'sofia.navarro@correo.mx', 'Dra. Lucía Fonseca', 'active'],
        ['Miguel Ángel Ibarra Ruiz', '1990-10-30', 'male', '5532547698', null, 'Dra. Mariana Reyes', 'active'],
        ['Regina Fuentes Ochoa', '2015-08-05', 'female', '5543658709', 'familia.fuentes@correo.mx', 'Dra. Paola Ríos', 'active'],
        ['Tomás Aguilar Peña', '1958-11-19', 'male', '5554769810', 'tomas.aguilar@correo.mx', null, 'active'],
    ];

    public function run(PatientService $service, CurrentOrganization $current): void
    {
        $organization = Organization::where('commercial_name', DemoOrganizationSeeder::ORGANIZATION)->firstOrFail();

        $admin = OrganizationMember::where('organization_id', $organization->id)
            ->whereHas('role', fn ($q) => $q->where('code', 'admin'))
            ->firstOrFail();

        $current->set($admin);

        $doctors = OrganizationMember::doctors()->with('user')->get()->keyBy(fn ($m) => $m->user->full_name);

        foreach (self::PATIENTS as [$name, $birth, $sex, $phone, $email, $doctor, $status]) {
            if (Patient::where('phone', $phone)->exists()) {
                continue;
            }

            $patient = $service->create([
                'full_name' => mb_strtoupper($name),
                'birth_date' => $birth,
                'sex' => $sex,
                'phone' => $phone,
                'email' => $email,
                'assigned_doctor_member_id' => $doctor ? $doctors[$doctor]->id : null,
                'consent_signed_by' => mb_strtoupper($name),
                'consent_method' => ConsentMethod::ScannedSignature->value,
            ]);

            if ($status === 'inactive') {
                $service->deactivate($patient);
            }
        }

        // Contactos de ejemplo para la primera paciente.
        $valeria = Patient::where('phone', '5518472093')->first();
        if ($valeria && $valeria->guardians()->doesntExist()) {
            PatientGuardian::create(['patient_id' => $valeria->id, 'full_name' => 'ARTURO DOMINGUEZ PAZ', 'relationship' => 'padre', 'phone' => '5512345678', 'is_primary' => true]);
            PatientGuardian::create(['patient_id' => $valeria->id, 'full_name' => 'LAURA SOTO VEGA', 'relationship' => 'madre', 'phone' => '5587654321', 'email' => 'laura.soto@correo.mx', 'is_primary' => false]);
        }

        $current->clear();
    }
}
