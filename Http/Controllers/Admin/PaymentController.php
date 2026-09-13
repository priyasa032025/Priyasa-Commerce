<?php
declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\Order;
use Modules\PriyasaCore\Services\GatewayManager;
use Modules\PriyasaCore\Services\RefundService;
final class PaymentController extends Controller {
 public function refund(Request $request, Order $order, GatewayManager $manager, RefundService $refunds) {
  $d=$request->validate(['amount'=>'nullable|numeric|min:0.01','reason'=>'nullable|string|max:255']);
  $payment=$order->payment()->first(); abort_unless($payment,422,'Payment not found.');
  $amountPaise=(int)round(((float)($d['amount']??$payment->amount-(float)$payment->refunded_amount))*100);
  $result=$refunds->refund($order,$amountPaise,$manager->payment($payment->provider),$d['reason']??'Admin refund');
  return response()->json(['success'=>true,'data'=>$result]);
 }
}
