<?php

namespace App\Http\Controllers;

use App\Exceptions\CityNotFoundException;
use App\Exceptions\InvalidCityException;
use App\Exceptions\UpstreamTimeoutException;
use App\Exceptions\UpstreamUnavailableException;
use App\Exceptions\WeatherServiceMisconfiguredException;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class WeatherController extends Controller
{
    private WeatherService $service;

    public function __construct(WeatherService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/weather/{city}',
        operationId: 'getWeather',
        summary: 'Get live weather for a city',
        tags: ['Weather'],
        parameters: [
            new OA\Parameter(
                name: 'city',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'Manila'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful weather response',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'city', type: 'string', example: 'Manila'),
                        new OA\Property(property: 'temperature', type: 'number', format: 'float', example: 28.5),
                        new OA\Property(property: 'description', type: 'string', example: 'light rain'),
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time', example: '2026-06-24T08:00:00Z'),
                        new OA\Property(property: 'source', type: 'string', enum: ['external'], example: 'external'),
                    ],
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid city name',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'error',
                            properties: [
                                new OA\Property(property: 'code', type: 'string', example: 'invalid_city'),
                                new OA\Property(property: 'message', type: 'string', example: 'City name invalid.'),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'City not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'error',
                            properties: [
                                new OA\Property(property: 'code', type: 'string', example: 'city_not_found'),
                                new OA\Property(property: 'message', type: 'string', example: 'Weather data not found for requested city.'),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 500,
                description: 'Weather service misconfigured',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'error',
                            properties: [
                                new OA\Property(property: 'code', type: 'string', example: 'weather_service_misconfigured'),
                                new OA\Property(property: 'message', type: 'string', example: 'Weather service not configured correctly.'),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 502,
                description: 'Upstream service unavailable',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'error',
                            properties: [
                                new OA\Property(property: 'code', type: 'string', example: 'upstream_unavailable'),
                                new OA\Property(property: 'message', type: 'string', example: 'Weather service currently unavailable.'),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 504,
                description: 'Upstream service timed out',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'error',
                            properties: [
                                new OA\Property(property: 'code', type: 'string', example: 'upstream_timeout'),
                                new OA\Property(property: 'message', type: 'string', example: 'Weather service timed out. Please try again.'),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
        ],
    )]
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

    #[OA\Get(
        path: '/weather/{city}/cached',
        operationId: 'getCachedWeather',
        summary: 'Get weather for a city, cached for 10 minutes',
        tags: ['Weather'],
        parameters: [
            new OA\Parameter(
                name: 'city',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'Manila'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful weather response',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'city', type: 'string', example: 'Manila'),
                        new OA\Property(property: 'temperature', type: 'number', format: 'float', example: 28.5),
                        new OA\Property(property: 'description', type: 'string', example: 'light rain'),
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time', example: '2026-06-24T08:00:00Z'),
                        new OA\Property(
                            property: 'source',
                            description: '"cache" when served from the 10-minute cache, "external" on a cache miss',
                            type: 'string',
                            enum: ['external', 'cache'],
                            example: 'external',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid city name',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'error',
                            properties: [
                                new OA\Property(property: 'code', type: 'string', example: 'invalid_city'),
                                new OA\Property(property: 'message', type: 'string', example: 'City name invalid.'),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'City not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'error',
                            properties: [
                                new OA\Property(property: 'code', type: 'string', example: 'city_not_found'),
                                new OA\Property(property: 'message', type: 'string', example: 'Weather data not found for requested city.'),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 500,
                description: 'Weather service misconfigured',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'error',
                            properties: [
                                new OA\Property(property: 'code', type: 'string', example: 'weather_service_misconfigured'),
                                new OA\Property(property: 'message', type: 'string', example: 'Weather service not configured correctly.'),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 502,
                description: 'Upstream service unavailable',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'error',
                            properties: [
                                new OA\Property(property: 'code', type: 'string', example: 'upstream_unavailable'),
                                new OA\Property(property: 'message', type: 'string', example: 'Weather service currently unavailable.'),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 504,
                description: 'Upstream service timed out',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'error',
                            properties: [
                                new OA\Property(property: 'code', type: 'string', example: 'upstream_timeout'),
                                new OA\Property(property: 'message', type: 'string', example: 'Weather service timed out. Please try again.'),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
        ],
    )]
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
