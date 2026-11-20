<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BookingConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_parallel_confirmations_of_the_last_seat_return_200_and_409(): void
    {
        $driver = User::factory()->create();
        Sanctum::actingAs($driver);
        $this->putJson('/api/v1/me/driver', [
            'experience_years' => 5,
            'vehicle' => ['make' => 'Lada', 'model' => 'Vesta', 'color' => 'silver', 'passenger_seats' => 3],
        ])->assertOk();

        $tripId = $this->postJson('/api/v1/trips', [
            'from' => 'ekaterinburg', 'to' => 'alapaevsk', 'stops' => ['rezh'],
            'departure_at' => now()->addDay()->toAtomString(), 'price_kopecks' => 40000,
            'seats' => 1, 'luggage_allowed' => true,
        ])->assertCreated()->json('data.id');

        $tripUri = '/api/v1/trips/'.$tripId;
        $token = $driver->createToken('concurrency-test')->plainTextToken;
        $marker = 'ride_race_'.bin2hex(random_bytes(8));
        $workers = [];

        foreach (User::factory()->count(2)->create() as $passenger) {
            Sanctum::actingAs($passenger);
            $bookingId = $this->postJson("$tripUri/bookings", ['seats' => 1])->assertCreated()->json('data.id');
            $workers[$bookingId] = $this->worker("$tripUri/bookings/$bookingId/confirm", $token, $marker);
        }

        DB::beginTransaction();

        try {
            Trip::lockForUpdate()->findOrFail($tripId);
            foreach ($workers as $worker) {
                $worker->start();
            }

            // Hold the row until PostgreSQL confirms that both API requests are blocked on a lock.
            $deadline = microtime(true) + 8;
            do {
                DB::selectOne('SELECT pg_stat_clear_snapshot()');
                $waiting = DB::table('pg_stat_activity')
                    ->where('application_name', $marker)->where('wait_event_type', 'Lock')->count();
                if ($waiting === 2) {
                    break;
                }

                usleep(20_000);
            } while (microtime(true) < $deadline);

            $this->assertSame(2, $waiting, 'Both requests must contend for the PostgreSQL row lock.');
            DB::commit();

            $results = [];
            foreach ($workers as $id => $worker) {
                $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
                $result = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $results[] = $result['status'];
                if ($result['status'] === 200) {
                    $this->assertSame('CONFIRMED', $result['body']['data']['status']);
                    $this->assertDatabaseHas('bookings', ['id' => $id, 'status' => 'CONFIRMED']);
                } else {
                    $this->assertSame('There are not enough available seats.', $result['body']['message']);
                    $this->assertDatabaseHas('bookings', ['id' => $id, 'status' => 'REQUESTED']);
                }
            }

            sort($results);
            $this->assertSame([200, 409], $results);
            $this->assertDatabaseHas('trips', ['id' => $tripId, 'available_seats' => 0]);
            $this->assertDatabaseCount('audit_events', 3);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                $worker->stop();
            }
        }
    }

    private function worker(string $uri, string $token, string $marker): Process
    {
        $database = DB::connection()->getConfig();

        return new Process([PHP_BINARY, 'tests/Fixtures/confirm-booking.php', $uri], base_path(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_URL' => '',
            'DB_HOST' => (string) $database['host'],
            'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => $database['database'],
            'DB_USERNAME' => $database['username'],
            'DB_PASSWORD' => (string) $database['password'],
            'DB_SSLMODE' => $database['sslmode'],
            'SESSION_DRIVER' => 'array',
            'CACHE_STORE' => 'array',
            'RIDE_BOOKING_TOKEN' => $token,
            'RIDE_BOOKING_WORKER' => $marker,
        ], timeout: 15);
    }
}
