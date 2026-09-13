<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\PriyasaCore\Models\Address;
use Modules\PriyasaCore\Models\Customer;
use Modules\PriyasaCore\Services\CustomerResolver;

final class AddressController extends Controller
{
    private function customer(Request $r): Customer { return app(CustomerResolver::class)->resolve($r->user()); }
    private function data(Request $r): array { return $r->validate(['label'=>'nullable|string|max:50','recipient_name'=>'required|string|max:120','phone'=>'required|string|max:20','line1'=>'required|string|max:255','line2'=>'nullable|string|max:255','area'=>'nullable|string|max:120','city'=>'required|string|max:100','state'=>'required|string|max:100','postal_code'=>'required|string|max:20','country'=>'nullable|string|size:2','landmark'=>'nullable|string|max:255','is_default'=>'sometimes|boolean']); }
    public function index(Request $r){return response()->json(['success'=>true,'data'=>$this->customer($r)->addresses()->latest()->get()]);}
    public function store(Request $r){$c=$this->customer($r);$d=$this->data($r);$a=DB::connection('priyasa')->transaction(function()use($c,$d){if(($d['is_default']??false)||!$c->addresses()->exists())$c->addresses()->update(['is_default'=>false]);return $c->addresses()->create($d+['country'=>$d['country']??'IN','is_default'=>($d['is_default']??false)||!$c->addresses()->exists()]);});return response()->json(['success'=>true,'data'=>$a],201);}
    public function update(Request $r, Address $address){$c=$this->customer($r);abort_unless((int)$address->customer_id===(int)$c->id,404);$d=$this->data($r);$a=DB::connection('priyasa')->transaction(function()use($c,$address,$d){if(($d['is_default']??false))$c->addresses()->where('id','!=',$address->id)->update(['is_default'=>false]);$address->update($d);return $address->fresh();});return response()->json(['success'=>true,'data'=>$a]);}
    public function destroy(Request $r, Address $address){$c=$this->customer($r);abort_unless((int)$address->customer_id===(int)$c->id,404);DB::connection('priyasa')->transaction(function()use($c,$address){$was=$address->is_default;$address->delete();if($was)$c->addresses()->latest('id')->first()?->update(['is_default'=>true]);});return response()->json(['success'=>true]);}
}
