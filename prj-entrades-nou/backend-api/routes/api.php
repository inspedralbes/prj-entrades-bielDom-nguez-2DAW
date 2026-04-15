<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminDiscoveryController;
use App\Http\Controllers\Api\AdminLogController;
use App\Http\Controllers\Api\AdminMonitorController;
use App\Http\Controllers\Api\AdminAnalyticsController;
use App\Http\Controllers\Api\AdminUsersController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\SavedEventsController;
use App\Http\Controllers\Api\SearchEventsController;
use App\Http\Controllers\Api\PlacesController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\HoldController;
use App\Http\Controllers\Api\InternalSeatHoldController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\SavedEventsController;
use App\Http\Controllers\Api\SearchEventsController;
use App\Http\Controllers\Api\SeatmapController;
use App\Http\Controllers\Api\SocialController;
use App\Http\Controllers\Api\SocialUserController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketTransferController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\ValidationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'show']);

Route::get('/feed/featured', [FeedController::class, 'featured']);

Route::get('/search/events', [SearchEventsController::class, 'index']);

Route::get('/events/nearby', [SearchEventsController::class, 'nearby']);
Route::get('/cities/search', [SearchEventsController::class, 'searchCities']);
Route::get('/places/autocomplete', [PlacesController::class, 'autocomplete']);
Route::get('/places/details', [PlacesController::class, 'details']);
Route::get('/events/{eventId}', [SearchEventsController::class, 'show']);
Route::get('/events/{eventId}/price', [SearchEventsController::class, 'eventPrice']);

Route::get('/events/{eventId}/seatmap', [SeatmapController::class, 'show']);

Route::middleware('internal.socket')->post('/internal/seat-holds/release-user', [InternalSeatHoldController::class, 'releaseUser']);
Route::middleware('internal.socket')->post('/internal/seat-holds/release-user-event', [InternalSeatHoldController::class, 'releaseUserEvent']);

Route::post('/events/{eventId}/holds', [HoldController::class, 'store']);
Route::post('/holds/{holdId}/login-grace', [HoldController::class, 'loginGrace']);
Route::delete('/holds/{holdId}', [HoldController::class, 'destroy']);
Route::get('/holds/{holdId}/time', [HoldController::class, 'time']);

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('jwt.auth')->get('/auth/me', [AuthController::class, 'me']);
Route::middleware('jwt.auth')->post('/presence/ping', [PresenceController::class, 'ping']);
Route::middleware('jwt.auth')->get('/user/profile', [UserProfileController::class, 'show']);
Route::middleware('jwt.auth')->patch('/user/profile', [UserProfileController::class, 'updateProfile']);
Route::middleware('jwt.auth')->get('/feed/for-you', [FeedController::class, 'forYou']);
Route::middleware('jwt.auth')->get('/saved-events', [SavedEventsController::class, 'index']);
Route::middleware('jwt.auth')->post('/saved-events', [SavedEventsController::class, 'store']);
Route::middleware('jwt.auth')->delete('/saved-events/{eventId}', [SavedEventsController::class, 'destroy']);
Route::middleware('jwt.auth')->post('/events/{eventId}/seat-holds', [SeatmapController::class, 'holdSeat']);
Route::middleware('jwt.auth')->post('/events/{eventId}/seat-holds/release', [SeatmapController::class, 'releaseSeat']);
Route::middleware('jwt.auth')->post('/events/{eventId}/seat-holds/release-all', [SeatmapController::class, 'releaseAllMyHolds']);

Route::middleware('jwt.auth')->post('/orders', [OrderController::class, 'store']);
Route::middleware('jwt.auth')->post('/orders/quantity', [OrderController::class, 'storeQuantity']);
Route::middleware('jwt.auth')->post('/orders/cinema-seats', [OrderController::class, 'storeCinemaSeats']);
Route::middleware('jwt.auth')->post('/orders/{order}/confirm-payment', [OrderController::class, 'confirmPayment']);
Route::middleware('jwt.auth')->get('/tickets', [TicketController::class, 'index']);
Route::middleware('jwt.auth')->get('/tickets/{ticketId}/qr', [TicketController::class, 'showQr'])
    ->whereUuid('ticketId');
