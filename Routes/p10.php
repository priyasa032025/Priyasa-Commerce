<?php
use Illuminate\Support\Facades\Route;
use Modules\PriyasaCore\Http\Controllers\Admin\OperationsController;
Route::prefix('v1')->group(function(){
 Route::get('health',[OperationsController::class,'health']);
 Route::get('ready',[OperationsController::class,'readiness']);
 Route::middleware(['auth:sanctum','priyasa.admin'])->prefix('admin/ops')->group(function(){Route::get('metrics',[OperationsController::class,'metrics']);Route::get('audit',[OperationsController::class,'audit']);});
});
