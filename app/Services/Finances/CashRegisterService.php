<?php

namespace App\Services\Finances;

use App\Enums\ActiveStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CashRegister;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** RF-06: cajas por sucursal. Se desactivan, no se borran. */
class CashRegisterService
{
    public function __construct(private AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data): CashRegister
    {
        return DB::transaction(function () use ($data) {
            $register = CashRegister::create(Arr::only($data, ['branch_id', 'name']));
            $this->audit->log($register, AuditLogger::INSERT, ['name' => $register->name, 'branch_id' => $register->branch_id]);

            return $register;
        });
    }

    /** Solo cambia el nombre: la sucursal de una caja no se mueve. */
    public function rename(CashRegister $register, string $name): CashRegister
    {
        return DB::transaction(function () use ($register, $name) {
            $register->name = $name;
            if ($register->isDirty('name')) {
                $register->save();
                $this->audit->log($register, AuditLogger::UPDATE, ['fields' => ['name']]);
            }

            return $register;
        });
    }

    public function deactivate(CashRegister $register): void
    {
        DB::transaction(function () use ($register) {
            $locked = CashRegister::whereKey($register->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->openSession()->exists()) {
                throw new BusinessRuleException('No se puede desactivar una caja con una sesión abierta; haz el corte primero.');
            }

            $this->changeStatus($register, ActiveStatus::Inactive);
        });
    }

    public function reactivate(CashRegister $register): void
    {
        DB::transaction(fn () => $this->changeStatus($register, ActiveStatus::Active));
    }

    private function changeStatus(CashRegister $register, ActiveStatus $status): void
    {
        if ($register->status === $status) {
            return;
        }

        $from = $register->status->value;
        $register->update(['status' => $status]);
        $this->audit->log($register, AuditLogger::UPDATE, ['status' => [$from, $status->value]]);
    }
}
