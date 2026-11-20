<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['passenger_id', 'seats', 'status'])]
class Booking extends Model
{
    protected function casts(): array
    {
        return ['status' => BookingStatus::class];
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class)->orderBy('id');
    }

    public function rating(): HasOne
    {
        return $this->hasOne(Rating::class);
    }
}
