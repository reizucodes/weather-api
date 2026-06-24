# Weather API

A Laravel REST API that fetches real-time weather data from OpenWeatherMap with a 10-minute caching layer.

## Prerequisites

- PHP 8.3+
- Composer

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

The `.env.example` includes a working `OPENWEATHERMAP_API_KEY` for evaluation purposes. No additional configuration needed.

## Run

```bash
php artisan serve
```

## Endpoints

### `GET /`

Returns API info and available endpoints.

**Response:**
```json
{
    "name": "WeatherApi",
    "version": "1.0.0",
    "endpoints": []
}
```

---

### `GET /weather/{city}`

Fetches real-time weather data from OpenWeatherMap.

**Example:**
```
GET /weather/Manila
```

**Response:**
```json
{
    "city": "Manila",
    "temperature": 29.44,
    "description": "overcast clouds",
    "timestamp": "2026-06-24T08:31:08Z",
    "source": "external"
}
```

---

### `GET /weather/{city}/cached`

Returns the same data but caches the result for 10 minutes. Returns `source: "cache"` on subsequent requests within the TTL.

**Example:**
```
GET /weather/Manila/cached
```

**Response (cache hit):**
```json
{
    "city": "Manila",
    "temperature": 29.44,
    "description": "overcast clouds",
    "timestamp": "2026-06-24T08:31:08Z",
    "source": "cache"
}
```

> **Note:** The `timestamp` reflects the original upstream observation time from OpenWeatherMap, not the time the cache was hit. A timestamp that appears slightly old alongside `source: "cache"` is correct behavior.

---

### Error Responses

All errors return a consistent JSON shape:

```json
{
    "error": {
        "code": "city_not_found",
        "message": "Weather data not found for requested city."
    }
}
```

| Code | HTTP | Cause |
|---|---|---|
| `invalid_city` | 400 | Blank or whitespace city name |
| `city_not_found` | 404 | City not recognized by OpenWeatherMap |
| `upstream_unavailable` | 502 | OpenWeatherMap returned an unexpected error |
| `upstream_timeout` | 504 | OpenWeatherMap did not respond in time |
| `weather_service_misconfigured` | 500 | API key is missing or empty |

## Tests

```bash
php artisan test
```

Tests use `Http::fake()` to mock all OpenWeatherMap calls — no live network required.

## Approach

The application follows a three-layer architecture:

- **Controller** — handles HTTP input/output and maps exceptions to error responses
- **WeatherService** — validates input, normalizes upstream data, and manages cache logic
- **OpenWeatherMapClient** — handles all outbound HTTP calls to OpenWeatherMap and throws typed exceptions on failure

Temperature is returned in **metric units (°C)**.
