<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Services\OrderService;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $q = Order::with('customer')->latest();
        if ($status = $request->query('status')) $q->where('status',$status);
        if ($search = trim((string)$request->query('search'))) $q->where(fn($x)=>$x->where('order_number','like',"%{$search}%"));
        return response()->json(['success'=>true,'data'=>$q->paginate(50)]);
    }

    public function show(Order $order)
    {
        return response()->json(['success'=>true,'data'=>$order->load(['customer','items','statusHistory','payment'])]);
    }

    public function confirmCod(Request $request, Order $order, OrderService $orders)
    {
        $updated=$orders->confirmCod($order,'admin',(string)$request->user()->id);
        return response()->json(['success'=>true,'data'=>$updated]);
    }

    public function status(Request $request, Order $order, OrderService $orders)
    {
        $data = $request->validate(['status'=>'required|string|max:32','note'=>'nullable|string|max:500']);
        $updated = $orders->transition($order, $data['status'], 'admin', (string)$request->user()->id, $data['note'] ?? null);
        return response()->json(['success'=>true,'data'=>$updated]);
    }
}