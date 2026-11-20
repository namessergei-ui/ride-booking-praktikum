<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Trip;
use Database\Seeders\DemoRideSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_demo_seed_can_run_twice_and_returns_a_searchable_trip_with_accurate_seats(): void
    {
        $this->seed(DemoRideSeeder::class);
        $upcoming = Trip::where('departure_at', '>', now())->sole();
        $this->seed(DemoRideSeeder::class);

        $response = $this->getJson('/api/v1/trips?from=rezh&to=alapaevsk&seats=2&min_rating=5');
        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $upcoming->id)
            ->assertJsonPath('data.0.available_seats', 2)
            ->assertJsonPath('data.0.route.1.id', 'rezh');

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('trips', 2);
        $this->assertDatabaseCount('trip_stops', 6);
        $this->assertDatabaseCount('bookings', 3);
        $this->assertDatabaseCount('audit_events', 5);
        $this->assertDatabaseCount('chat_messages', 1);
        $this->assertDatabaseCount('ratings', 1);
    }

    public function test_trip_and_city_endpoints_read_the_same_stored_catalog(): void
    {
        $this->seed(DemoRideSeeder::class);
        City::findOrFail('rezh')->update(['name' => 'Реж, центр', 'lat' => 57.4]);
        $trip = Trip::where('departure_at', '>', now())->sole();

        $this->getJson('/api/v1/cities?q='.urlencode('РЕЖ'))
            ->assertOk()->assertJsonPath('data.0.name', 'Реж, центр')->assertJsonPath('data.0.lat', 57.4);

        $this->getJson('/api/v1/trips/'.$trip->id)
            ->assertOk()->assertJsonPath('data.route.1.name', 'Реж, центр')->assertJsonPath('data.route.1.lat', 57.4);
    }

    public function test_postgresql_rejects_a_stop_with_an_unknown_city(): void
    {
        $this->seed(DemoRideSeeder::class);
        $trip = Trip::where('departure_at', '>', now())->sole();
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23503');

        DB::transaction(fn () => $trip->stops()->create(['city' => 'unknown-city', 'position' => 3]));
    }
}
