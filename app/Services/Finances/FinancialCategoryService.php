<?php

namespace App\Services\Finances;

use App\Enums\ActiveStatus;
use App\Models\FinancialCategory;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** RF-05: catálogo de categorías de ingreso/egreso. */
class FinancialCategoryService
{
    public function __construct(private AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data): FinancialCategory
    {
        return DB::transaction(function () use ($data) {
            $category = FinancialCategory::create(Arr::only($data, ['name', 'direction']));
            $this->audit->log($category, AuditLogger::INSERT, ['name' => $category->name, 'direction' => $category->direction->value]);

            return $category;
        });
    }

    /**
     * El tipo solo cambia si la categoría no tiene movimientos (lo valida FinancialCategoryRequest).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(FinancialCategory $category, array $data): FinancialCategory
    {
        return DB::transaction(function () use ($category, $data) {
            $category->fill(Arr::only($data, ['name', 'direction']));
            $changed = array_keys($category->getDirty());
            $category->save();

            if ($changed) {
                $this->audit->log($category, AuditLogger::UPDATE, ['fields' => $changed]);
            }

            return $category;
        });
    }

    public function deactivate(FinancialCategory $category): void
    {
        $this->changeStatus($category, ActiveStatus::Inactive);
    }

    public function reactivate(FinancialCategory $category): void
    {
        $this->changeStatus($category, ActiveStatus::Active);
    }

    private function changeStatus(FinancialCategory $category, ActiveStatus $status): void
    {
        if ($category->status === $status) {
            return;
        }

        DB::transaction(function () use ($category, $status) {
            $from = $category->status->value;
            $category->update(['status' => $status]);
            $this->audit->log($category, AuditLogger::UPDATE, ['status' => [$from, $status->value]]);
        });
    }
}
