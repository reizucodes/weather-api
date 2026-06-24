<?php

namespace App\Services;

use App\Exceptions\CityNotFoundException;
use App\Exceptions\UpstreamTimeoutException;
use App\Exceptions\UpstreamUnavailableException;
use App\Exceptions\WeatherServiceMisconfiguredException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenWeatherMapClient
{
    private string $baseUrl;

    private string $key;

    private string $unit;

    public function __construct()
    {
        $this->baseUrl = config('services.openweathermap.base');
        $this->key = config('services.openweathermap.key');
        $this->unit = config('services.openweathermap.unit');
    }

    public function fetch(string $city): array
    {
        if (empty($this->key)) {
            throw new WeatherServiceMisconfiguredException;
        }

        try {
            $response = Http::timeout(10)->get(
                url: $this->baseUrl.'/weather',
                query: [
                    'appid' => $this->key,
                    'q' => $city,
                    'units' => $this->unit,
                ]
            );
        } catch (ConnectionException) {
            throw new UpstreamTimeoutException;
        }

        if ($response->status() === 404) {
            throw new CityNotFoundException;
        }

        if (! $response->successful()) {
            throw new UpstreamUnavailableException;
        }

        $data = $response->json();

        // guard against a 200 response with missing required fields
        if (! isset($data['name'], $data['main']['temp'], $data['weather'][0]['description'], $data['dt'])) {
            throw new UpstreamUnavailableException;
        }

        return $data;
    }
}
