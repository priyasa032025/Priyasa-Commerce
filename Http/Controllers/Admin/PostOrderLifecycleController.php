<?php
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use Illuminate\Http\Request; use Modules\PriyasaCore\Http\Controllers\Controller; use Modules\PriyasaCore\Models\{Order,ReturnRequest}; use Modules\PriyasaCore\Services\PostOrderLifecycleService;
class PostOrderLifecycleController extends Controller { public function eligibility(Order $order){return response()->json(app(PostOrderLifecycleService::class)->eligibility($order->load('items')));} public function cancel(Request $r,Order $order){return response()->json(app(PostOrderLifecycleService::class)->cancel($order,'admin',(string)$r->user()?->id));} public function returns(ReturnRequest $return){return response()->json($return->load('order','customer','items'));} }
