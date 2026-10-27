<?php

use App\Http\Controllers\Panel\AuthController;
use App\Http\Controllers\Panel\CashierController;
use Illuminate\Support\Facades\Route;

Route::prefix('panel')->name('panel.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/user', [AuthController::class, 'user'])->name('user');

        Route::get('/cashiers', [CashierController::class, 'index'])->name('cashiers.index');
        Route::post('/cashiers', [CashierController::class, 'store'])->name('cashiers.store');
        Route::put('/cashiers/{cashier}', [CashierController::class, 'update'])->name('cashiers.update');
        Route::patch('/cashiers/{cashier}/activate', [CashierController::class, 'activate'])->name('cashiers.activate');
        Route::patch('/cashiers/{cashier}/deactivate', [CashierController::class, 'deactivate'])->name('cashiers.deactivate');
        Route::delete('/cashiers/{cashier}', [CashierController::class, 'destroy'])->name('cashiers.destroy');
    });
});

// Reserved for the Android companion app (Day 3): /device/login, /device/payments, /device/heartbeat.
