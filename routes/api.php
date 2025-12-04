<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrbiTalkCallDurationController;
use App\Http\Controllers\IptspCallDurationController;
use App\Http\Controllers\OrbitalkReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;

// PUBLIC routes
Route::post('/login', [AuthController::class, 'login']);

// PROTECTED routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Example: protected user info
    Route::get('/user', function () {
        return auth()->user();
    });
});
// orbitalk dashboard

Route::get('/dashboard/recharged-amount', [DashboardController::class, 'DashboardRechargedAmount']);
Route::get('/dashboard/gross-profit', [DashboardController::class, 'grossProfit']);
Route::get('/dashboard/revenue', [DashboardController::class, 'revenue']);


// iptsp dashboard

Route::get('/dashboard/recharged-amount-iptsp', [DashboardController::class, 'DashboardRechargedAmountIptsp']);
Route::get('/dashboard/gross-profit-iptsp', [DashboardController::class, 'grossProfitIptsp']);
Route::get('/dashboard/revenue-iptsp', [DashboardController::class, 'revenueIptsp']);

//orbitalk report

Route::get('/payment-report', [OrbitalkReportController::class, 'paymentReport']);
Route::get('/daily', [OrbitalkReportController::class, 'grossProfitDayWise']);



Route::prefix('orbitalk')->group(function () {
    Route::get('call-duration', [OrbiTalkCallDurationController::class, 'getCallDuration']);
    Route::get('monthly-minutes', [OrbiTalkCallDurationController::class, 'getMonthlyMinutes']);
    Route::get('monthly-call-volume', [OrbiTalkCallDurationController::class, 'getMonthlyCallVolume']);
    Route::get('call-duration-report', [OrbiTalkCallDurationController::class, 'getCallDurationReport']);
});


Route::prefix('iptsp')->group(function () {
    Route::get('call-duration', [IptspCallDurationController::class, 'getCallDuration']);
    Route::get('monthly-minutes', [IptspCallDurationController::class, 'getMonthlyMinutes']);
    Route::get('monthly-call-volume', [IptspCallDurationController::class, 'getMonthlyCallVolume']);
    Route::get('call-duration-report', [IptspCallDurationController::class, 'getCallDurationReport']);
});

Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/{id}', [UserController::class, 'show']);
    Route::post('/', [UserController::class, 'store']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::delete('/{id}', [UserController::class, 'destroy']);
});