Route::middleware('jwt.auth')->post('/tickets/{ticketId}/transfer', [TicketTransferController::class, 'store'])
    ->whereUuid('ticketId');

Route::middleware('jwt.auth')->get('/notifications', [NotificationController::class, 'index']);
Route::middleware('jwt.auth')->post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
Route::middleware('jwt.auth')->post('/notifications/mark-read-for-actor/{actorUserId}', [NotificationController::class, 'markReadForActor'])->whereNumber('actorUserId');
Route::middleware('jwt.auth')->patch('/notifications/{id}', [NotificationController::class, 'update'])->whereNumber('id');

Route::middleware('jwt.auth')->get('/social/friends', [SocialController::class, 'friends']);
Route::middleware('jwt.auth')->get('/social/discover/search', [SocialUserController::class, 'search']);
Route::middleware('jwt.auth')->get('/social/thread-notification-mutes', [SocialUserController::class, 'threadMutesIndex']);
Route::middleware('jwt.auth')->get('/social/users/{userId}/share-thread', [SocialUserController::class, 'shareThread']);
Route::middleware('jwt.auth')->patch('/social/users/{userId}/thread-notification-mute', [SocialUserController::class, 'threadMutePatch']);
Route::middleware('jwt.auth')->get('/social/users/{userId}', [SocialUserController::class, 'publicProfile']);
Route::middleware('jwt.auth')->post('/social/share-event', [SocialController::class, 'shareEvent']);
Route::middleware('jwt.auth')->get('/social/friend-invites', [SocialController::class, 'invitesIndex']);
Route::middleware('jwt.auth')->post('/social/friend-invites', [SocialController::class, 'invitesStore']);
Route::middleware('jwt.auth')->patch('/social/friend-invites/{inviteId}', [SocialController::class, 'invitesPatch']);

Route::middleware(['jwt.auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/logs', [AdminLogController::class, 'index']);
    Route::get('/summary', [AdminController::class, 'summary']);
    Route::post('/discovery/sync', [AdminController::class, 'discoverySync']);
    Route::get('/discovery/search', [AdminDiscoveryController::class, 'search']);
    Route::post('/discovery/import', [AdminDiscoveryController::class, 'importByExternalId']);
    Route::get('/events/metrics', [AdminController::class, 'eventsMetrics']);
    Route::get('/events', [AdminController::class, 'index']);
    Route::post('/events', [AdminController::class, 'store']);
    Route::patch('/events/{eventId}', [AdminController::class, 'updateEvent'])->whereNumber('eventId');
    Route::delete('/events/{eventId}', [AdminController::class, 'destroy'])->whereNumber('eventId');
    Route::get('/events/{eventId}/monitor', [AdminMonitorController::class, 'show'])->whereNumber('eventId');
    Route::get('/users', [AdminUsersController::class, 'index']);
    Route::post('/users', [AdminUsersController::class, 'store']);
    Route::patch('/users/{userId}', [AdminUsersController::class, 'update'])->whereNumber('userId');
    Route::delete('/users/{userId}', [AdminUsersController::class, 'destroy'])->whereNumber('userId');
    Route::get('/users/{userId}/orders', [AdminUsersController::class, 'orders'])->whereNumber('userId');
    Route::get('/orders/recent', [AdminUsersController::class, 'recentOrders']);
    Route::get('/analytics/summary', [AdminAnalyticsController::class, 'summary']);
    Route::get('/analytics/events', [AdminAnalyticsController::class, 'events']);
    Route::get('/analytics/categories/occupancy', [AdminAnalyticsController::class, 'categoryOccupancy']);
});

Route::middleware('jwt.auth')->post('/validation/scan', [ValidationController::class, 'scan']);
