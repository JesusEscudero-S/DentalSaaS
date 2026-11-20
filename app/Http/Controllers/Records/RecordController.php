<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Models\OrganizationMember;
use App\Models\Patient;
use App\Support\CurrentOrganization;
use Closure;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

/**
 * Base de los controladores del Expediente (módulo 6).
 * Todas las pestañas comparten el encabezado del paciente: shell() arma esos datos.
 */
abstract class RecordController extends Controller
{
    /**
     * Datos del encabezado (x-records.shell): paciente, expediente (alerta RN-E3) y doctores
     * (los usa el modal "Editar datos" del paciente).
     *
     * @return array<string, mixed>
     */
    protected function shell(Patient $patient): array
    {
        $patient->loadMissing(['fiscalProfile', 'assignedDoctor.user']);

        return [
            'patient' => $patient,
            // RN-E1: un expediente por paciente. Los pacientes del seeder ya lo traen; firstOrCreate por si acaso.
            'record' => MedicalRecord::firstOrCreate(['patient_id' => $patient->id]),
            'doctors' => OrganizationMember::doctors()->with('user')->get()->sortBy(fn ($m) => $m->displayName())->values(),
        ];
    }

    /** Miembro (usuario dentro de la clínica) que hace la petición. */
    protected function member(): OrganizationMember
    {
        return app(CurrentOrganization::class)->member();
    }

    /**
     * Ejecuta la acción y convierte las reglas de negocio rotas en un toast de error:
     * - DomainException: reglas del Service (ej. "La nota ya está firmada").
     * - SQLSTATE 45000: reglas de los triggers de la BD (ej. documento firmado inmutable).
     */
    protected function attempt(Closure $action): RedirectResponse
    {
        try {
            return $action();
        } catch (DomainException $e) {
            return $this->error($e->getMessage());
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '45000') {
                return $this->error($e->errorInfo[2] ?? 'La base de datos rechazó el cambio.');
            }
            throw $e;
        }
    }

    protected function error(string $message): RedirectResponse
    {
        return back()->with('toast', ['type' => 'error', 'title' => 'No se pudo completar', 'message' => $message]);
    }

    /** @return array{type: string, title: string, message: string} */
    protected function toast(string $title, string $message = ''): array
    {
        return ['type' => 'success', 'title' => $title, 'message' => $message];
    }
}
