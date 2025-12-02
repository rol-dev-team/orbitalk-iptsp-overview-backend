<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CallDurationController;
use App\Http\Controllers\IptspCallDurationController;
use App\Http\Controllers\OrbitalkReportController;


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
    Route::get('call-duration', [CallDurationController::class, 'getCallDuration']);
    Route::get('monthly-minutes', [CallDurationController::class, 'getMonthlyMinutes']);
    Route::get('monthly-call-volume', [CallDurationController::class, 'getMonthlyCallVolume']);
});


Route::prefix('iptsp')->group(function () {
    Route::get('call-duration', [IptspCallDurationController::class, 'getCallDuration']);
    Route::get('monthly-minutes', [IptspCallDurationController::class, 'getMonthlyMinutes']);
    Route::get('monthly-call-volume', [IptspCallDurationController::class, 'getMonthlyCallVolume']);
});
