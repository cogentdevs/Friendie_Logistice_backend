<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// ==============================
// =========Admin Routes=========
// ==============================

Route::match(['get', 'post'],'/admin', [App\Http\Controllers\AdminController::class, 'login'])->name('adminlogin');

Route::match(['get', 'post'], '/admin/forgot-password', [App\Http\Controllers\AdminController::class, 'forgot_password_link']);
Route::match(['get', 'post'], '/admin/reset-password/{user_id}-{slug}', [App\Http\Controllers\AdminController::class, 'forgot_reset_password']);

Route::group(['middleware' => ['role:admin']], function () {

    // dashboard
    Route::get('/admin/dashboard', [App\Http\Controllers\AdminController::class, 'dashboard']);

    // change password
    Route::match(['get', 'post'],'/admin/change-password', [App\Http\Controllers\AdminController::class, 'change_password']);

    // admin logout
    Route::get('/admin/logout', [App\Http\Controllers\AdminController::class, 'logout'])->name('adminlogout');

    // contact info route
    Route::get('/admin/view-contact-info-item', [App\Http\Controllers\ContactInfoController::class, 'viewContactInfo']);
    Route::match(['get', 'post'], '/admin/add-contact-info-item', [App\Http\Controllers\ContactInfoController::class, 'addContactInfo']);
    Route::match(['get', 'post'], '/admin/add-contact-info-item/{column_name}/{id}', [App\Http\Controllers\ContactInfoController::class, 'editContactInfo']);
    Route::match(['get', 'post'], '/admin/edit-contact-info-item/{column_name}/{id}', [App\Http\Controllers\ContactInfoController::class, 'editContactInfo']);
    Route::get('/admin/delete-contact-info-item/{column_name}/{id}', [App\Http\Controllers\ContactInfoController::class, 'deleteContactInfo']);

    // meta tags route for SEO
    Route::get('/admin/view-meta-tags', [App\Http\Controllers\MetaTagController::class, 'viewMetaTag']);
    Route::match(['get', 'post'], '/admin/add-meta-tags', [App\Http\Controllers\MetaTagController::class, 'addMetaTag']);
    Route::match(['get', 'post'], '/admin/edit-meta-tags/{id}', [App\Http\Controllers\MetaTagController::class, 'editMetaTag']);
    Route::get('/admin/delete-meta-tag/{id}', [App\Http\Controllers\MetaTagController::class, 'deleteMetaTag']);

    // category route
    Route::get('/admin/view-categories', [App\Http\Controllers\CategoryController::class, 'viewCategory']);
    Route::match(['get', 'post'], '/admin/add-category', [App\Http\Controllers\CategoryController::class, 'addCategory']);
    Route::match(['get', 'post'], '/admin/edit-category/{id}', [App\Http\Controllers\CategoryController::class, 'editCategory']);
    Route::get('/admin/delete-category/{id}', [App\Http\Controllers\CategoryController::class, 'deleteCategory']);

    // country route
    Route::get('/admin/view-countries', [App\Http\Controllers\CountryController::class, 'viewCountry']);
    Route::match(['get', 'post'], '/admin/add-country', [App\Http\Controllers\CountryController::class, 'addCountry']);
    Route::match(['get', 'post'], '/admin/edit-country/{id}', [App\Http\Controllers\CountryController::class, 'editCountry']);
    Route::get('/admin/delete-country/{id}', [App\Http\Controllers\CountryController::class, 'deleteCountry']);

    // city route
    Route::get('/admin/view-cities', [App\Http\Controllers\CityController::class, 'viewCity']);
    Route::match(['get', 'post'], '/admin/add-city', [App\Http\Controllers\CityController::class, 'addCity']);
    Route::match(['get', 'post'], '/admin/edit-city/{id}', [App\Http\Controllers\CityController::class, 'editCity']);
    Route::get('/admin/delete-city/{id}', [App\Http\Controllers\CityController::class, 'deleteCity']);
    Route::get('/admin/search-cities/{name}', [App\Http\Controllers\CityController::class, 'searchCities']);

    // user route
    Route::get('/admin/view-users', [App\Http\Controllers\UserController::class, 'viewUser']);
    Route::match(['get', 'post'], '/admin/add-user', [App\Http\Controllers\UserController::class, 'addUser']);
    Route::match(['get', 'post'], '/admin/edit-user/{id}', [App\Http\Controllers\UserController::class, 'editUser']);
    Route::get('/admin/delete-user/{id}', [App\Http\Controllers\UserController::class, 'deleteUser']);

    // post ads listing route
    Route::get('/admin/view-listing/{ad_type?}', [App\Http\Controllers\PostAdsController::class, 'viewPostAds']);
    Route::get('/admin/view-pending-listing', [App\Http\Controllers\PostAdsController::class, 'viewPendingPostAds']);
    Route::match(['get', 'post'], '/admin/edit-listing/{id}', [App\Http\Controllers\PostAdsController::class, 'editPostAds']);
    Route::get('/admin/delete-listing/{id}', [App\Http\Controllers\PostAdsController::class, 'deletePostAds']);
    Route::get('/admin/mark-published/listing/{id}', [App\Http\Controllers\PostAdsController::class, 'markPublishedPostAds']);
    Route::get('/admin/mark-featured/listing/{id}', [App\Http\Controllers\PostAdsController::class, 'markFeaturedPostAds']);
    Route::get('/admin/unmark-featured/listing/{id}', [App\Http\Controllers\PostAdsController::class, 'unmarkFeaturedPostAds']);

    // contact message route
    Route::get('/admin/view-contact-messages', [App\Http\Controllers\ContactMessageController::class, 'viewContactMessage']);
    Route::get('/admin/delete-contact-message/{id}', [App\Http\Controllers\ContactMessageController::class, 'deleteContactMessage']);

    //subscribers route
    Route::get('/admin/view-subscribers', [App\Http\Controllers\SubscribeController::class, 'viewSubscribers']);
    Route::get('/admin/export-subscribers/xlsx', [App\Http\Controllers\SubscribeController::class, 'exportSubscribers']);

});

