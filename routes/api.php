<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\SocialLinkController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PortfolioItemController;
use App\Http\Controllers\MediaItemController;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::middleware([
    EnsureFrontendRequestsAreStateful::class,
    'auth:sanctum'
])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile/update', [UserController::class, 'updateProfile']);

    // Portfolio Items CRUD
    Route::get('/users/{id}/portfolio', [PortfolioItemController::class, 'getByUser']);
    Route::get('/portfolio', [PortfolioItemController::class, 'index']);
    Route::get('/portfolio/{id}', [PortfolioItemController::class, 'show']);
    Route::post('/portfolio', [PortfolioItemController::class, 'store']);
    Route::put('/portfolio/{id}', [PortfolioItemController::class, 'update']);
    Route::delete('/portfolio/{id}', [PortfolioItemController::class, 'destroy']);

    // Media Items CRUD
    Route::get('/media/portfolio/{portfolio_item_id}', [MediaItemController::class, 'index']);
    Route::get('/media/{portfolioItemId}', [MediaItemController::class, 'show']);
    Route::post('/media', [MediaItemController::class, 'store']);
    Route::put('/media/{id}', [MediaItemController::class, 'update']);
    Route::delete('/media/{id}', [MediaItemController::class, 'destroy']);
    /*
    |--------------------------------------------------------------------------
    | Follows APIs
    |--------------------------------------------------------------------------
    */
    Route::post('/users/{id}/follow', [FollowController::class, 'toggleFollow']);
    Route::get('/followers', [FollowController::class, 'followers']);
    Route::get('/followings', [FollowController::class, 'followings']);

    /*
    |--------------------------------------------------------------------------
    | Messaging APIs
    |--------------------------------------------------------------------------
    */
    // Send a new message
    Route::post('/messages/send', [MessageController::class, 'send']);

    // Retrieve conversation with a specific user (marks unread messages as read)
    Route::get('/messages/{userId}', [MessageController::class, 'retrieve']);

    // Mark a single message as read (if needed separately)
    Route::patch('/messages/{id}/read', [MessageController::class, 'markAsRead']);

    // Mark multiple messages as delivered (typically called by recipient's client)
    Route::post('/messages/delivered', [MessageController::class, 'markAsDelivered']);

    // Edit a message (sender only)
    Route::put('/messages/{id}', [MessageController::class, 'update']);

    // Delete a message (sender only)
    Route::delete('/messages/{id}', [MessageController::class, 'destroy']);
    Route::delete('/messages/conversation/{userId}', [MessageController::class, 'destroyConversation']);

    /*
    |--------------------------------------------------------------------------
    | Offers Routes (Publisher Only)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:publisher')->group(function () {
        Route::post('/offers', [OfferController::class, 'store']);
        Route::put('/offers/{id}', [OfferController::class, 'update']);
        Route::delete('/offers/{id}', [OfferController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Offers Routes (Influencer Only)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:influencer')->group(function () {
        Route::post('/applications', [ApplicationController::class, 'store']);
        Route::put('/applications/{id}', [ApplicationController::class, 'update']);
        Route::delete('/applications/{id}', [ApplicationController::class, 'destroy']);
        Route::apiResource('social-links', SocialLinkController::class);

    });
    /*
   |--------------------------------------------------------------------------
   | Applications View Routes (Restricted to Related Users)
   |--------------------------------------------------------------------------
   */
    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::get('/applications/{id}', [ApplicationController::class, 'show']);
    Route::get('/applications/user/{userId}', [ApplicationController::class, 'getByUser']);
    Route::get('/applications/offer/{offerId}', [ApplicationController::class, 'getByOffer']);
Broadcast::routes();});

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
// Offers - Public view
Route::get('/offers', [OfferController::class, 'index']);
Route::get('/offers/{id}', [OfferController::class, 'show']);

// Auth (throttled)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Admin-only routes
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/users', [UserController::class, 'store']);
});
