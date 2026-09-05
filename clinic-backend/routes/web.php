<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SpaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', [SpaController::class, 'show']);
Route::get('/assets/{filename}', [SpaController::class, 'asset']);
Route::get('/clinic-icon.svg', fn () => response()->file(base_path('../clinic-frontend/public/clinic-icon.svg'), ['Content-Type' => 'image/svg+xml']));
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
Route::get('/storage/gallery/{filename}', function (string $filename) {
    abort_unless(preg_match('/^[a-f0-9-]{36}\.webp$/', $filename), 404);
    $path = Storage::disk('public')->path('gallery/'.$filename);
    abort_unless(is_file($path), 404);

    return response()->file($path, ['Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff']);
});
Route::get('/{path}', [SpaController::class, 'show'])->where('path', '(?!api(?:/|$)|storage(?:/|$)).*');
Route::prefix('api')->group(function () {
    Route::get('auth/csrf', fn () => ['token' => csrf_token()]);
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', fn (Request $r) => $r->user());
        Route::post('auth/logout', [AuthController::class, 'logout']);
    });
    Route::prefix('public')->middleware('throttle:public')->group(function () {
        Route::get('clinic', [PublicController::class, 'clinic']);
        Route::get('services', [PublicController::class, 'services']);
        Route::get('content', [PublicController::class, 'content']);
        Route::get('availability', [PublicController::class, 'availability']);
        Route::post('appointments', [PublicController::class, 'book'])->middleware('throttle:booking');
        Route::get('appointments/{token}', [PublicController::class, 'show'])->middleware('throttle:lookup');
        Route::patch('appointments/{token}', [PublicController::class, 'change'])->middleware('throttle:manage');
        Route::post('enquiries', [PublicController::class, 'enquiry'])->middleware('throttle:enquiry');
    });
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin,receptionist,doctor'])->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard']);
        Route::get('appointments', [AdminController::class, 'appointments']);
        Route::get('appointments/{appointment}', [AdminController::class, 'showAppointment'])->whereNumber('appointment');
        Route::middleware('role:admin,receptionist')->group(function () {
            Route::post('appointments', [AdminController::class, 'book']);
            Route::patch('appointments/{appointment}/status', [AdminController::class, 'change']);
            Route::get('patients', [AdminController::class, 'patients']);
        });
        Route::middleware('role:admin')->group(function () {
            Route::get('reports/appointments', [AdminController::class, 'report']);
            Route::get('reports/export', [AdminController::class, 'export']);
            Route::get('settings', [AdminController::class, 'settings']);
            Route::put('settings', [AdminController::class, 'saveSettings']);
            Route::get('audit', [AdminController::class, 'audit']);
            Route::get('notifications', [AdminController::class, 'notifications']);
            Route::post('notifications/{notification}/retry', [AdminController::class, 'retry']);
            Route::get('media', [AdminController::class, 'media']);
            Route::post('media', [AdminController::class, 'upload']);
            Route::put('media/{media}', [AdminController::class, 'updateMedia']);
        });
        Route::get('{resource}', [AdminController::class, 'index']);
        Route::post('{resource}', [AdminController::class, 'save']);
        Route::put('{resource}/{id}', [AdminController::class, 'save'])->whereNumber('id');
        Route::delete('{resource}/{id}',[AdminController::class, 'destroy'])->whereNumber('id');
    });
});
