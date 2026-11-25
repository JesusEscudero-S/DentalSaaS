<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Módulo 7, RN-T4: corrección de una sesión (la sesión no se edita ni se borra). */
class TreatmentSessionNote extends Model
{
    use BelongsToOrganization, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = ['organization_id', 'session_id', 'note', 'author_user_id'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
