<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\PinLoginController;
use App\Http\Controllers\PlaidController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TransactionTypeController;
use App\Http\Middleware\EnsurePlaidConfigured;

Route::get('/', function () {
    return redirect('/plaid');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [PinLoginController::class, 'show'])->name('login');
    Route::post('/login', [PinLoginController::class, 'login']);
});

Route::post('/logout', [PinLoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/banks', [BankAccountController::class, 'index'])->name('banks.index');
    Route::resource('types', TransactionTypeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
});

Route::middleware('auth')->prefix('plaid')->group(function () {
    Route::get('/', [PlaidController::class, 'index'])->name('plaid.index');
    Route::get('/transactions', [PlaidController::class, 'transactions']);
    Route::patch('/transactions/{transactionId}/type', [PlaidController::class, 'updateType']);

    // Everything that calls Plaid needs saved credentials.
    Route::middleware(EnsurePlaidConfigured::class)->group(function () {
        Route::post('/link-token', [PlaidController::class, 'createLinkToken']);
        Route::post('/exchange-token', [PlaidController::class, 'exchangePublicToken']);
        Route::post('/sync', [PlaidController::class, 'syncTransactions']);
    });
});
