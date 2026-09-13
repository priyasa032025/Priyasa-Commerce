<?php
use Illuminate\Support\Facades\Route;
use Modules\PriyasaCore\Http\Controllers\Admin\NotificationController;
use Modules\PriyasaCore\Http\Controllers\StorefrontNotificationController;
Route::prefix('v1')->group(function(){
 Route::prefix('storefront')->middleware('auth:sanctum')->group(function(){Route::get('notification-preferences',[StorefrontNotificationController::class,'preferences']);Route::put('notification-preferences',[StorefrontNotificationController::class,'updatePreferences']);});
 Route::prefix('admin')->middleware('auth:sanctum')->group(function(){Route::get('notifications/templates',[NotificationController::class,'templates']);Route::post('notifications/templates',[NotificationController::class,'storeTemplate']);Route::get('notifications/outbox',[NotificationController::class,'outbox']);Route::post('notifications/test',[NotificationController::class,'queueTest'])->middleware('idempotency');});
});
