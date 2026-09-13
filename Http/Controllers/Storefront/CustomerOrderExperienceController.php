<?php
namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use Illuminate\Http\Request;
use Modules\PriyasaCore\Http\Controllers\Controller;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Services\{CustomerResolver,CustomerOrderExperienceService};

final class CustomerOrderExperienceController extends Controller
{
    private function customer(Request $r){ return app(CustomerResolver::class)->resolve($r); }
    private function own(Request $r, Order $order){ $c=$this->customer($r); abort_unless((int)$order->customer_id===(int)$c->id,403); return $c; }
    public function timeline(Request $r, Order $order){$this->own($r,$order); return response()->json(app(CustomerOrderExperienceService::class)->timeline($order));}
    public function reorder(Request $r, Order $order){$c=$this->own($r,$order); return response()->json(app(CustomerOrderExperienceService::class)->reorder($order->load('items'),$c));}
    public function reasons(){return response()->json(app(CustomerOrderExperienceService::class)->reasons());}
}
