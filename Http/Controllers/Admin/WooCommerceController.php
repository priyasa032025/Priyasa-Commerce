<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use Illuminate\Http\Request; use Modules\PriyasaCore\Jobs\SyncWooCommerce; use Modules\PriyasaCore\Services\WooCommerceService; use Throwable;
final class WooCommerceController extends Controller {
 public function status(WooCommerceService $woo){try{return response()->json(['data'=>array_merge(['configured'=>(bool)(config('priyasacore.woocommerce.store_url')&&config('priyasacore.woocommerce.consumer_key')&&config('priyasacore.woocommerce.consumer_secret'))],$woo->test())]);}catch(Throwable $e){return response()->json(['data'=>['configured'=>true,'connected'=>false,'error'=>$e->getMessage()]],503);}}
 public function sync(Request $r){$scope=in_array($r->input('scope','all'),['all','products','orders'],true)?$r->input('scope','all'):'all';$pages=max(1,min(100,(int)$r->input('max_pages',10)));$job=SyncWooCommerce::dispatch($scope,$pages);return response()->json(['message'=>'WooCommerce sync queued.','data'=>['scope'=>$scope,'max_pages'=>$pages]],202);}
 public function products(WooCommerceService $woo,Request $r){return response()->json(['data'=>$woo->syncProducts((int)$r->input('max_pages',1),(int)$r->input('per_page',100))]);}
 public function orders(WooCommerceService $woo,Request $r){return response()->json(['data'=>$woo->syncOrders((int)$r->input('max_pages',1))]);}
}
