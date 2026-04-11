<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\HoldController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\SeatmapController;
use App\Http\Controllers\Api\SocialController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketTransferController;
use App\Http\Controllers\Api\ValidationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'show']);

Route::get('/feed/featured', [FeedController::class, 'featured']);

Route::get('/events/{eventId}/seatmap', [SeatmapController::class, 'show']);

Route::post('/events/{eventId}/holds', [HoldController::class, 'store']);
Route::post('/holds/{holdId}/login-grace', [HoldController::class, 'loginGrace']);
Route::delete('/holds/{holdId}', [HoldController::class, 'destroy']);
Route::get('/holds/{holdId}/time', [HoldController::class, 'time']);

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('jwt.auth')->get('/auth/me', [AuthController::class, 'me']);
Route::middleware('jwt.auth')->get('/feed/for-you', [FeedController::class, 'forYou']);
Route::middleware('jwt.auth')->post('/orders', [OrderController::class, 'store']);
Route::middleware('jwt.auth')->post('/orders/{order}/confirm-payment', [OrderController::class, 'confirmPayment']);
Route::middleware('jwt.auth')->get('/tickets', [TicketController::class, 'index']);
Route::middleware('jwt.auth')->get('/tickets/{ticketId}/qr', [TicketController::class, 'showQr'])
    ->whereUuid('ticketId');
Route::middleware('jwt.auth')->post('/tickets/{ticketId}/transfer', [TicketTransferController::class, 'store'])
    ->whereUuid('ticketId');

Route::middleware('jwt.auth')->get('/social/friends', [SocialController::class, 'friends']);
Route::middleware('jwt.auth')->get('/social/friend-invites', [SocialController::class, 'invitesIndex']);
Route::middleware('jwt.auth')->post('/social/friend-invites', [SocialController::class, 'invitesStore']);
Route::middleware('jwt.auth')->patch('/social/friend-invites/{inviteId}', [SocialController::class, 'invitesPatch']);

Route::middleware(['jwt.auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/summary', [AdminController::class, 'summary']);
    Route::post('/discovery/sync', [AdminController::class, 'discoverySync']);
});

Route::middleware('jwt.auth')->post('/validation/scan', [ValidationController::class, 'scan']);
