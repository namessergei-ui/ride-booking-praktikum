<?php

namespace App\Http\Resources;

use App\Models\AuditEvent;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Booking */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'passenger_id' => $this->passenger_id,
            'seats' => $this->seats,
            'status' => $this->status->value,
            'created_at' => $this->created_at->toAtomString(),
            'history' => $this->whenLoaded('auditEvents', fn () => $this->auditEvents->map(fn (AuditEvent $event): array => [
                'status' => $event->status->value,
                'actor_id' => $event->actor_id,
                'created_at' => $event->created_at->toAtomString(),
            ])),
        ];
    }
}
