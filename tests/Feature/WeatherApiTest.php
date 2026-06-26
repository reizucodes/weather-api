<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function fakeSuccessfulResponse(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response([
                'name' => 'Manila',
                'main' => ['temp' => 29.44],
                'weather' => [['description' => 'overcast clouds']],
                'dt' => 1782289250,
                'timezone' => 28800,
                'sys' => [
                    'sunrise' => 1782260432,
                    'sunset' => 1782305835,
                ],
                'cod' => 200,
            ], 200),
        ]);
    }

    public function test_root_route_returns_home_view(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(config('app.name'));
    }

    public function test_info_route_returns_metadata_json(): void
    {
        $response = $this->get('/info');

        $response->assertOk();
        $response->assertJson([
            'name' => config('app.name'),
            'version' => '1.0.0',
            'author' => 'John Blaise Bueno',
            'endpoints' => [
                'GET /weather/{city}',
                'GET /weather/{city}/cached',
            ],
        ]);
    }

    public function test_direct_endpoint_returns_200_with_correct_structure_and_source(): void
    {
        $this->fakeSuccessfulResponse();

        $response = $this->get('/weather/Manila');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'city',
            'temperature',
            'description',
            'timestamp',
            'local_time',
            'is_daytime',
            'source',
        ]);
        $response->assertJsonFragment(['source' => 'external']);
        $response->assertJsonFragment(['local_time' => '2026-06-24T16:20:50']);
        $response->assertJsonFragment(['is_daytime' => true]);
    }

    public function test_cached_endpoint_returns_cache_on_second_call(): void
    {
        $this->fakeSuccessfulResponse();

        $first = $this->get('/weather/Manila/cached');
        $first->assertJsonFragment(['source' => 'external']);

        $second = $this->get('/weather/Manila/cached');
        $second->assertJsonFragment(['source' => 'cache']);

        Http::assertSentCount(1); // assert only one upstream call made
    }

    public function test_invalid_city_returns_400(): void
    {
        $response = $this->get('/weather/%20');

        $response->assertStatus(400);
        $response->assertJson([
            'error' =>  [
                'code' => 'invalid_city'
            ]
        ]);
    }

    public function test_cache_expires_and_refetches(): void
    {
        $this->fakeSuccessfulResponse();

        $first = $this->get('/weather/Manila/cached');
        $first->assertJsonFragment(['source' => 'external']);

        $this->travel(11)->minutes();

        $second = $this->get('/weather/Manila/cached');
        $second->assertJsonFragment(['source' => 'external']);

        Http::assertSentCount(2); // cache expired — two upstream calls made
    }

    // Exceptions Test Cases

    public function test_unknown_city_returns_404(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response([
                'cod' => '404',
                'message' => 'city not found',
            ], 404),
        ]);

        $response = $this->get('/weather/ToyStory5');
        $response->assertStatus(404);
        $response->assertJson([
            'error' =>  [
                'code' => 'city_not_found'
            ]
        ]);
    }

    public function test_upstream_timeout_returns_504(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::failedConnection(),
        ]);

        $response = $this->get('/weather/Manila');

        $response->assertStatus(504);
        $response->assertJson([
            'error' => [
                'code' => 'upstream_timeout',
            ],
        ]);
    }

    public function test_upstream_5xx_returns_502(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response([], 500),
        ]);

        $response = $this->get('/weather/Manila');

        $response->assertStatus(502);
        $response->assertJson([
            'error' => [
                'code' => 'upstream_unavailable',
            ],
        ]);
    }

    public function test_missing_api_key_returns_500(): void
    {
        config(['services.openweathermap.key' => '']);

        $response = $this->get('/weather/Manila');

        $response->assertStatus(500);
        $response->assertJson([
            'error' => [
                'code' => 'weather_service_misconfigured',
            ],
        ]);
    }
}
