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
Route::get('/dashboard/regester-user', [DashboardController::class, 'getClientCounts']);


//for both orbitalk and iptsp

Route::get('/dashboard/call-status', [DashboardController::class, 'callStats']);


// iptsp dashboard

Route::get('/dashboard/iptsp/recharged-amount', [DashboardController::class, 'DashboardRechargedAmountIptsp']);
Route::get('/dashboard/iptsp/gross-profit', [DashboardController::class, 'grossProfitIptsp']);
Route::get('/dashboard/iptsp/revenue', [DashboardController::class, 'revenueIptsp']);
Route::get('/dashboard/iptsp/regester-user', [DashboardController::class, 'getClientCountsIptsp']);

//orbitalk report

Route::get('/clients', [OrbitalkReportController::class, 'getClients']);


Route::get('/payment-report/date-wise', [OrbitalkReportController::class, 'paymentReport']);
Route::get('/payment-report/date-wise/export', [OrbitalkReportController::class, 'exportPaymentReport']);

Route::get('/gross-profit/date-wise', [OrbitalkReportController::class, 'dateWiseGrossProfit']);
Route::get('/gross-profit/date-wise/export', [OrbitalkReportController::class, 'exportDateWiseGrossProfit']);


Route::get('/revenue/date-wise', [OrbitalkReportController::class, 'dateWiseRevenue']);
Route::get('/revenue/date-wise/export', [OrbitalkReportController::class, 'exportDateWiseRevenue']);


//iptsp report

Route::get('/iptsp/clients', [OrbitalkReportController::class, 'getClientsIptsp']);

Route::get('/recharge-report/date-wise', [OrbitalkReportController::class, 'dateWiseRechargedAmountIptsp']);
Route::get('/recharge-report/date-wise/export', [OrbitalkReportController::class, 'exportDateWiseRechargedAmountIptsp']);


Route::get('/gross-profit/iptsp/date-wise', [OrbitalkReportController::class, 'dateWiseGrossProfitIptsp']);
Route::get('/gross-profit/iptsp/date-wise/export', [OrbitalkReportController::class, 'exportDateWiseGrossProfitIptsp']);


Route::get('/revenue/iptsp/date-wise', [OrbitalkReportController::class, 'dateWiseRevenueIptsp']);
Route::get('/revenue/iptsp/date-wise/export', [OrbitalkReportController::class, 'exportDateWiseRevenueIptsp']);



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
