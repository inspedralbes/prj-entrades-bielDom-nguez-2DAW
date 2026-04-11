<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\HoldController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\SeatmapController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\ValidationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'show']);

Route::get('/events/{eventId}/seatmap', [SeatmapController::class, 'show']);

Route::post('/events/{eventId}/holds', [HoldController::class, 'store']);
Route::post('/holds/{holdId}/login-grace', [HoldController::class, 'loginGrace']);
Route::delete('/holds/{holdId}', [HoldController::class, 'destroy']);
Route::get('/holds/{holdId}/time', [HoldController::class, 'time']);

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('jwt.auth')->get('/auth/me', [AuthController::class, 'me']);
Route::middleware('jwt.auth')->post('/orders', [OrderController::class, 'store']);
Route::middleware('jwt.auth')->post('/orders/{order}/confirm-payment', [OrderController::class, 'confirmPayment']);
Route::middleware('jwt.auth')->get('/tickets', [TicketController::class, 'index']);
Route::middleware('jwt.auth')->get('/tickets/{ticketId}/qr', [TicketController::class, 'showQr'])
    ->whereUuid('ticketId');
Route::middleware('jwt.auth')->post('/validation/scan', [ValidationController::class, 'scan']);
