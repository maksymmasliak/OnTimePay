<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\StripeCheckoutController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('invoices', InvoiceController::class)
        ->except(['index', 'show', 'create', 'edit']);
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send']);
    Route::post('invoices/{invoice}/checkout', [StripeCheckoutController::class, 'store']);
});

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);
