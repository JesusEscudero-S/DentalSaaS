<?php

namespace App\Models;

use App\Enums\ConsentMethod;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aceptación de la carta de consentimiento / responsiva (RN-P4). */
class ConsentAcceptance extends Model
{
    use BelongsToOrganization, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'patient_id', 'clinical_file_id', 'accepted_at', 'accepted_by_name',
        'method', 'witness_member_id', 'content_hash',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'method' => ConsentMethod::class,
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
