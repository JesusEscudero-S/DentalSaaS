<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Módulo 7, P8: cada versión del plan apunta a su presupuesto. */
class TreatmentPlanVersion extends Model
{
    use BelongsToOrganization, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = ['organization_id', 'plan_id', 'version', 'quote_id', 'change_reason', 'created_by_user_id'];

    protected $casts = ['version' => 'integer'];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
