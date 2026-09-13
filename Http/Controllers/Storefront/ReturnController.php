<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Models\ReturnRequest;
use Modules\PriyasaCore\Services\ReturnService;
use Modules\PriyasaCore\Services\CustomerResolver;

final class ReturnController extends Controller
{
    private function customer(Request $r): Customer { return app(CustomerResolver::class)->resolve($r->user()); }
    public function store(Request $r, Order $order, ReturnService $s) {
        $c=$this->customer($r); abort_unless((int)$order->customer_id===(int)$c->id,404);
        $d=$r->validate(['reason'=>'required|string|max:255','customer_note'=>'nullable|string|max:5000','metadata'=>'nullable|array','items'=>'nullable|array','items.*.order_item_id'=>'required_with:items|integer','items.*.quantity'=>'required_with:items|integer|min:1']);
        return response()->json(['success'=>true,'data'=>$s->request($c,$order,$d)->load('order')],201);
    }
    public function index(Request $r) {
        $c=$this->customer($r); return response()->json(['success'=>true,'data'=>$c->orders()->with(['returns','items'])->whereHas('returns')->latest()->paginate(config('priyasacore.per_page'))]);
    }
    public function show(Request $r, ReturnRequest $return) {
        $c=$this->customer($r); abort_unless((int)$return->customer_id===(int)$c->id,404);
        return response()->json(['success'=>true,'data'=>$return->load(['order.items','order.shipment'])]);
    }
}
