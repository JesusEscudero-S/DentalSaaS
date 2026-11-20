<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\belongsTo;

class AppointmentStatusHistory extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $table = 'appointment_status_history';

    const UPDATED_AT = null;

    protected $fillable = ['organization_id', 'appointment_id', 'from_status', 'to_status', 'old_starts_at', 'new_starts_at', 'reason', 'actor_user_id'];

    protected $casts = [
        'from_status' => AppointmentStatus::class,
        'to_status' => AppointmentStatus::class,
        'old_starts_at' => 'datetime',
        'new_starts_at' => 'datetime',
    ];

    public function appointment(): belongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
