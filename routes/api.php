<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CountryController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\ListingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('health', HealthController::class)->name('health');
    Route::get('home', HomeController::class)->name('home');
    Route::get('categories', CategoryController::class)->name('categories');
    Route::get('countries', CountryController::class)->name('countries');
    Route::get('cities', CityController::class)->name('cities');
    Route::get('currencies', CurrencyController::class)->name('currencies');
    Route::get('listings', [ListingController::class, 'index'])->name('listings.index');
    Route::get('listings/{id}', [ListingController::class, 'show'])->whereNumber('id')->name('listings.show');
});
