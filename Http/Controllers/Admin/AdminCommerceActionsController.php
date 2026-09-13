<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Services\AdminActionService;

final class AdminCommerceActionsController extends Controller
{
    private function key(Request $r): string { return trim((string)$r->header('Idempotency-Key')); }
    private function actor(Request $r): string { return (string)$r->user()->getAuthIdentifier(); }
    private function requireKey(Request $r): void { if ($this->key($r)==='') abort(400,'Idempotency-Key header is required for admin mutations.'); }

    public function orderTransition(Request $r,int $order,AdminActionService $s){$this->requireKey($r);$d=$r->validate(['to'=>'required|string|max:64','note'=>'nullable|string|max:1000']);return response()->json(['success'=>true,'type'=>'admin.order.transition','data'=>$s->orderTransition($order,$d['to'],$this->actor($r),$this->key($r),$d['note']??null),'api_version'=>'v1']);}
    public function inventoryAdjust(Request $r,AdminActionService $s){$this->requireKey($r);$d=$r->validate(['warehouse_id'=>'required|integer|min:1','variant_id'=>'required|integer|min:1','delta'=>'required|integer|between:-100000,100000','reason'=>'required|string|max:100']);return response()->json(['success'=>true,'type'=>'admin.inventory.adjusted','data'=>$s->inventoryAdjust($d['warehouse_id'],$d['variant_id'],$d['delta'],$d['reason'],$this->actor($r),$this->key($r)),'api_version'=>'v1']);}
    public function transfer(Request $r,AdminActionService $s){$this->requireKey($r);$d=$r->validate(['from_warehouse_id'=>'required|integer|min:1','to_warehouse_id'=>'required|integer|min:1|different:from_warehouse_id','variant_id'=>'required|integer|min:1','quantity'=>'required|integer|min:1|max:100000','note'=>'nullable|string|max:500']);return response()->json(['success'=>true,'type'=>'admin.warehouse.transfer_created','data'=>$s->warehouseTransfer($d['from_warehouse_id'],$d['to_warehouse_id'],$d['variant_id'],$d['quantity'],$d['note']??null,$this->actor($r),$this->key($r)),'api_version'=>'v1']);}
    public function fulfillment(Request $r,int $allocation,AdminActionService $s){$this->requireKey($r);$d=$r->validate(['operation'=>'required|in:pick,pack,cancel']);return response()->json(['success'=>true,'type'=>'admin.fulfillment.'.$d['operation'],'data'=>$s->fulfillment($allocation,$d['operation'],$this->actor($r),$this->key($r)),'api_version'=>'v1']);}
    public function returnAction(Request $r,int $return,AdminActionService $s){$this->requireKey($r);$d=$r->validate(['operation'=>'required|in:approve,reject,pickup,receive,qc_pass,qc_fail,refund,cancel']);return response()->json(['success'=>true,'type'=>'admin.return.'.$d['operation'],'data'=>$s->returnAction($return,$d['operation'],$this->actor($r),$this->key($r)),'api_version'=>'v1']);}
    public function refundAction(Request $r,int $refund,AdminActionService $s){$this->requireKey($r);$d=$r->validate(['amount_paise'=>'required|integer|min:1|max:100000000','reason'=>'nullable|string|max:255']);return response()->json(['success'=>true,'type'=>'admin.refund.initiated','data'=>$s->refundAction($refund,$d['amount_paise'],$this->actor($r),$this->key($r),$d['reason']??null),'api_version'=>'v1']);}
}
