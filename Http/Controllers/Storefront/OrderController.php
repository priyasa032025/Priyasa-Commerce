<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Services\OrderService;
use Modules\PriyasaCore\Services\ReturnService;
use Modules\PriyasaCore\Services\InventoryService;
use Modules\PriyasaCore\Services\CustomerResolver;

final class OrderController extends Controller
{
    private function customer(Request $request): Customer { return app(CustomerResolver::class)->resolve($request->user()); }
    private function owned(Request $request, Order $order): Order { abort_unless((int)$order->customer_id === (int)$this->customer($request)->id,404); return $order; }

    public function index(Request $request) { return response()->json(['success'=>true,'data'=>$this->customer($request)->orders()->with(['items','shipment'])->latest()->paginate(min(100,max(1,(int)$request->query('per_page',20))))]); }
    public function show(Request $request, Order $order) { return response()->json(['success'=>true,'data'=>$this->owned($request,$order)->load(['items','statusHistory','payment','paymentTransactions','shipment','returns','shippingAddress'])]); }

    public function cancel(Request $request, Order $order, OrderService $orders) {
        $order = $this->owned($request,$order);
        if (!in_array($order->status, ['pending_payment','payment_failed','confirmed','processing'], true)) return response()->json(['success'=>false,'message'=>'Order cannot be cancelled at this stage.'],422);
        $orders->transition($order, 'cancelled', 'customer', (string)$request->user()->id, 'Customer cancellation');
        return response()->json(['success'=>true,'data'=>$order->fresh()]);
    }

    public function tracking(Request $request, Order $order) {
        $order = $this->owned($request,$order)->load('shipment');
        if (!$order->shipment?->awb) return response()->json(['success'=>true,'data'=>['shipment'=>$order->shipment,'tracking'=>null]]);
        try {
            $tracking = app(\Modules\PriyasaCore\Integrations\Shipping\ShiprocketProvider::class)->track($order->shipment->awb);
            return response()->json(['success'=>true,'data'=>['shipment'=>$order->shipment,'tracking'=>$tracking]]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success'=>true,'data'=>['shipment'=>$order->shipment,'tracking'=>null],'meta'=>['tracking_available'=>false]]);
        }
    }
}
