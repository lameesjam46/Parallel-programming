<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\OrderController; 
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::post('/reset-semaphore', function () {
    \Illuminate\Support\Facades\Cache::forget('checkout_semaphore_count');
    return response()->json(['status' => true]);
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);


//admin
Route::get('/sendNotificationToAll',    [AdminController::class, 'sendNotificationToAll'])->middleware('Performance');


Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/products',  [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);

    Route::post('/cart',        [CartController::class, 'addToCart']);
    Route::get('/cart',         [CartController::class, 'viewCart']);
    Route::delete('/cart/{id}', [CartController::class, 'removeItem']);

   
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::post('/checkout-semaphore', [OrderController::class, 'checkoutWithSemaphore']); 
    Route::post('/checkout-safe', [OrderController::class, 'checkoutWithSemaphore']);
});
});


Route::get('/process-sequential', [OrderController::class, 'processSequentially']);


Route::post('/protected-checkout', [OrderController::class, 'protectedCheckout']);
Route::post('/unprotected-checkout', [OrderController::class, 'unprotectedCheckout']);


Route::post('/test', function () {
    return response()->json([
        'status' => 'ok'
    ]);
});


Route::post('/orders/place/{mode?}', [ProductController::class, 'placeOrder'])->middleware('log.concurrency');
Route::get('/products/most-requested', [ProductController::class, 'getMostRequestedProducts']);
Route::post('/transaction-demo', [OrderController::class, 'transactionDemo']);