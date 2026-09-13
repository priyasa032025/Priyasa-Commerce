<?php

declare(strict_types=1);
namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Services\SupportCommandCenterService;

final class SupportCommandCenterController extends Controller
{
    public function dashboard(Request $request, SupportCommandCenterService $service){ return response()->json(['success'=>true,'type'=>'support.command_center','data'=>$service->dashboard($request->all())]); }
    public function order(Request $request,int $order, SupportCommandCenterService $service){ return response()->json(['success'=>true,'type'=>'support.order_command_center','data'=>$service->orderContext($order)]); }
    public function ticket(int $ticket, SupportCommandCenterService $service){ return response()->json(['success'=>true,'type'=>'support.ticket_command_center','data'=>$service->ticket($ticket)]); }
    public function link(Request $request,int $ticket, SupportCommandCenterService $service){ $data=$request->validate(['return_id'=>'nullable|integer','refund_id'=>'nullable|integer','shipment_id'=>'nullable|integer','payment_transaction_id'=>'nullable|integer','conversation_id'=>'nullable|integer']); return response()->json(['success'=>true,'type'=>'support.ticket.linked','data'=>$service->link($ticket,$data,(string)optional($request->user())->id)]); }
    public function resolve(Request $request,int $ticket, SupportCommandCenterService $service){ $data=$request->validate(['resolution_code'=>'required|string|max:64']); return response()->json(['success'=>true,'type'=>'support.ticket.resolved','data'=>$service->resolve($ticket,$data['resolution_code'],(string)optional($request->user())->id)]); }
    public function reopen(Request $request,int $ticket, SupportCommandCenterService $service){ $data=$request->validate(['reason'=>'nullable|string|max:1000']); return response()->json(['success'=>true,'type'=>'support.ticket.reopened','data'=>$service->reopen($ticket,(string)optional($request->user())->id,$data['reason']??null)]); }
    public function notify(Request $request,int $ticket, SupportCommandCenterService $service){ $data=$request->validate(['event'=>'required|string|max:64']); return response()->json(['success'=>true,'type'=>'support.customer_notified','data'=>$service->notifyCustomer($ticket,$data['event'],(string)optional($request->user())->id)]); }
}
