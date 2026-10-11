<?php

use App\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index'])->name('home');
Route::get('products/{product:sku}', [CatalogController::class, 'show'])->name('products.show');

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
