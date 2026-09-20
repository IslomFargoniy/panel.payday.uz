<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\WorkerApiController;
use App\Http\Controllers\Api\AttendanceApiController;
use App\Http\Controllers\Api\SalaryApiController;
use App\Http\Controllers\Api\FirmApiController;
use App\Http\Controllers\Api\TelegramBotController;
use App\Http\Controllers\Hikvision\HikvisionController;
use App\Http\Controllers\Worker\WorkerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Auth Endpoints
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected Mobile API Endpoints
Route::middleware('auth:sanctum')->group(function () {
    // Auth & Profile
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/change-password', [AuthController::class, 'changePassword']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Workers
    Route::get('/workers', [WorkerApiController::class, 'index']);
    Route::post('/workers', [WorkerApiController::class, 'store']);
    Route::get('/workers/{id}', [WorkerApiController::class, 'show']);
    Route::put('/workers/{id}', [WorkerApiController::class, 'update']);
    Route::delete('/workers/{id}', [WorkerApiController::class, 'destroy']);
    Route::post('/workers/{id}/avatar', [WorkerApiController::class, 'uploadAvatar']);

    // Attendance & Reports
    Route::get('/attendance/daily/{branchId?}', [AttendanceApiController::class, 'dailyAttendance']);
    Route::get('/attendance/monthly', [AttendanceApiController::class, 'monthlyAttendance']);
    Route::get('/attendance/grid', [AttendanceApiController::class, 'attendanceGrid']);

    // Salary & Payments
    Route::get('/salary/report', [SalaryApiController::class, 'salaryReport']);
    Route::get('/salary/list', [SalaryApiController::class, 'salaryList']);
    Route::post('/salary/calculate', [SalaryApiController::class, 'calculateSalary']);
    Route::get('/salary/payments', [SalaryApiController::class, 'paymentList']);
    Route::post('/salary/payments', [SalaryApiController::class, 'storePayment']);

    // Firms, Branches & Devices
    Route::get('/firms', [FirmApiController::class, 'firms']);
    Route::get('/branches', [FirmApiController::class, 'branches']);
    Route::get('/devices', [FirmApiController::class, 'devices']);
});

// Legacy and External Device Callbacks
Route::any('/hikvision-callback', [HikvisionController::class, 'store']);
Route::get('/hikvision-device-key', [HikvisionController::class, 'getDeviceKey']);
Route::post('/hikvision-device-status', [HikvisionController::class, 'updateDeviceStatus']);
Route::post('/branch-device/{device}/sync-events', [HikvisionController::class, 'syncDeviceEvents']);
Route::get('/worker/show_history/{worker}', [WorkerController::class, 'show_history']);
Route::post('/bot/auth', [TelegramBotController::class, 'authenticate']);
Route::post('/bot/attendance', [TelegramBotController::class, 'recordAttendance']);

