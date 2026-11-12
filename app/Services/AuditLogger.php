<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Bitácora (audit_log). Solo se guardan nombres de campos o valores no sensibles:
 * nunca secretos ni texto clínico.
 */
class AuditLogger
{
    public const INSERT = 'insert';

    public const UPDATE = 'update';

    public const DELETE = 'delete';

    /**
     * @param  array<string, mixed>|null  $changedFields
     */
    public function log(Model $entity, string $action, ?array $changedFields = null): void
    {
        AuditLog::create([
            'organization_id' => $entity->getAttribute('organization_id'),
            'actor_user_id' => Auth::id(),
            'entity_type' => $entity->getTable(),
            'entity_id' => $entity->getKey(),
            'action' => $action,
            'changed_fields' => $changedFields,
        ]);
    }
}
