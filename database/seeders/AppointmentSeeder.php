<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\OrganizationMember;
use App\Models\Patient;
use Illuminate\Database\Seeder;

class AppointmentSeeder extends Seeder
{
    public function run(): void
    {
        if (Appointment::withoutGlobalScopes()->exists()) {
            return;
        }

        $branch = Branch::withoutGlobalScopes()->where('is_main', true)->firstOrFail();
        $doctors = OrganizationMember::withoutGlobalScopes()->whereHas('doctorProfile')->get();
        $patients = Patient::withoutGlobalScopes()->where('status', 'active')->get();
        $plan = [
            [0, 9, 60, 'Endodoncia'],
            [0, 11, 45, 'Limpieza'],
            [0, 13, 60, 'Corona'],
            [1, 10, 30, 'Ajuste ortodoncia'],
            [1, 12, 45, 'Resina'],
            [1, 16, 30, 'Valoración'],
            [2, 9, 60, 'Implante'],
            [2, 11, 30, 'Limpieza'],
            [3, 10, 45, 'Periodoncia'],
            [3, 15, 30, 'Sellador'],
            [4, 9, 45, 'Endodoncia'],
            [4, 11, 30, 'Ajuste'],
            [4, 17, 60, 'Corona'],
            [5, 10, 45, 'Limpieza'],
            [5, 12, 30, 'Revisión'],
        ];
        foreach ($plan as $i => [$day, $hour, $minutes, $reason]) {
            $startsAt = now()->startOfWeek()->addDays($day)->setTime($hour, 0);
            $endsAt = $startsAt->copy()->addMinutes($minutes);
            $patient = $patients[$i % $patients->count()];
            $doctor = $doctors[$i % $doctors->count()];
            Appointment::create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'doctor_member_id' => $doctor->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                // Días anteriores: completadas. Hoy y después: pendientes, y una de cada tres confirmada
                // (las de hoy que ya pasaron aparecen con "Retraso", para ver la regla RN-C5).
                'status' => match (true) {
                    $startsAt->lt(today()) => AppointmentStatus::Completada,
                    $startsAt->gt(today()->endOfDay()) && $i % 3 === 0 => AppointmentStatus::Confirmada,
                    default => AppointmentStatus::Pendiente,
                },
                'notes' => $reason,
            ]);

        }

    }
}
