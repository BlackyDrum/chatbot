<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\HomeController;
use App\Http\Middleware\ValidateRemainingRequests;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware(['auth'])->group(function() {
    Route::get('/', [HomeController::class, 'show'])->name('home');

    Route::get('/chat/{id}', [ChatController::class, 'show'])->name('chat');

    Route::middleware(ValidateRemainingRequests::class)->group(function() {
        Route::post('/create-conversation', [HomeController::class, 'createConversation'])->name('create-conversation');

        Route::post('/chat/chat-agent', [ChatController::class, 'chat'])->name('chat-agent');
    });
});

require __DIR__.'/auth.php';
