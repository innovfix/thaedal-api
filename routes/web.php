<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CreatorController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\PaymentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->route('admin.login');
});

// Convenience redirects to avoid 404 when users paste extra text into the URL.
Route::get('/admin', function () {
    return redirect()->route('admin.login');
});

// Handles URLs like: /admin/login%20Login%20credentials
Route::get('/admin/login{any}', function () {
    return redirect()->route('admin.login');
})->where('any', '.+');

// Admin Authentication Routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth:admin');

    // Protected Admin Routes
    Route::middleware('auth:admin')->group(function () {
        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // User Management
        Route::resource('users', UserController::class)->except(['create', 'store', 'edit', 'update']);
        Route::post('users/{user}/toggle-subscription', [UserController::class, 'toggleSubscription'])->name('users.toggle-subscription');

        // Video Management
        Route::resource('videos', VideoController::class);

        // Category Management
        Route::resource('categories', CategoryController::class)->except(['show']);

        // Creator Management
        Route::get('creators', [CreatorController::class, 'index'])->name('creators.index');
        Route::get('creators/{creator}', [CreatorController::class, 'show'])->name('creators.show');

        // Subscription Management
        Route::resource('subscriptions', SubscriptionController::class)->only(['index', 'show']);
        Route::get('subscriptions/plans', [SubscriptionController::class, 'plans'])->name('subscriptions.plans');
        Route::post('subscriptions/{subscription}/status', [SubscriptionController::class, 'updateStatus'])->name('subscriptions.updateStatus');

        // Payment Management
        Route::resource('payments', PaymentController::class)->only(['index', 'show']);
    });
});

