<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_service_status(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.version', '2.0.0')
            ->assertJsonPath('data.services.database.status', 'connected')
            ->assertJsonPath('data.services.storage.status', 'active')
            ->assertJsonPath('data.services.queue.status', 'active')
            ->assertJsonPath('data.services.queue.driver', 'database')
            ->assertJsonMissingPath('data.services.storage.path')
            ->assertJsonMissingPath('data.services.database.host');
    }

    public function test_unknown_api_route_returns_consistent_json(): void
    {
        $this->getJson('/api/unknown')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.');
    }
}
