<?php
use Illuminate\Support\Facades\Route;
use Modules\PriyasaCore\Http\Controllers\Storefront\SearchIntelligenceController;
use Modules\PriyasaCore\Jobs\RebuildProductRelations;
use Modules\PriyasaCore\Http\Controllers\Admin\OperationsController;
Route::prefix('v1')->group(function(){
 Route::prefix('storefront')->group(function(){Route::get('search/intelligent',[SearchIntelligenceController::class,'search']);Route::get('search/intelligent/suggestions',[SearchIntelligenceController::class,'suggestions']);Route::get('trending',[SearchIntelligenceController::class,'trending']);Route::get('products/{product}/related',[SearchIntelligenceController::class,'related']);});
 Route::middleware(['auth:sanctum','priyasa.admin'])->prefix('admin/search')->group(function(){Route::post('rebuild-relations',function(){RebuildProductRelations::dispatch();return response()->json(['data'=>['queued'=>true]]);});});
});
