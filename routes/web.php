<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Auth\GoogleAuthController;

Route::get('/', function () {
    return redirect()->route('login');
    return Inertia::render('welcome');
})->name('home');

Route::any('telegram/handle', [\App\Http\Controllers\TelegramController::class , 'handle']);

Route::middleware(['auth', 'verified'])->group(function () {
    //    Route::get('dashboard', function () {
//        return Inertia::render('dashboard');
//    })->name('dashboard');

    Route::get('dashboard', [\App\Http\Controllers\HomeController::class , 'index'])->name('dashboard');

    Route::resource('user', \App\Http\Controllers\User\UserController::class);
    Route::resource('user_firm', \App\Http\Controllers\User\UserFirmController::class);
    Route::resource('firm', \App\Http\Controllers\Firm\FirmController::class);
    Route::resource('firm_setting', \App\Http\Controllers\Firm\FirmSettingController::class);
    Route::resource('firm_holiday', \App\Http\Controllers\Firm\FirmHolidayController::class);
    Route::resource('firm_setting', \App\Http\Controllers\Firm\FirmSettingController::class);
    Route::resource('branch', \App\Http\Controllers\Branch\BranchController::class);
    Route::resource('branch_holiday', \App\Http\Controllers\Branch\BranchHolidayController::class);
    Route::resource('branch_day', \App\Http\Controllers\Branch\BranchDayController::class);
    Route::resource('worker_day', \App\Http\Controllers\Worker\WorkerDayController::class);
    Route::resource('branch_device', \App\Http\Controllers\Branch\BranchDeviceController::class);
    Route::resource('worker', \App\Http\Controllers\Worker\WorkerController::class);
    Route::resource('worker_holiday', \App\Http\Controllers\Worker\WorkerHolidayController::class);
    Route::resource('salary', \App\Http\Controllers\Salary\SalaryController::class);
    Route::resource('salary_payment', \App\Http\Controllers\Salary\SalaryPaymentController::class);
    Route::resource('hikvision_access_event', \App\Http\Controllers\Hikvision\HikvisionAccessEventController::class);

    Route::get('/worker/show_history/{worker}', [\App\Http\Controllers\Worker\WorkerController::class , 'show_history']);

    Route::get('/attendance', [\App\Http\Controllers\Hikvision\HikvisionController::class , 'attendance']);
    Route::get('/daily_attendance/{branch}', [\App\Http\Controllers\Hikvision\HikvisionController::class , 'daily_attendance']);

    Route::get('/monthly_attendance', [\App\Http\Controllers\ReportController::class , 'monthly_attendance']);
    Route::get('/salary_report', [\App\Http\Controllers\ReportController::class , 'salary_report']);

});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';


Route::get('/auth/google', [GoogleAuthController::class , 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class , 'callback'])->name('google.callback');
Route::get('/lang/{locale}', function ($locale) {
    if (!in_array($locale, ['en', 'uz', 'ru'])) {
        abort(400);
    }
    session(['locale' => $locale]);
    app()->setLocale($locale);
    return back();
});


//handle requests from payment system
Route::any('/handle/{paysys}', function ($paysys) {
    (new Goodoneuz\PayUz\PayUz)->driver($paysys)->handle();
});

//redirect to payment system or payment form
Route::any('/pay/{paysys}/{key}/{amount}', function ($paysys, $key, $amount) {
    $model = Goodoneuz\PayUz\Services\PaymentService::convertKeyToModel($key);
    $url = request('redirect_url', '/'); // redirect url after payment completed
    $pay_uz = new Goodoneuz\PayUz\PayUz;
    $pay_uz
        ->driver($paysys)
        ->redirect($model, $amount, 860, $url);
});

Route::get('/bot/mini-app', function () {
    return inertia('bot/MiniApp');
});

Route::get('/privacy-policy', function () {
    return view('privacy');
})->name('privacy.policy');

Route::get('/terms-of-service', function () {
    return view('terms');
})->name('terms.service');