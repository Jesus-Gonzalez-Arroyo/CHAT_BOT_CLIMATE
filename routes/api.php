<?php

use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

Route::prefix('conversations')->group(function () {
    Route::get('/', [ConversationController::class, 'index']);
    Route::post('/', [ConversationController::class, 'store']);
    Route::get('/{conversation}', [ConversationController::class, 'show']);
    Route::delete('/{conversation}', [ConversationController::class, 'destroy']);
});

Route::post('/messages', [MessageController::class, 'store']);

# cGFuZ29saW4=