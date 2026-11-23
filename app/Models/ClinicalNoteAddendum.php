<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Adenda de una nota firmada: la nota no cambia, se agrega una versión con su motivo. */
class ClinicalNoteAddendum extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $table = 'clinical_note_addenda';

    const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'note_id', 'version_number', 'content', 'reason', 'author_member_id', 'content_hash',
    ];

    protected $casts = [
        'version_number' => 'integer',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(ClinicalNote::class, 'note_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'author_member_id');
    }
}
