<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrbiTalkCallDurationController;
use App\Http\Controllers\IptspCallDurationController;
use App\Http\Controllers\OrbitalkReportController;

// orbitalk dashboard

Route::get('/dashboard/recharged-amount', [DashboardController::class, 'DashboardRechargedAmount']);
Route::get('/dashboard/gross-profit', [DashboardController::class, 'grossProfit']);
Route::get('/dashboard/revenue', [DashboardController::class, 'revenue']);


Route::get('/dashboard/call-status', [DashboardController::class, 'callStats']);


// iptsp dashboard

Route::get('/dashboard/iptsp/recharged-amount', [DashboardController::class, 'DashboardRechargedAmountIptsp']);
Route::get('/dashboard/iptsp/gross-profit', [DashboardController::class, 'grossProfitIptsp']);
Route::get('/dashboard/iptsp/revenue', [DashboardController::class, 'revenueIptsp']);

//orbitalk report

Route::get('/payment-report/date-wise', [OrbitalkReportController::class, 'paymentReport']);
Route::get('/gross-profit/date-wise', [OrbitalkReportController::class, 'dateWiseGrossProfit']);
Route::get('/revenue/date-wise', [OrbitalkReportController::class, 'dateWiseRevenue']);



//iptsp report

Route::get('/payment-report/iptsp/date-wise', [OrbitalkReportController::class, 'paymentReport']);
Route::get('/gross-profit/iptsp/date-wise', [OrbitalkReportController::class, 'dateWiseGrossProfitIptsp']);
Route::get('/revenue/iptsp/date-wise', [OrbitalkReportController::class, 'dateWiseRevenueIptsp']);



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
