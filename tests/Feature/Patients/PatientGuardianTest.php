<?php

namespace Tests\Feature\Patients;

use App\Models\PatientGuardian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientGuardianTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return ['full_name' => 'laura soto', 'relationship' => 'madre', 'phone' => '55 8765 4321', 'email' => '', ...$overrides];
    }

    public function test_adds_contacts_and_first_one_is_primary(): void
    {
        $member = $this->member();
        $patient = $this->createPatient($member);

        $this->actingAsMember($member)->post("/pacientes/{$patient->id}/contactos", $this->payload())
            ->assertRedirect("/pacientes/{$patient->id}")
            ->assertSessionHas('toast.title', 'Contacto guardado');
        $this->actingAsMember($member)->post("/pacientes/{$patient->id}/contactos", $this->payload(['full_name' => 'arturo dominguez', 'relationship' => 'padre']));

        $guardians = PatientGuardian::orderBy('full_name')->get();
        $this->assertCount(2, $guardians);
        $this->assertSame('LAURA SOTO', $guardians[1]->full_name);
        $this->assertSame('5587654321', $guardians[1]->phone);
        $this->assertTrue($guardians[1]->is_primary);
        $this->assertFalse($guardians[0]->is_primary);

        $this->actingAsMember($member)->get("/pacientes/{$patient->id}")
            ->assertSee('LAURA SOTO')->assertSee('Madre')->assertSee('55 8765 4321');
    }

    public function test_shows_empty_state_without_contacts(): void
    {
        $member = $this->member();
        $patient = $this->createPatient($member);

        $this->actingAsMember($member)->get("/pacientes/{$patient->id}")->assertSee('No hay contactos registrados');
    }

    public function test_validates_contact_fields(): void
    {
        $member = $this->member();
        $patient = $this->createPatient($member);

        $this->actingAsMember($member)
            ->post("/pacientes/{$patient->id}/contactos", $this->payload(['full_name' => '', 'relationship' => 'vecino', 'phone' => '123']))
            ->assertSessionHasErrors(['full_name', 'relationship', 'phone'], null, 'createGuardian');
    }

    public function test_updates_and_deletes_contact_promoting_a_new_primary(): void
    {
        $member = $this->member();
        $patient = $this->createPatient($member);
        $this->actingAsMember($member)->post("/pacientes/{$patient->id}/contactos", $this->payload());
        $this->actingAsMember($member)->post("/pacientes/{$patient->id}/contactos", $this->payload(['full_name' => 'otro contacto', 'relationship' => 'otro']));
        $primary = PatientGuardian::where('is_primary', true)->sole();

        $this->actingAsMember($member)
            ->put("/pacientes/{$patient->id}/contactos/{$primary->id}", $this->payload(['phone' => '5500001111']))
            ->assertSessionHas('toast.title', 'Contacto modificado');
        $this->assertSame('5500001111', $primary->fresh()->phone);

        $this->actingAsMember($member)
            ->delete("/pacientes/{$patient->id}/contactos/{$primary->id}")
            ->assertSessionHas('toast.title', 'Contacto borrado');

        $this->assertNull(PatientGuardian::find($primary->id));
        $this->assertTrue(PatientGuardian::sole()->is_primary);
    }

    public function test_contact_must_belong_to_the_patient_in_the_url(): void
    {
        $member = $this->member();
        $patientA = $this->createPatient($member);
        $patientB = $this->createPatient($member);
        $this->actingAsMember($member)->post("/pacientes/{$patientA->id}/contactos", $this->payload());
        $guardian = PatientGuardian::sole();

        $this->actingAsMember($member)->delete("/pacientes/{$patientB->id}/contactos/{$guardian->id}")->assertNotFound();
        $this->assertNotNull($guardian->fresh());
    }
}
