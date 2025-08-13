<?php

use App\Http\Controllers\OfferController;
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
    | Offers Routes (Publisher Only)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:publisher')->group(function () {
        Route::post('/offers', [OfferController::class, 'store']);
        Route::put('/offers/{id}', [OfferController::class, 'update']);
        Route::delete('/offers/{id}', [OfferController::class, 'destroy']);
    });
});

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
