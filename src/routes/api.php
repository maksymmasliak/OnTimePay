<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('invoices', InvoiceController::class)
        ->except(['index', 'show', 'create', 'edit']);

    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send']);
});
