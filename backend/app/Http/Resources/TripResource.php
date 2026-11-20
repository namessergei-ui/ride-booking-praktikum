<?php

namespace App\Http\Resources;

use App\Models\Trip;
use App\Models\TripStop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Trip */
class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $driver = $this->driverProfile;
        $vehicle = $driver->vehicle;
        $route = $this->stops->map(fn (TripStop $stop): array => [
            'position' => $stop->position,
            ...$stop->location->only('id', 'name', 'lat', 'lon'),
        ])->all();

        return [
            'id' => $this->id,
            'from' => $route[0]['id'],
            'to' => $route[array_key_last($route)]['id'],
            'route' => $route,
            'departure_at' => $this->departure_at->toAtomString(),
            'price_kopecks' => $this->price_kopecks,
            'total_seats' => $this->total_seats,
            'available_seats' => $this->available_seats,
            'luggage_allowed' => $this->luggage_allowed,
            'driver' => [
                'id' => $driver->user_id,
                'name' => $driver->user->name,
                'experience_years' => $driver->experience_years,
                'rating' => $driver->ratings_avg_score === null ? null : round((float) $driver->ratings_avg_score, 2),
                'vehicle' => $vehicle->only('make', 'model', 'color', 'passenger_seats'),
            ],
        ];
    }
}
