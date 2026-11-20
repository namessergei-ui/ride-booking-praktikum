<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['departure_at', 'price_kopecks', 'total_seats', 'available_seats', 'luggage_allowed'])]
class Trip extends Model
{
    protected function casts(): array
    {
        return ['departure_at' => 'immutable_datetime', 'luggage_allowed' => 'boolean'];
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TripStop::class)->orderBy('position');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function confirmedBookings(): HasMany
    {
        return $this->bookings()->where('status', BookingStatus::Confirmed);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function isDrivenBy(User $user): bool
    {
        return $this->driverProfile->user_id === $user->id;
    }

    public function hasParticipant(User $user): bool
    {
        return $this->isDrivenBy($user) || $this->confirmedBookings()->where('passenger_id', $user->id)->exists();
    }
}
