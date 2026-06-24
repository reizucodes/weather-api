<?php

use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'version' => '1.0.0',
        'author' => 'John Blaise Bueno',
        'endpoints' => [
            'GET /weather/{city}',
            'GET /weather/{city}/cached'
        ]
    ]);
});

// Defined in web.php to preserve the exact endpoint paths specified in the take-home exercise.
Route::prefix('weather')->group(function () {
    Route::get('/{city}', [WeatherController::class, 'show']);
    Route::get('/{city}/cached', [WeatherController::class, 'cached']);
});
