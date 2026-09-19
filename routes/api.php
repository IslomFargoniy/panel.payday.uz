<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::any('/hikvision-callback', [\App\Http\Controllers\Hikvision\HikvisionController::class, 'store']);
Route::get('/hikvision-device-key', [\App\Http\Controllers\Hikvision\HikvisionController::class, 'getDeviceKey']);
Route::post('/hikvision-device-status', [\App\Http\Controllers\Hikvision\HikvisionController::class, 'updateDeviceStatus']);
Route::post('/branch-device/{device}/sync-events', [\App\Http\Controllers\Hikvision\HikvisionController::class, 'syncDeviceEvents']);


Route::get('/worker/show_history/{worker}', [\App\Http\Controllers\Worker\WorkerController::class, 'show_history']);
Route::post('/bot/auth', [\App\Http\Controllers\Api\TelegramBotController::class, 'authenticate']);
Route::post('/bot/attendance', [\App\Http\Controllers\Api\TelegramBotController::class, 'recordAttendance']);
