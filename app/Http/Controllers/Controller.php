<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Weather API',
    description: 'Real-time weather data from OpenWeatherMap with optional 10-minute caching.',
)]
abstract class Controller
{
    //
}
