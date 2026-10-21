<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ChatFeedbackController;
use Illuminate\Support\Facades\Route;

Route::post('/chat', ChatController::class)
    ->middleware('throttle:10,1')
    ->name('api.chat');

Route::post('/chat/feedback', ChatFeedbackController::class)
    ->middleware('throttle:30,1')
    ->name('api.chat.feedback');
