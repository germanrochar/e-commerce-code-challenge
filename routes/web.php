<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index'])->name('home');
Route::get('products/{product:sku}', [CatalogController::class, 'show'])->name('products.show');

Route::controller(CartController::class)
    ->prefix('cart')
    ->name('cart.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('items', 'store')->name('items.store');
        Route::patch('items/{product}', 'update')->whereNumber('product')->name('items.update');
        Route::delete('items/{product}', 'destroy')->whereNumber('product')->name('items.destroy');
    });

Route::middleware('auth')->group(function () {
    Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->whereUuid('order')->name('orders.show');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
