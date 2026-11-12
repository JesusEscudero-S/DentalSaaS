<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToOrganization;

    protected $table = 'audit_log';

    const UPDATED_AT = null;

    protected $fillable = ['organization_id', 'actor_user_id', 'entity_type', 'entity_id', 'action', 'changed_fields'];

    protected $casts = ['changed_fields' => 'array'];
}
