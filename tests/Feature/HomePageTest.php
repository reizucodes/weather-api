<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_page_renders_weather_ui(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('href="'.asset('css/home.css').'"', false);
        $response->assertSee('src="'.asset('js/home.js').'"', false);
        $response->assertSee('Live weather, no fuss.', false);
        $response->assertSee('Search any city and get the current conditions instantly.', false);
        $response->assertSee('data-default-city="Manila"', false);
        $response->assertSee('data-state="idle"', false);
        $response->assertSee('Manila', false);
        $response->assertSee('Singapore', false);
        $response->assertSee('New York', false);
        $response->assertSee('London', false);
        $response->assertDontSee('Quick load', false);
        $response->assertSee('Check weather', false);
        $response->assertSee('aria-busy="false"', false);
        $response->assertSee('submit-status', false);
        $response->assertSee('data-time="day"', false);
        $response->assertDontSee('Search a city below', false);
        $response->assertDontSee('Search for a city to see the latest weather.', false);
        $response->assertDontSee('request-helper', false);
        $response->assertDontSee('id="cached"', false);
        $response->assertSeeInOrder([
            'class="chips"',
            'id="weather-form"',
        ], false);
        $response->assertSee('weather-panel', false);
        $response->assertSee('weather-card', false);
        $response->assertSee('local-time', false);
        $response->assertSee('Local time', false);
        $response->assertSee('result-city', false);
        $response->assertSee('temperature', false);
        $response->assertSee('description', false);
        $response->assertSee('stat-humidity', false);
        $response->assertDontSee('Showing fresh weather for', false);
        $response->assertDontSee('cached weather', false);
        $response->assertDontSee('Current conditions at a glance', false);
        $response->assertDontSee('/weather/{city}', false);
        $response->assertDontSee('backend route split', false);
    }
}
