<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Services\GatewayManager;
use Modules\PriyasaCore\Services\PaymentService;
use Modules\PriyasaCore\Services\CustomerResolver;

final class PaymentController extends Controller
{
    private function customer(Request $request): Customer
    {
        abort_unless($request->user(), 401, 'Unauthenticated.');
        return app(CustomerResolver::class)->resolve($request->user());
    }

    private function owned(Request $request, Order $order): Order
    {
        abort_unless((int) $order->customer_id === (int) $this->customer($request)->id, 404);
        return $order;
    }

    public function create(Request $request, Order $order, GatewayManager $manager, PaymentService $service)
    {
        $order = $this->owned($request, $order);
        if (strtolower((string) $order->payment_method) !== 'razorpay') return response()->json(['success'=>false,'error'=>['code'=>'PAYMENT_NOT_REQUIRED','message'=>'This order does not require an online payment.']], 422);
        $payment = $service->create($order, $manager->payment('razorpay'));
        return response()->json(['success'=>true,'data'=>$payment], 201);
    }

    public function capture(Request $request, Order $order, GatewayManager $manager, PaymentService $service)
    {
        $order = $this->owned($request, $order);
        $data = $request->validate([
            'provider_payment_id'=>'required|string|max:191',
            'payload'=>'required|array',
            'payload.razorpay_order_id'=>'required|string|max:191',
            'payload.razorpay_signature'=>'required|string|max:191',
        ]);
        $payment = $service->capture($order, $data['provider_payment_id'], $manager->payment('razorpay'), $data['payload']);
        return response()->json(['success'=>true,'data'=>$payment]);
    }

    public function status(Request $request, Order $order)
    {
        $order = $this->owned($request, $order);
        return response()->json(['success'=>true,'data'=>$order->load('payment','paymentTransactions')]);
    }
}
