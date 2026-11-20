<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function request(Trip $trip, User $passenger, int $seats): Booking
    {
        return DB::transaction(function () use ($trip, $passenger, $seats): Booking {
            $lockedTrip = Trip::lockForUpdate()->findOrFail($trip->id);
            abort_if($lockedTrip->isDrivenBy($passenger), 403, 'Drivers cannot book their own trip.');
            abort_if($lockedTrip->departure_at->isPast(), 409, 'This trip has already departed.');
            abort_if($seats > $lockedTrip->available_seats, 409, 'There are not enough available seats.');

            $hasActiveBooking = $lockedTrip->bookings()
                ->where('passenger_id', $passenger->id)
                ->whereIn('status', [BookingStatus::Requested, BookingStatus::Confirmed])
                ->exists();

            abort_if($hasActiveBooking, 409, 'You already have an active booking for this trip.');

            $booking = $lockedTrip->bookings()->create([
                'passenger_id' => $passenger->id,
                'seats' => $seats,
                'status' => BookingStatus::Requested,
            ]);

            $this->audit($booking, $passenger);

            return $booking->load('auditEvents');
        });
    }

    public function transition(Trip $trip, Booking $booking, User $actor, BookingStatus $target): Booking
    {
        return DB::transaction(function () use ($trip, $booking, $actor, $target): Booking {
            $lockedTrip = Trip::lockForUpdate()->findOrFail($trip->id);
            $lockedBooking = $lockedTrip->bookings()->lockForUpdate()->findOrFail($booking->id);

            $authorized = match ($target) {
                BookingStatus::Cancelled => $lockedBooking->passenger_id === $actor->id,
                BookingStatus::Confirmed, BookingStatus::Rejected => $lockedTrip->isDrivenBy($actor),
                BookingStatus::Requested => false,
            };

            abort_unless($authorized, 403, 'You cannot perform this booking transition.');

            if ($lockedBooking->status === $target) {
                return $lockedBooking->load('auditEvents');
            }

            abort_unless($lockedBooking->status === BookingStatus::Requested, 409, 'This booking has already reached a final status.');

            if ($target === BookingStatus::Confirmed) {
                abort_if($lockedTrip->departure_at->isPast(), 409, 'This trip has already departed.');
                abort_if($lockedBooking->seats > $lockedTrip->available_seats, 409, 'There are not enough available seats.');
                $lockedTrip->decrement('available_seats', $lockedBooking->seats);
            }

            $lockedBooking->update(['status' => $target]);
            $this->audit($lockedBooking, $actor);

            return $lockedBooking->load('auditEvents');
        });
    }

    private function audit(Booking $booking, User $actor): void
    {
        $booking->auditEvents()->create(['actor_id' => $actor->id, 'status' => $booking->status]);
    }
}
