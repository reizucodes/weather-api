# Exceptions

These exception classes are intentionally empty. Each class name carries the full meaning — no extra properties or messages are needed.

They are thrown by `OpenWeatherMapClient` and caught by `WeatherController`. The controller explicitly maps each exception type to an HTTP status code and JSON error response — the exceptions themselves carry no status codes.

| Exception | Thrown when | HTTP (set in controller) |
|---|---|---|
| `CityNotFoundException` | Upstream returns 404 | 404 |
| `UpstreamUnavailableException` | Upstream returns non-404 failure | 502 |
| `UpstreamTimeoutException` | Upstream connection times out | 504 |
| `WeatherServiceMisconfiguredException` | API key is missing or empty | 500 |
