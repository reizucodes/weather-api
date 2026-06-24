<?php

namespace App\Http\Controllers;

use App\Exceptions\CityNotFoundException;
use App\Exceptions\InvalidCityException;
use App\Exceptions\UpstreamTimeoutException;
use App\Exceptions\UpstreamUnavailableException;
use App\Exceptions\WeatherServiceMisconfiguredException;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;

class WeatherController extends Controller
{
    private WeatherService $service;

    public function __construct(WeatherService $service)
    {
        $this->service = $service;
    }

    public function show(string $city): JsonResponse
    {
        try {
            $city = urldecode($city);
            $data = $this->service->get($city);
        } catch (InvalidCityException) {
            return $this->errorResponse('invalid_city');
        } catch (CityNotFoundException) {
            return $this->errorResponse('city_not_found');
        } catch (UpstreamTimeoutException) {
            return $this->errorResponse('upstream_timeout');
        } catch (UpstreamUnavailableException) {
            return $this->errorResponse('upstream_unavailable');
        } catch (WeatherServiceMisconfiguredException) {
            return $this->errorResponse('weather_service_misconfigured');
        }

        return response()->json($data);
    }

    public function cached(string $city): JsonResponse
    {
        try {
            $city = urldecode($city);
            $data = $this->service->getCached($city);
        } catch (InvalidCityException) {
            return $this->errorResponse('invalid_city');
        } catch (CityNotFoundException) {
            return $this->errorResponse('city_not_found');
        } catch (UpstreamTimeoutException) {
            return $this->errorResponse('upstream_timeout');
        } catch (UpstreamUnavailableException) {
            return $this->errorResponse('upstream_unavailable');
        } catch (WeatherServiceMisconfiguredException) {
            return $this->errorResponse('weather_service_misconfigured');
        }

        return response()->json($data);
    }

    /**
    * Maps an error code to its HTTP status and message, returning a consistent JSON error response.
    * Exceptions are handled here rather than in Handler.php to keep error mapping visible and self-contained.
    */
    private function errorResponse(string $code): JsonResponse
    {
        $errors = [
            'invalid_city' => ['status' => 400, 'message' => 'City name invalid.'],
            'city_not_found' => ['status' => 404, 'message' => 'Weather data not found for requested city.'],
            'upstream_timeout' => ['status' => 504, 'message' => 'Weather service timed out. Please try again.'],
            'upstream_unavailable' => ['status' => 502, 'message' => 'Weather service currently unavailable.'],
            'weather_service_misconfigured' => ['status' => 500, 'message' => 'Weather service not configured correctly.'],
        ];

        $error = $errors[$code] ?? ['status' => 500, 'message' => 'An unexpected error occurred.'];

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $error['message'],
            ],
        ], $error['status']);
    }
}
