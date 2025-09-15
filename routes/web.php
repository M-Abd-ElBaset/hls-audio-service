<?php

use App\Http\Controllers\Web\TrackController;
use App\Http\Controllers\Web\PlayerController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Authentication routes (provided by Breeze)
require __DIR__.'/auth.php';

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    // Track management
    Route::get('/tracks', [TrackController::class, 'index'])->name('tracks.index');
    Route::get('/tracks/upload', [TrackController::class, 'create'])->name('tracks.create');
    Route::post('/tracks', [TrackController::class, 'store'])->name('tracks.store');
    Route::get('/tracks/{track}', [TrackController::class, 'show'])->name('tracks.show');
    
    // Player
    Route::get('/player/track/{track}', [PlayerController::class, 'showTrack'])->name('player.track');
    Route::get('/player/clip/{clip}', [PlayerController::class, 'showClip'])->name('player.clip');
});
