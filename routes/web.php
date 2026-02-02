<?php

use Illuminate\Support\Facades\Route;

// Minimal web routes — application is API-only. Keep this file intentionally small.
Route::get('/', function () {
    return response()->json(['message' => 'This application is API-only. Use /api/v1/* endpoints.']);
});
