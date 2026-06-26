<?php

namespace App\Services;

use App\Exceptions\InvalidCityException;
use Illuminate\Support\Facades\Cache;

class WeatherService
{
    private OpenWeatherMapClient $client;

    public function __construct(OpenWeatherMapClient $client)
    {
        $this->client = $client;
    }

    public function get(string $city): array
    {
        $this->validateCity($city);
        $data = $this->client->fetch($city);

        return $this->format($data);
    }

    /**
     * Returns cached weather data for the given city, fetching from upstream on a cache miss.
     * Cache::has() is called before Cache::remember() to capture whether the entry existed
     * prior to the call — checking after would always return true since remember() stores on miss.
     */
    public function getCached(string $city): array
    {
        $this->validateCity($city);

        $key = 'weather.' . strtolower(trim($city));
        $cached = Cache::has($key);
        $ttl = 10 * 60;

        $data = Cache::remember($key, $ttl, function () use ($city) {
            $data = $this->client->fetch($city);
            return $this->format($data);
        });

        if ($cached) $data['source'] = 'cache';

        return $data;
    }

    private function validateCity(string $city): void
    {
        if (empty(trim($city))) {
            throw new InvalidCityException;
        }
    }

    private function format(array $data): array
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z', $data['dt']);
        $localTime = gmdate('Y-m-d\TH:i:s', $data['dt'] + ($data['timezone'] ?? 0));

        return [
            'city' => $data['name'] ?? null,
            'temperature' => $data['main']['temp'] ?? null,
            'description' => $data['weather'][0]['description'] ?? null,
            'timestamp' => $timestamp,
            'local_time' => $localTime,
            'is_daytime' => $this->isDaytime($data),
            'source' => 'external',
        ];
    }

    private function isDaytime(array $data): ?bool
    {
        if (! isset($data['dt'], $data['sys']['sunrise'], $data['sys']['sunset'])) {
            return null;
        }

        return $data['dt'] >= $data['sys']['sunrise'] && $data['dt'] < $data['sys']['sunset'];
    }
}
