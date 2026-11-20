<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RideBookingApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const DRIVER_PROFILE = [
        'experience_years' => 6,
        'vehicle' => ['make' => 'Lada', 'model' => 'Vesta', 'color' => 'silver', 'passenger_seats' => 3],
    ];

    public function test_register_login_profile_and_token_revocation(): void
    {
        $credentials = ['email' => 'Alice@example.test', 'password' => 'example-password'];
        $response = $this->postJson('/api/v1/auth/register', ['name' => 'Alice', ...$credentials])
            ->assertCreated()->assertJsonPath('data.user.email', 'alice@example.test');

        $user = User::where('email', 'alice@example.test')->firstOrFail();
        $this->assertNotSame($credentials['password'], $user->password);
        $this->withToken($response->json('data.token'))
            ->patchJson('/api/v1/me', ['name' => 'Alice Driver', 'email' => 'ignored@example.test'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Alice Driver')
            ->assertJsonPath('data.email', 'alice@example.test');

        $this->postJson('/api/v1/auth/login', [...$credentials, 'password' => 'incorrect'])
            ->assertUnauthorized();
        $login = $this->postJson('/api/v1/auth/login', $credentials)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($login->json('data.token'))->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_route_search_matches_stop_order_local_date_and_combined_filters(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 20)->startOfDay());
        $this->getJson('/api/v1/cities?q='.urlencode('реЖ'))->assertOk()->assertJsonPath('data.0.id', 'rezh');
        $trip = $this->publishTrip(User::factory()->create(), ['departure_at' => '2026-11-23T00:30:00+05:00']);

        $this->assertSame(['ekaterinburg', 'rezh', 'alapaevsk'], array_column($trip['route'], 'id'));
        $this->assertSame('2026-11-22T19:30:00+00:00', $trip['departure_at']);

        $params = ['from' => 'rezh', 'to' => 'alapaevsk', 'date' => '2026-11-23',
            'max_price_kopecks' => 40000, 'seats' => 1, 'luggage_allowed' => 1];
        $this->getJson('/api/v1/trips?'.http_build_query($params))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $trip['id'])
            ->assertJsonMissingPath('data.0.driver.email');

        foreach ([
            ['from' => 'alapaevsk', 'to' => 'rezh'],
            ['timezone' => 'UTC'],
            ['max_price_kopecks' => 39999],
            ['seats' => 2],
            ['luggage_allowed' => 0],
            ['min_rating' => 4],
        ] as $override) {
            $this->getJson('/api/v1/trips?'.http_build_query(array_replace($params, $override)))
                ->assertOk()->assertJsonCount(0, 'data');
        }

        $this->getJson('/api/v1/trips/invalid')->assertNotFound();
    }

    public function test_trip_creation_validates_driver_and_payload(): void
    {
        $this->postJson('/api/v1/trips', $this->tripPayload())->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/trips', $this->tripPayload())->assertForbidden();
        $this->putJson('/api/v1/me/driver', self::DRIVER_PROFILE)->assertOk();
        $this->postJson('/api/v1/trips', $this->tripPayload([
            'departure_at' => now()->subDay()->toAtomString(), 'seats' => 0, 'from' => 'unknown',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['departure_at', 'seats', 'from']);
        $this->postJson('/api/v1/trips', $this->tripPayload(['stops' => ['ekaterinburg']]))
            ->assertUnprocessable()->assertJsonValidationErrors(['stops']);
        $this->postJson('/api/v1/trips', $this->tripPayload(['seats' => 4]))
            ->assertUnprocessable()->assertJsonValidationErrors(['seats']);
        $this->assertDatabaseCount('trips', 0);
    }

    public function test_booking_confirmation_updates_seats_history_and_chat_membership(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = $this->publishTrip($driver);
        $base = '/api/v1/trips/'.$trip['id'];

        $this->postJson("$base/bookings", ['seats' => 1])->assertForbidden();
        Sanctum::actingAs($passenger);
        $this->postJson("$base/bookings", ['seats' => 2])->assertConflict();
        $booking = $this->postJson("$base/bookings", ['seats' => 1])->assertCreated()
            ->assertJsonPath('data.status', 'REQUESTED')
            ->assertJsonPath('data.history.0.actor_id', $passenger->id)->json('data');
        $uri = "$base/bookings/".$booking['id'];
        $this->postJson("$base/bookings", ['seats' => 1])->assertConflict();
        $this->getJson('/api/v1/bookings')->assertOk()->assertJsonPath('data.0.id', $booking['id']);
        $this->getJson($base)->assertOk()->assertJsonPath('data.available_seats', 1);
        $this->getJson("$base/messages")->assertForbidden();
        $this->postJson("$uri/confirm")->assertForbidden();
        $this->putJson("$uri/rating", ['score' => 5])->assertConflict();

        Sanctum::actingAs($stranger);
        $this->getJson($uri)->assertForbidden();
        $this->getJson("$base/bookings")->assertForbidden();
        $this->getJson("$base/participants")->assertForbidden();
        $this->postJson("$base/messages", ['body' => 'Blocked'])->assertForbidden();

        Sanctum::actingAs($driver);
        $this->getJson("$base/bookings")->assertOk()->assertJsonPath('data.0.status', 'REQUESTED');
        $this->postJson("$uri/cancel")->assertForbidden();
        $this->postJson("$uri/confirm")->assertOk()
            ->assertJsonPath('data.status', 'CONFIRMED')
            ->assertJsonPath('data.history.1.actor_id', $driver->id);
        $this->postJson("$uri/confirm")->assertOk()->assertJsonCount(2, 'data.history');
        $this->getJson($base)->assertOk()->assertJsonPath('data.available_seats', 0);

        Sanctum::actingAs($passenger);
        $this->getJson("$base/participants")->assertOk()
            ->assertJsonPath('data.passengers.0.id', $passenger->id)->assertJsonMissingPath('data.passengers.0.email');
        $this->getJson($uri)->assertOk()->assertJsonPath('data.status', 'CONFIRMED');
        $this->postJson("$uri/cancel")->assertConflict();
        $message = $this->postJson("$base/messages", ['body' => 'Встречаемся на вокзале', 'author_id' => $stranger->id])
            ->assertCreated()->assertJsonPath('data.author.id', $passenger->id)->json('data');

        Sanctum::actingAs($driver);
        $this->getJson("$base/messages")->assertOk()->assertJsonPath('data.0.body', 'Встречаемся на вокзале');
        $this->getJson("$base/messages?after_id=".$message['id'])->assertOk()->assertExactJson(['data' => []]);

        $this->assertDatabaseHas('trips', ['id' => $trip['id'], 'available_seats' => 0]);
        $this->assertDatabaseCount('audit_events', 2);
        $this->assertDatabaseCount('chat_messages', 1);

        $this->travel(3)->days();
        Sanctum::actingAs($passenger);
        $this->putJson("$uri/rating", ['score' => 5])->assertOk()->assertJsonPath('data.score', 5);
        $this->assertDatabaseHas('ratings', ['booking_id' => $booking['id'], 'score' => 5]);
        $newTrip = $this->publishTrip($driver, ['price_kopecks' => 35000]);
        $this->getJson('/api/v1/trips?min_rating=4.5&max_price_kopecks=35000')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $newTrip['id']);
    }

    public function test_cancel_and_reject_keep_seats_available_and_nested_routes_are_scoped(): void
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = $this->publishTrip($driver);
        $other = $this->publishTrip($driver);
        $base = '/api/v1/trips/'.$trip['id'];

        Sanctum::actingAs($passenger);
        $id = $this->postJson("$base/bookings", ['seats' => 1])->assertCreated()->json('data.id');
        $this->postJson("$base/bookings/$id/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $next = $this->postJson("$base/bookings", ['seats' => 1])->assertCreated()->json('data.id');

        Sanctum::actingAs($driver);
        $this->postJson('/api/v1/trips/'.$other['id'].'/bookings/'.$next.'/confirm')->assertNotFound();
        $this->postJson("$base/bookings/$next/reject")->assertOk()
            ->assertJsonPath('data.status', 'REJECTED')->assertJsonPath('data.history.1.actor_id', $driver->id);
        $this->getJson($base)->assertOk()->assertJsonPath('data.available_seats', 1);
    }

    public function test_personal_trips_and_booking_filter_are_scoped_to_the_signed_in_user(): void
    {
        $driver = User::factory()->create();
        $trip = $this->publishTrip($driver);
        $otherTrip = $this->publishTrip(User::factory()->create());

        $this->getJson('/api/v1/me/trips')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $otherTrip['id']);

        Sanctum::actingAs($driver);
        $this->getJson('/api/v1/me/trips')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $trip['id']);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/me/trips')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/trips/'.$trip['id'].'/bookings', ['seats' => 1])->assertCreated();
        $this->getJson('/api/v1/bookings?trip_id='.$trip['id'])->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.trip.route.1.id', 'rezh');
        $this->getJson('/api/v1/bookings?trip_id='.$otherTrip['id'])->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($driver);
        $this->getJson('/api/v1/bookings?trip_id='.$trip['id'])->assertOk()->assertJsonCount(0, 'data');
    }

    private function publishTrip(User $driver, array $overrides = []): array
    {
        Sanctum::actingAs($driver);
        $this->putJson('/api/v1/me/driver', self::DRIVER_PROFILE)->assertOk();

        return $this->postJson('/api/v1/trips', $this->tripPayload($overrides))->assertCreated()->json('data');
    }

    private function tripPayload(array $overrides = []): array
    {
        return array_replace([
            'from' => 'ekaterinburg', 'to' => 'alapaevsk', 'stops' => ['rezh'],
            'departure_at' => now()->addDays(2)->toAtomString(),
            'price_kopecks' => 40000, 'seats' => 1, 'luggage_allowed' => true,
        ], $overrides);
    }
}
