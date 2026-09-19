<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_backend_root_renders_the_cloud_api_gateway(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Cloud by NasLabs API')
            ->assertSee('Operational')
            ->assertSee('Explore Documentation')
            ->assertSee('Sanctum Session');
    }

    public function test_documentation_landing_page_is_available(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('Cloud by NasLabs API')
            ->assertSee('/docs/api');
    }

    public function test_openapi_document_route_is_registered(): void
    {
        $this->assertSame('/docs/api.json', parse_url(route('scramble.docs.document'), PHP_URL_PATH));
        $this->assertTrue((bool) config('docs.enabled'));
        $this->assertSame('1.0.0', config('docs.version'));
    }
}
