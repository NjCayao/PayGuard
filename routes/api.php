<?php

use App\Http\Controllers\Device\AuthController as DeviceAuthController;
use App\Http\Controllers\Device\HeartbeatController;
use App\Http\Controllers\Device\PaymentController;
use App\Http\Controllers\Panel\AccountController;
use App\Http\Controllers\Panel\AuthController;
use App\Http\Controllers\Panel\BusinessSettingsController;
use App\Http\Controllers\Panel\CashierController;
use App\Http\Controllers\Panel\DeviceController as PanelDeviceController;
use App\Http\Controllers\Panel\OwnerAlertController;
use App\Http\Controllers\Panel\ReceivedPaymentController;
use App\Http\Controllers\Panel\SummaryController;
use App\Http\Controllers\Panel\TrialController;
use App\Http\Controllers\Panel\VerificationController;
use App\Http\Controllers\Panel\VerificationExportController;
use App\Http\Controllers\WhatsApp\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/webhooks/whatsapp', [WebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WebhookController::class, 'handle'])->name('webhooks.whatsapp.handle');

Route::prefix('panel')->name('panel.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:'.config('payguard.registration.throttle'))
        ->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/user', [AuthController::class, 'user'])->name('user');

        Route::get('/account', [AccountController::class, 'show'])->name('account.show');
        Route::put('/account/email', [AccountController::class, 'updateEmail'])->name('account.email');
        Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
        Route::put('/account/owner-whatsapp', [AccountController::class, 'updateOwnerWhatsapp'])->name('account.owner-whatsapp');

        Route::get('/cashiers', [CashierController::class, 'index'])->name('cashiers.index');
        Route::post('/cashiers', [CashierController::class, 'store'])->name('cashiers.store');
        Route::put('/cashiers/{cashier}', [CashierController::class, 'update'])->name('cashiers.update');
        Route::patch('/cashiers/{cashier}/activate', [CashierController::class, 'activate'])->name('cashiers.activate');
        Route::patch('/cashiers/{cashier}/deactivate', [CashierController::class, 'deactivate'])->name('cashiers.deactivate');
        Route::delete('/cashiers/{cashier}', [CashierController::class, 'destroy'])->name('cashiers.destroy');

        Route::get('/received-payments', [ReceivedPaymentController::class, 'index'])->name('received-payments.index');

        Route::get('/verifications/export', [VerificationExportController::class, 'export'])->name('verifications.export');
        Route::get('/verifications', [VerificationController::class, 'index'])->name('verifications.index');
        Route::get('/verifications/{verification}', [VerificationController::class, 'show'])->name('verifications.show');
        Route::get('/verifications/{verification}/image', [VerificationController::class, 'image'])->name('verifications.image');
        Route::get('/verifications/{verification}/audio', [VerificationController::class, 'audio'])->name('verifications.audio');

        Route::get('/owner-alerts', [OwnerAlertController::class, 'index'])->name('owner-alerts.index');

        Route::get('/settings', [BusinessSettingsController::class, 'show'])->name('settings.show');
        Route::put('/settings', [BusinessSettingsController::class, 'update'])->name('settings.update');

        Route::get('/summary', [SummaryController::class, 'index'])->name('summary.index');

        Route::get('/devices', [PanelDeviceController::class, 'index'])->name('devices.index');
        Route::post('/devices/{device}/unlink', [PanelDeviceController::class, 'unlink'])->name('devices.unlink');

        Route::get('/trial/session', [TrialController::class, 'show'])->name('trial.session');
        Route::post('/trial/image', [TrialController::class, 'storeImage'])->middleware('throttle:trial')->name('trial.image');
        Route::post('/trial/query', [TrialController::class, 'storeQuery'])->middleware('throttle:trial')->name('trial.query');
        Route::get('/trial/samples/{kind}', [TrialController::class, 'sample'])->name('trial.sample');
    });
});

Route::prefix('device')->name('device.')->group(function () {
    Route::post('/login', [DeviceAuthController::class, 'login'])
        ->middleware('throttle:'.config('payguard.device.login_throttle'))
        ->name('login');

    Route::middleware(['auth:sanctum', 'throttle:'.config('payguard.device.api_throttle')])->group(function () {
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/heartbeat', [HeartbeatController::class, 'store'])->name('heartbeat.store');
        Route::post('/logout', [DeviceAuthController::class, 'logout'])->name('logout');
    });
});
