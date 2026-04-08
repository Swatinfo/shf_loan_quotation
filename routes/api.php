<?php

use App\Http\Controllers\Api\ConfigApiController;
use App\Http\Controllers\Api\NotesApiController;
use Illuminate\Support\Facades\Route;

// Public endpoints (no auth required) — used by PWA offline mode
Route::get('/config/public', [ConfigApiController::class, 'public']);
Route::get('/notes', [NotesApiController::class, 'get']);
