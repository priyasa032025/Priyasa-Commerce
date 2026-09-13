<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Services\CheckoutExperienceService;
use Modules\PriyasaCore\Services\CheckoutService;
use Modules\PriyasaCore\Services\CustomerResolver;

final class CheckoutExperienceController extends Controller
{
    private function customer(Request $request): Customer
    {
        abort_unless($request->user(), 401, 'Unauthenticated.');
        return app(CustomerResolver::class)->resolve($request->user());
    }

    public function addresses(Request $request, CheckoutExperienceService $service)
    { return response()->json(['success'=>true,'data'=>$service->addresses($this->customer($request))]); }

    public function createAddress(Request $request, CheckoutExperienceService $service)
    {
        $data=$request->validate(['name'=>'required|string|max:120','phone'=>'nullable|string|max:32','address_line1'=>'required|string|max:255','address_line2'=>'nullable|string|max:255','landmark'=>'nullable|string|max:120','city'=>'required|string|max:120','state'=>'required|string|max:120','pincode'=>'required|string|regex:/^[0-9]{6}$/','country'=>'nullable|string|max:80','is_default'=>'nullable|boolean']);
        return response()->json(['success'=>true,'data'=>$service->createAddress($this->customer($request),$data)],201);
    }

    public function updateAddress(Request $request, int $address, CheckoutExperienceService $service)
    {
        $data=$request->validate(['name'=>'sometimes|string|max:120','phone'=>'nullable|string|max:32','address_line1'=>'sometimes|string|max:255','address_line2'=>'nullable|string|max:255','landmark'=>'nullable|string|max:120','city'=>'sometimes|string|max:120','state'=>'sometimes|string|max:120','pincode'=>'sometimes|string|regex:/^[0-9]{6}$/','country'=>'nullable|string|max:80','is_default'=>'nullable|boolean']);
        return response()->json(['success'=>true,'data'=>$service->updateAddress($this->customer($request),$address,$data)]);
    }

    public function deleteAddress(Request $request, int $address, CheckoutExperienceService $service)
    { $service->deleteAddress($this->customer($request),$address); return response()->json(['success'=>true,'data'=>null]); }

    public function setDefault(Request $request, int $address, CheckoutExperienceService $service)
    { return response()->json(['success'=>true,'data'=>$service->setDefaultAddress($this->customer($request),$address)]); }

    public function delivery(Request $request, CheckoutExperienceService $service)
    {
        $data=$request->validate(['shipping_address_id'=>'required|integer','order_value'=>'nullable|numeric|min:0']);
        return response()->json(['success'=>true,'data'=>$service->delivery($this->customer($request),(int)$data['shipping_address_id'],(float)($data['order_value']??0))]);
    }

    public function quote(Request $request, CheckoutService $checkout, CheckoutExperienceService $delivery)
    {
        $data=$request->validate(['shipping_address_id'=>'required|integer','coupon_code'=>'nullable|string|max:64']);
        $customer=$this->customer($request); $quote=$checkout->quote($customer,$data['coupon_code']??null);
        $orderValue=(float)($quote['total']??$quote['grand_total']??$quote['subtotal']??0);
        $ship=$delivery->delivery($customer,(int)$data['shipping_address_id'],$orderValue);
        $quote['delivery']=$ship; $quote['shipping_charge']=$ship['shipping_charge']; $quote['grand_total']=round($orderValue+$ship['shipping_charge'],2);
        return response()->json(['success'=>true,'data'=>$quote]);
    }

    public function place(Request $request, CheckoutService $checkout, CheckoutExperienceService $delivery)
    {
        $data=$request->validate(['shipping_address_id'=>'required|integer','coupon_code'=>'nullable|string|max:64','payment_method'=>'required|string|in:razorpay,cod']);
        $customer=$this->customer($request); $quote=$checkout->quote($customer,$data['coupon_code']??null); $base=(float)($quote['total']??$quote['grand_total']??$quote['subtotal']??0);
        $availability=$delivery->delivery($customer,(int)$data['shipping_address_id'],$base);
        if (!$availability['serviceable']) return response()->json(['success'=>false,'error'=>['code'=>'PINCODE_NOT_SERVICEABLE','message'=>'Delivery is unavailable for this pincode.']],422);
        if ($data['payment_method']==='cod' && !$availability['cod']['eligible']) return response()->json(['success'=>false,'error'=>['code'=>'COD_NOT_AVAILABLE','message'=>'Cash on Delivery is unavailable for this order.']],422);
        $key=trim((string)$request->header('Idempotency-Key','')) ?: null;
        $order=$checkout->place($customer,(int)$data['shipping_address_id'],strtolower($data['payment_method']),$data['coupon_code']??null,'website',$key);
        return response()->json(['success'=>true,'data'=>$order,'meta'=>['delivery'=>$availability,'idempotent'=>$key!==null]],201);
    }
}
