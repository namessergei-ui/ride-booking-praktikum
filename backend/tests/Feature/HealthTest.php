<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_root_identifies_the_api_and_healthcheck_responds(): void
    {
        $this->getJson('/')->assertOk()->assertExactJson(['service' => 'Ride Booking API', 'version' => 'v1']);
        $this->get('/up')->assertOk();
    }
}
