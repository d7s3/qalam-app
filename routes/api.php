<?php

use App\Http\Controllers\Api\Teacher\AttendanceController;
use App\Http\Controllers\Api\Teacher\AuthController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/teacher/login', [AuthController::class, 'login']);

// The complaints bot; the secret in the path is checked against Telegram's header too.
Route::post('/telegram/{secret}', TelegramWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('telegram.webhook');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/teacher/attendance', [AttendanceController::class, 'index']);
    Route::post('/teacher/attendance', [AttendanceController::class, 'store']);
});
