<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\V1\TrackController;
use App\Http\Controllers\Api\V1\ClipController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\StreamController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Public authentication routes
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Public streaming routes (token protected)
Route::prefix('streams/hls')->group(function () {
    Route::get('{track_uuid}/master.m3u8', [StreamController::class, 'trackMaster'])
        ->name('stream.track.master')
        ->middleware(['signed.stream', 'concurrency.limit', 'rate.limit:playlist']);
    
    Route::get('{track_uuid}/{bitrate}/index.m3u8', [StreamController::class, 'trackVariant'])
        ->name('stream.track.variant')
        ->middleware(['signed.stream', 'concurrency.limit', 'rate.limit:playlist']);
    
    Route::get('{track_uuid}/{bitrate}/seg-{segment}.ts', [StreamController::class, 'trackSegment'])
        ->name('stream.track.segment')
        ->middleware(['signed.stream', 'concurrency.limit', 'rate.limit:segments']);
    
    Route::get('clip/{clip_uuid}/master.m3u8', [StreamController::class, 'clipMaster'])
        ->name('stream.clip.master')
        ->middleware(['signed.stream', 'concurrency.limit', 'rate.limit:playlist']);
});

// Protected API routes (require Sanctum token)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // API v1 routes with rate limiting
    Route::prefix('v1')->group(function () {
        // Tracks - rate limit uploads
        Route::apiResource('tracks', TrackController::class)->except(['update', 'destroy']);
        Route::post('tracks/{track}/clips', [TrackController::class, 'createClip'])
            ->middleware('rate.limit:uploads');
        
        // Clips
        Route::apiResource('clips', ClipController::class)->only(['index', 'show', 'destroy']);
        
        // Webhooks
        Route::post('webhooks/{webhook}/replay', [WebhookController::class, 'replay']);
    });
});

// CSRF cookie route for Sanctum
Route::get('/sanctum/csrf-cookie', function (Request $request) {
    return response()->json(['message' => 'CSRF cookie set']);
});