// ==============================
// =====Admin Routes End=========
// ==============================
// =========================================================================================
// ==============================
// =======Frontend Routes========
// ==============================

Route::get('/', [App\Http\Controllers\FrontController::class, 'index']);
Route::get('/about', [App\Http\Controllers\FrontController::class, 'about']);
Route::get('/terms-condition', [App\Http\Controllers\FrontController::class, 'termsAndCondition']);
Route::get('/listing', [App\Http\Controllers\FrontController::class, 'listings']);
Route::get('/listing/category/{id}/{slug?}', [App\Http\Controllers\FrontController::class, 'listings']);
Route::match(['get', 'post'], '/listing/search/results', [App\Http\Controllers\FrontController::class, 'searchListings']);
Route::get('/listing-details/{id}/{slug}', [App\Http\Controllers\FrontController::class, 'listing_details']);

Route::match(['get', 'post'], '/contact', [App\Http\Controllers\ContactMessageController::class, 'contact']);

// User Routes
Route::match(['get', 'post'], '/sign-up', [App\Http\Controllers\UserController::class, 'register']);
Route::match(['get', 'post'], '/login', [App\Http\Controllers\UserController::class, 'login']);

Route::match(['get', 'post'], '/forgot-password', [App\Http\Controllers\UserController::class, 'forgot_password_link']);
Route::match(['get', 'post'], '/reset-password/{user_id}-{slug}', [App\Http\Controllers\UserController::class, 'forgot_reset_password']);

Route::match(['get', 'post'], '/activate-your-account/{user_id}-{slug}', [App\Http\Controllers\UserController::class, 'activateYourAccount']);

Route::group(['middleware' => ['role:user']], function () {

    // dashboard
    Route::get('/user/dashboard', [App\Http\Controllers\UserController::class, 'dashboard']);

    // edit Profile
    Route::match(['get', 'post'], '/user/profile', [App\Http\Controllers\UserController::class, 'edit_profile']);

    // change password
    Route::post('/user/change-password', [App\Http\Controllers\UserController::class, 'change_password']);

    // admin logout
    Route::get('/user/logout', [App\Http\Controllers\UserController::class, 'logout'])->name('adminlogout');

    // listing route
    Route::get('/user/my-listing/{ad_type?}', [App\Http\Controllers\PostAdsController::class, 'viewPostAds']);
    Route::get('/user/listing-details/{id}/{slug?}', [App\Http\Controllers\PostAdsController::class, 'viewPostAdsDetails']);
    Route::match(['get', 'post'], '/user/add-listing', [App\Http\Controllers\PostAdsController::class, 'addPostAds']);
    Route::match(['get', 'post'], '/user/edit-listing/{id}', [App\Http\Controllers\PostAdsController::class, 'editPostAds']);
    Route::get('/user/delete-listing/{id}', [App\Http\Controllers\PostAdsController::class, 'deletePostAds']);
    
    // messages
    Route::get('/user/messages', [App\Http\Controllers\UserController::class, 'messagesPostAds']);
    Route::get('/user/messages/{post_id}/{slug}', [App\Http\Controllers\UserController::class, 'messagesPostAdsUserList']);

    // create messages room
    Route::post('/user/create/room', [App\Http\Controllers\PostAdMessageController::class, 'createMessageRoom']);

    // get room messages
    Route::get('/user/messages/{post_id}/{post_slug}/{room_id}={room_slug}', [App\Http\Controllers\PostAdMessageController::class, 'getRoomMessages']);

    // msg send
    Route::post('/user/msg/send', [App\Http\Controllers\PostAdMessageController::class, 'userMessageSend']);

    // get partials
    Route::get('/user/get-chat-user-list/{post_id}', [App\Http\Controllers\PostAdMessageController::class, 'getUserChatList']);
    Route::get('/user/get-chats/{post_ad_room_id}', [App\Http\Controllers\PostAdMessageController::class, 'getUserChats']);

    // search caht user list
    Route::get('/user/get-search-users/{post_id}/{search}', [App\Http\Controllers\PostAdMessageController::class, 'getSearchChatUsers']);

    // bookmarks
    Route::get('/user/bookmarks', [App\Http\Controllers\UserController::class, 'bookmarks']);

    // reviews
    Route::get('/user/reviews', [App\Http\Controllers\UserController::class, 'reviews']);

    // post review
    Route::post('/user/post-review', [App\Http\Controllers\UserController::class, 'postRateReview']);

    
});

// ==============================
// ======Frontend Routes End=====
// ==============================
// =========================================================================================
// ==============================
// =========Ajax Routes==========
// ==============================

// ajax Admin/Sub-admin
Route::get('/view/city-list/{country_id}', [App\Http\Controllers\AjaxController::class,'viewCountryCityList']);
Route::get('/view/country/code/{country_id}', [App\Http\Controllers\AjaxController::class,'viewCountryCode']);

// subscribe
Route::post('/subscribe', [App\Http\Controllers\AjaxController::class, 'subscribe']);


// Route::get('/', function () {
//     return view('welcome');
// });

// Route::get('/dashboard', function () {
//     return view('dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

// Route::middleware('auth')->group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
// });

require __DIR__.'/auth.php';
