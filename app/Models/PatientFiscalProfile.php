<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientFiscalProfile extends Model
{
    use BelongsToOrganization, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'patient_id', 'rfc', 'legal_name', 'tax_regime_code',
        'fiscal_postal_code', 'default_cfdi_use', 'is_default',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
