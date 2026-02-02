<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ForceJsonResponse;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| This file registers API routes and mounts a versioned router (v1).
| All application API routes should be placed under routes/api_v1.php
| and will be served with the `api` middleware group.
|
*/

Route::prefix('v1')->middleware(['api'])->group(function () {
    // Include v1 routes file (create new routes in routes/api_v1.php)
    $v1 = __DIR__ . '/api_v1.php';
    if (file_exists($v1)) {
        require $v1;
    } else {
        // minimal route if api_v1.php not present
        Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
            return $request->user();
        });
    }
});
