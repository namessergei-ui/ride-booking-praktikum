<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_id', 'status'])]
class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['status' => BookingStatus::class];
    }
}
