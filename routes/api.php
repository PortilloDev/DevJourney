<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;

Route::post('/chat', ChatController::class)
    ->middleware('throttle:10,1')
    ->name('api.chat');
