<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductionAuthConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cors.allowed_origins' => ['https://cloud.naslabs.my.id'],
            'session.domain' => '.naslabs.my.id',
            'session.secure' => true,
            'sanctum.stateful' => ['cloud.naslabs.my.id'],
        ]);
    }

    public function test_cors_allows_the_configured_production_frontend_with_credentials(): void
    {
        $origin = 'https://cloud.naslabs.my.id';

        $this->assertTrue(config('cors.supports_credentials'));
        $this->assertContains($origin, config('cors.allowed_origins'));
        $this->assertNotContains('*', config('cors.allowed_origins'));
        $this->assertContains('sanctum/csrf-cookie', config('cors.paths'));
        $this->assertContains('X-XSRF-TOKEN', config('cors.allowed_headers'));
        $this->assertContains('Content-Disposition', config('cors.exposed_headers'));

        $response = $this->withHeaders([
            'Origin' => $origin,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type,x-xsrf-token',
        ])->options('/api/auth/login')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $this->assertStringContainsString('POST', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('x-xsrf-token', strtolower($response->headers->get('Access-Control-Allow-Headers')));
    }

    public function test_csrf_cookie_endpoint_is_cors_enabled_for_the_production_frontend(): void
    {
        $response = $this->withHeader('Origin', 'https://cloud.naslabs.my.id')
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://cloud.naslabs.my.id')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $cookies = $response->baseResponse->headers->getCookies();
        $sessionCookie = collect($cookies)->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $xsrfCookie = collect($cookies)->first(fn ($cookie) => $cookie->getName() === 'XSRF-TOKEN');

        $this->assertNotNull($sessionCookie);
        $this->assertSame('.naslabs.my.id', $sessionCookie->getDomain());
        $this->assertTrue($sessionCookie->isSecure());
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertSame('lax', $sessionCookie->getSameSite());
        $this->assertNotNull($xsrfCookie);
        $this->assertFalse($xsrfCookie->isHttpOnly());
        $this->assertTrue($xsrfCookie->isSecure());
    }

    public function test_production_session_and_stateful_domain_contract_is_sane(): void
    {
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertSame('.naslabs.my.id', config('session.domain'));
        $this->assertTrue(config('session.secure'));
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertContains('cloud.naslabs.my.id', config('sanctum.stateful'));
        $this->assertGreaterThan(0, config('auth.passwords.users.throttle'));

        foreach (config('sanctum.stateful') as $domain) {
            $this->assertSame(trim($domain), $domain);
            $this->assertStringNotContainsString('://', $domain);
        }
    }

    public function test_login_regenerates_the_session_identifier(): void
    {
        $user = User::factory()->create();

        $this->withHeader('Origin', 'https://cloud.naslabs.my.id')
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent();
        $originalSessionId = app('session')->driver()->getId();

        $this->withHeader('Origin', 'https://cloud.naslabs.my.id')->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertNotSame($originalSessionId, app('session')->driver()->getId());
    }
}
