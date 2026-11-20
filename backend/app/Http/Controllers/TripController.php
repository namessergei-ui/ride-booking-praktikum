<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchTripsRequest;
use App\Http\Requests\StoreTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripController extends Controller
{
    public function index(SearchTripsRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = Trip::with($this->relations())->where('departure_at', '>', now());

        if (isset($filters['from']) || isset($filters['to'])) {
            $this->filterRoute($query, $filters['from'] ?? null, $filters['to'] ?? null);
        }

        if (isset($filters['date'])) {
            $day = Carbon::createFromFormat('!Y-m-d', $filters['date'], $filters['timezone'] ?? 'Asia/Yekaterinburg')->toImmutable();
            $query->where('departure_at', '>=', $day->utc())
                ->where('departure_at', '<', $day->addDay()->utc());
        }

        foreach (['max_price_kopecks' => ['price_kopecks', '<='], 'seats' => ['available_seats', '>='], 'luggage_allowed' => ['luggage_allowed', '=']] as $filter => [$column, $operator]) {
            if (isset($filters[$filter])) {
                $query->where($column, $operator, $filters[$filter]);
            }
        }

        if (isset($filters['min_rating'])) {
            $query->where(
                fn (QueryBuilder $ratings) => $ratings->from('ratings')->selectRaw('AVG(score)')
                    ->whereColumn('ratings.driver_profile_id', 'trips.driver_profile_id'),
                '>=',
                $filters['min_rating'],
            );
        }

        return TripResource::collection($query->orderBy('departure_at')->orderBy('id')->paginate(20)->withQueryString());
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        $trips = Trip::with($this->relations())
            ->whereRelation('driverProfile', 'user_id', $request->user()->id)
            ->latest('departure_at')
            ->latest('id')
            ->paginate(20);

        return TripResource::collection($trips);
    }

    public function show(Trip $trip): TripResource
    {
        return new TripResource($trip->load($this->relations()));
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $data = $request->validated();

        $trip = DB::transaction(function () use ($request, $data): Trip {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            $driver = $user->driverProfile;
            abort_unless($driver?->vehicle, 403, 'Create a driver profile and vehicle first.');

            if ($data['seats'] > $driver->vehicle->passenger_seats) {
                throw ValidationException::withMessages(['seats' => ['Seats exceed the vehicle capacity.']]);
            }

            $trip = $driver->trips()->create([
                'departure_at' => Carbon::parse($data['departure_at'])->utc(),
                'price_kopecks' => $data['price_kopecks'],
                'total_seats' => $data['seats'],
                'available_seats' => $data['seats'],
                'luggage_allowed' => $data['luggage_allowed'],
            ]);

            $route = [$data['from'], ...($data['stops'] ?? []), $data['to']];
            $trip->stops()->createMany(array_map(
                fn (string $city, int $position): array => compact('city', 'position'),
                $route,
                array_keys($route),
            ));

            return $trip;
        });

        return $this->show($trip->refresh())->response()->setStatusCode(201);
    }

    public function participants(Request $request, Trip $trip): JsonResponse
    {
        abort_unless($trip->hasParticipant($request->user()), 403, 'Trip membership is required.');

        $passengers = $trip->confirmedBookings()
            ->with('passenger:id,name')->orderBy('id')->get()
            ->map(fn (Booking $booking): array => [...$booking->passenger->only('id', 'name'), 'seats' => $booking->seats]);

        return response()->json(['data' => [
            'driver' => $trip->driverProfile->user->only('id', 'name'),
            'passengers' => $passengers,
        ]]);
    }

    private function relations(): array
    {
        return [
            'stops.location',
            'driverProfile' => fn (BelongsTo $driver) => $driver->withAvg('ratings', 'score'),
            'driverProfile.user:id,name',
            'driverProfile.vehicle',
        ];
    }

    private function filterRoute(Builder $query, ?string $from, ?string $to): void
    {
        if ($from === null || $to === null) {
            $query->whereHas('stops', fn (Builder $stops) => $stops->where('city', $from ?? $to));

            return;
        }

        $query->whereExists(fn (QueryBuilder $segment) => $segment
            ->from('trip_stops as origin')
            ->join('trip_stops as destination', 'destination.trip_id', '=', 'origin.trip_id')
            ->whereColumn('origin.trip_id', 'trips.id')
            ->whereColumn('origin.position', '<', 'destination.position')
            ->where('origin.city', $from)
            ->where('destination.city', $to));
    }
}
