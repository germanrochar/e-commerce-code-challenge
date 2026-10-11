<?php

use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', fn () => redirect()->route('admin.products.index'))->name('home');

        Route::resource('products', ProductController::class)->except(['show']);

        Route::controller(ProductImportController::class)
            ->prefix('imports')
            ->name('imports.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('template', 'template')->name('template');
                Route::get('{import}', 'show')->whereNumber('import')->name('show');
                Route::get('{import}/rejected', 'rejected')->whereNumber('import')->name('rejected');
            });
    });
