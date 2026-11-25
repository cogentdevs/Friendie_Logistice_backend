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
    Route::prefix('customer')->name('customer.')->group(function () {
        $controller = \App\Http\Controllers\Api\V1\CustomerController::class;
        $protected = ['auth:sanctum', \App\Http\Middleware\EligibleMobileCustomer::class];
        $readOnly = [\App\Http\Middleware\ReadOnlyCustomerSanctum::class, ...$protected];
        $notifications = \App\Http\Controllers\Api\V1\CustomerNotificationController::class;
        Route::get('notifications', [$notifications, 'index'])->middleware($readOnly)->name('notifications.index');
        Route::get('notifications/counts', [$notifications, 'counts'])->middleware($readOnly)->name('notifications.counts');
        Route::post('notifications/{id}/read', [$notifications, 'read'])->whereNumber('id')->middleware($protected)->name('notifications.read');
        $deals = \App\Http\Controllers\Api\V1\CustomerDealController::class;
        Route::get('deals', [$deals, 'index'])->middleware($readOnly)->name('deals.index');
        Route::get('deals/{id}/contact', [$deals, 'contact'])->whereNumber('id')->middleware($readOnly)->name('deals.contact');
        Route::get('deals/{id}', [$deals, 'show'])->whereNumber('id')->middleware($readOnly)->name('deals.show');
        Route::post('deals', [$deals, 'store'])->middleware($protected)->name('deals.store');
        foreach (['accept', 'reject', 'confirm', 'cancel', 'pickup', 'complete'] as $action) {
            Route::post('deals/{id}/'.$action, [$deals, 'transition'])->whereNumber('id')
                ->defaults('action', $action)->middleware($protected)->name('deals.'.$action);
        }
        $reviews = \App\Http\Controllers\Api\V1\CustomerReviewController::class;
        Route::get('reviews/received', [$reviews, 'received'])->middleware($readOnly)->name('reviews.received');
        Route::get('reviews/given', [$reviews, 'given'])->middleware($readOnly)->name('reviews.given');
        Route::post('reviews', [$reviews, 'store'])->middleware($protected)->name('reviews.store');
        $chat = \App\Http\Controllers\Api\V1\CustomerChatController::class;
        Route::get('conversations', [$chat, 'index'])->middleware($readOnly)->name('conversations.index');
        Route::get('conversations/{id}', [$chat, 'show'])->whereNumber('id')->middleware($readOnly)->name('conversations.show');
        Route::get('conversations/{id}/messages', [$chat, 'messages'])->whereNumber('id')->middleware($readOnly)->name('conversations.messages');
        Route::post('conversations', [$chat, 'store'])->middleware($protected)->name('conversations.store');
        Route::post('conversations/{id}/messages', [$chat, 'send'])->whereNumber('id')->middleware($protected)->name('conversations.send');
        Route::post('conversations/{id}/read', [$chat, 'read'])->whereNumber('id')->middleware($protected)->name('conversations.read');
        $listings = \App\Http\Controllers\Api\V1\CustomerListingController::class;
        Route::get('listings', [$listings, 'index'])->middleware($readOnly)->name('listings.index');
        Route::get('listings/{id}', [$listings, 'show'])->whereNumber('id')->middleware($readOnly)->name('listings.show');
        Route::middleware($protected)->group(function () use ($listings) {
            Route::post('listings', [$listings, 'store'])->name('listings.store');
            Route::put('listings/{id}', [$listings, 'update'])->whereNumber('id')->name('listings.update');
            Route::delete('listings/{id}', [$listings, 'destroy'])->whereNumber('id')->name('listings.destroy');
            Route::post('listings/{id}/image', [$listings, 'uploadImage'])->whereNumber('id')->name('listings.image.upload');
            Route::delete('listings/{id}/image', [$listings, 'removeImage'])->whereNumber('id')->name('listings.image.remove');
        });
        Route::get('dashboard', [$controller, 'dashboard'])->middleware($readOnly)->name('dashboard');
        Route::get('profile', [$controller, 'profile'])->middleware($readOnly)->name('profile.show');
        Route::middleware($protected)->group(function () use ($controller) {
            Route::put('profile', [$controller, 'updateProfile'])->name('profile.update');
            Route::post('profile/avatar', [$controller, 'uploadAvatar'])->name('avatar.upload');
            Route::delete('profile/avatar', [$controller, 'removeAvatar'])->name('avatar.remove');
            Route::post('change-password', [$controller, 'changePassword'])->name('password.change');
        });
    });
    Route::prefix('auth')->name('auth.')->group(function () {
        $controller = \App\Http\Controllers\Api\V1\AuthController::class;
        Route::post('register', [$controller, 'register'])->name('register');
        Route::post('login', [$controller, 'login'])->name('login');
        Route::post('forgot-password', [$controller, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [$controller, 'resetPassword'])->name('reset-password');
        Route::post('resend-verification', [$controller, 'resendVerification'])->name('resend-verification');
        Route::middleware(['auth:sanctum', \App\Http\Middleware\EligibleMobileCustomer::class])->group(function () use ($controller) {
            Route::get('me', [$controller, 'me'])->name('me');
            Route::post('logout', [$controller, 'logout'])->name('logout');
        });
    });
    Route::get('health', HealthController::class)->name('health');
    Route::get('home', HomeController::class)->name('home');
    Route::get('categories', CategoryController::class)->name('categories');
    Route::get('countries', CountryController::class)->name('countries');
    Route::get('cities', CityController::class)->name('cities');
    Route::get('currencies', CurrencyController::class)->name('currencies');
    Route::get('listings', [ListingController::class, 'index'])->name('listings.index');
    Route::get('listings/{id}', [ListingController::class, 'show'])->whereNumber('id')->name('listings.show');
    Route::get('listings/{id}/reviews', [\App\Http\Controllers\Api\V1\CustomerReviewController::class, 'listingReviews'])->whereNumber('id')->name('listings.reviews');
});
