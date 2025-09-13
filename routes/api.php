<?php
// routes/api.php (updated)

use App\Http\Controllers\Api\V1\TrackController;
use App\Http\Controllers\Api\V1\ClipController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\StreamController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['auth:sanctum'])->group(function () {
    // Tracks
    Route::apiResource('tracks', TrackController::class)->except(['update', 'destroy']);
    Route::post('tracks/{track}/clips', [TrackController::class, 'createClip'])->name('api.tracks.clips.store');
    
    // Clips
    Route::apiResource('clips', ClipController::class)->only(['index', 'show', 'destroy']);
    
    // Webhooks
    Route::post('webhooks/{webhook}/replay', [WebhookController::class, 'replay'])->name('api.webhooks.replay');
});

// Public streaming routes (token protected)
Route::prefix('streams/hls')->group(function () {
    Route::get('{track_uuid}/master.m3u8', [StreamController::class, 'trackMaster'])
        ->name('stream.track.master')
        ->middleware(['signed.stream', 'concurrency.limit']);
    
    Route::get('{track_uuid}/{bitrate}/index.m3u8', [StreamController::class, 'trackVariant'])
        ->name('stream.track.variant')
        ->middleware(['signed.stream', 'concurrency.limit']);
    
    Route::get('{track_uuid}/{bitrate}/seg-{segment}.ts', [StreamController::class, 'trackSegment'])
        ->name('stream.track.segment')
        ->middleware(['signed.stream', 'concurrency.limit']);
    
    Route::get('clip/{clip_uuid}/master.m3u8', [StreamController::class, 'clipMaster'])
        ->name('stream.clip.master')
        ->middleware(['signed.stream', 'concurrency.limit']);
});