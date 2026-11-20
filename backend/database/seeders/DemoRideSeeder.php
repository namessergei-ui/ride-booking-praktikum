<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\DriverProfile;
use App\Models\Trip;
use App\Models\User;
use App\Services\BookingService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class DemoRideSeeder extends Seeder
{
    private const PASSWORD = 'RideBooking-demo-2026';

    public function run(BookingService $bookings): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo data may only be seeded in a local or testing environment.');
        }

        DB::transaction(function () use ($bookings): void {
            $driver = User::createOrFirst(
                ['email' => 'driver@ride-booking.example'],
                ['name' => 'Анна, водитель', 'password' => self::PASSWORD],
            );
            if (! $driver->wasRecentlyCreated) {
                return;
            }

            $passenger = User::create([
                'name' => 'Михаил, пассажир',
                'email' => 'passenger@ride-booking.example',
                'password' => self::PASSWORD,
            ]);
            $requester = User::create([
                'name' => 'Ольга, пассажир',
                'email' => 'requester@ride-booking.example',
                'password' => self::PASSWORD,
            ]);

            $profile = $driver->driverProfile()->create(['experience_years' => 7]);
            $profile->vehicle()->create([
                'make' => 'Lada', 'model' => 'Vesta', 'color' => 'серебристый', 'passenger_seats' => 3,
            ]);

            $upcoming = $this->trip($profile, ['ekaterinburg', 'rezh', 'alapaevsk'], now()->addDays(3), 45000, 3, 3);
            $confirmed = $bookings->request($upcoming, $passenger, 1);
            $bookings->transition($upcoming, $confirmed, $driver, BookingStatus::Confirmed);
            $bookings->request($upcoming, $requester, 2);
            $upcoming->messages()->create(['author_id' => $driver->id, 'body' => 'Встречаемся у главного входа на вокзал.']);

            $past = $this->trip($profile, ['ekaterinburg', 'nevyansk', 'nizhny-tagil'], now()->subDays(3), 39000, 2, 1);
            $requestedAt = $past->departure_at->subDay();
            $confirmedAt = $past->departure_at->subHours(12);

            $booking = $past->bookings()->make([
                'passenger_id' => $passenger->id, 'seats' => 1, 'status' => BookingStatus::Confirmed,
            ]);
            $booking->forceFill(['created_at' => $requestedAt, 'updated_at' => $confirmedAt])->save();
            $booking->auditEvents()->make(['actor_id' => $passenger->id, 'status' => BookingStatus::Requested])
                ->forceFill(['created_at' => $requestedAt])->save();
            $booking->auditEvents()->make(['actor_id' => $driver->id, 'status' => BookingStatus::Confirmed])
                ->forceFill(['created_at' => $confirmedAt])->save();
            $booking->rating()->create(['driver_profile_id' => $profile->id, 'score' => 5]);
        });
    }

    private function trip(
        DriverProfile $driver, array $route, CarbonInterface $departure, int $price, int $seats, int $available,
    ): Trip {
        $publishedAt = $departure->isPast() ? $departure->copy()->subDays(2) : now();
        $trip = $driver->trips()->make([
            'departure_at' => $departure,
            'price_kopecks' => $price,
            'total_seats' => $seats,
            'available_seats' => $available,
            'luggage_allowed' => true,
        ]);
        $trip->forceFill(['created_at' => $publishedAt, 'updated_at' => $publishedAt])->save();
        $trip->stops()->createMany(array_map(
            fn (string $city, int $position): array => compact('city', 'position'),
            $route,
            array_keys($route),
        ));

        return $trip;
    }
}
